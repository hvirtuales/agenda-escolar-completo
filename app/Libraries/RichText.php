<?php

namespace App\Libraries;

/**
 * Texto con formato de los bloques del cuaderno (barra de formato flotante).
 *
 * El bloque guarda siempre `text` (texto plano: lo usan la IA, la búsqueda, las plantillas) y, solo si hay formato,
 * `html` limpio. Este limpiador es una lista blanca estricta; se aplica al guardar y otra vez al mostrar:
 * - Etiquetas: strong, em, u, s, sup, sub, mark (resaltado), span (color y tamaño de texto), ul/ol/li (listas) y br.
 *   b/i/strike/del se normalizan. Un li solo vale dentro de ul/ol; fuera de una lista se vuelve un salto de línea.
 * - Atributos: solo `class`, y solo de las listas fijas hl-* (resaltado), tc-* (color) y fs-* (tamaño). Ni style, ni href,
 *   ni on*: así no hay forma de inyectar scripts, enlaces ni estilos.
 * - Bloques (div, p, li…) que inserta el navegador al pulsar Enter se convierten en saltos de línea.
 * - Todo lo demás se desenvuelve (se conserva el texto); script/style/iframe… se descartan con su contenido.
 */
class RichText
{
    public const HIGHLIGHTS = ['yellow', 'green', 'blue', 'pink'];
    /** Colores de texto que ofrece la barra: el de marca y 3 variaciones (siguen el color de la institución). */
    public const BRAND_COLORS = ['brand', 'brand-dark', 'brand-deep', 'brand-light'];
    /** Permitidos al limpiar: los de marca y los anteriores (rojo, naranja…), para no perder el formato ya guardado. */
    public const COLORS     = ['brand', 'brand-dark', 'brand-deep', 'brand-light', 'red', 'orange', 'green', 'blue', 'purple', 'gray'];
    public const SIZES      = ['sm', 'lg', 'xl'];

    private const INLINE = ['strong' => 'strong', 'b' => 'strong', 'em' => 'em', 'i' => 'em', 'u' => 'u', 's' => 's', 'strike' => 's', 'del' => 's', 'sup' => 'sup', 'sub' => 'sub'];
    private const BLOCK  = ['div', 'p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'blockquote', 'pre', 'section', 'article'];
    private const DROP   = ['script', 'style', 'template', 'iframe', 'object', 'embed', 'svg', 'math', 'head', 'title', 'noscript', 'textarea', 'select', 'button', 'img', 'video', 'audio'];

    public static function clean(string $html, int $max = 30000): string
    {
        if (trim($html) === '') {
            return '';
        }
        $doc  = new \DOMDocument();
        $prev = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8"><div id="rt-root">' . mb_substr($html, 0, $max) . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        $root = $doc->getElementById('rt-root');
        if (! $root) {
            return '';
        }

        return (string) preg_replace('#^(?:<br>)+|(?:<br>)+$#', '', self::walk($root));
    }

    /** ¿El HTML limpio tiene formato de verdad (no solo texto y saltos)? Si no, basta el texto plano. */
    public static function hasFormatting(string $clean): bool
    {
        return (bool) preg_match('/<(strong|em|u|s|sup|sub|mark|span|ul|ol)\b/', $clean);
    }

    private static function walk(\DOMNode $node, bool $inList = false): string
    {
        $s     = '';
        $after = false; // acaba de cerrarse un bloque: lo que siga empieza en otra línea
        foreach ($node->childNodes as $c) {
            if ($c instanceof \DOMText) {
                $txt = htmlspecialchars($c->nodeValue, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                if ($inList) { // texto suelto dentro de ul/ol: se vuelve un ítem (los espacios entre ítems se ignoran)
                    $s .= trim($txt) !== '' ? '<li>' . trim($txt) . '</li>' : '';

                    continue;
                }
                if ($after && trim($txt) !== '' && ! str_ends_with($s, '<br>')) {
                    $s .= '<br>';
                }
                $after = $after && trim($txt) === '';
                $s .= $txt;

                continue;
            }
            if (! $c instanceof \DOMElement) {
                continue; // comentarios, instrucciones de procesamiento…
            }
            $tag = strtolower($c->tagName);
            if (in_array($tag, self::DROP, true)) {
                continue;
            }
            if ($tag === 'br') {
                $s .= '<br>';

                continue;
            }
            // Listas: ul/ol solo con sus li; un li suelto (fuera de una lista) se trata como una línea.
            if ($tag === 'ul' || $tag === 'ol') {
                $items = self::walk($c, true);
                if ($items !== '') {
                    $s .= "<{$tag}>{$items}</{$tag}>";
                }

                continue;
            }
            if ($tag === 'li' && $inList) {
                $x = self::walk($c);
                $s .= trim(strip_tags($x)) !== '' ? '<li>' . $x . '</li>' : ''; // sin ítems vacíos

                continue;
            }
            if ($inList) {
                // Dentro de ul/ol solo valen li: cualquier otra cosa se envuelve en uno.
                $x = self::walk($c);
                $s .= trim(strip_tags($x)) !== '' ? '<li>' . $x . '</li>' : '';

                continue;
            }
            $inner = self::walk($c);
            if ($tag === 'li' || in_array($tag, self::BLOCK, true)) {
                if ($s !== '' && ! str_ends_with($s, '<br>') && ! str_ends_with($s, "\n")) {
                    $s .= '<br>';
                }
                $s    .= $inner;
                $after = true;

                continue;
            }
            if ($inner === '') {
                continue;
            }
            if ($after && ! str_ends_with($s, '<br>')) {
                $s .= '<br>';
            }
            $after = false;
            if (isset(self::INLINE[$tag])) {
                $t = self::INLINE[$tag];
                $s .= "<{$t}>{$inner}</{$t}>";

                continue;
            }
            if ($tag === 'mark' || $tag === 'span') {
                $classes = preg_split('/\s+/', trim((string) $c->getAttribute('class'))) ?: [];
                $hl      = self::pick($classes, 'hl-', self::HIGHLIGHTS) ?? ($tag === 'mark' ? 'hl-yellow' : null);
                $tc      = self::pick($classes, 'tc-', self::COLORS);
                $fs      = self::pick($classes, 'fs-', self::SIZES);
                $out     = $inner;
                if ($fs) {
                    $out = '<span class="' . $fs . '">' . $out . '</span>';
                }
                if ($tc) {
                    $out = '<span class="' . $tc . '">' . $out . '</span>';
                }
                if ($hl) {
                    $out = '<mark class="' . $hl . '">' . $out . '</mark>';
                }
                $s .= $out;

                continue;
            }
            $s .= $inner; // etiqueta no permitida: se conserva solo su contenido
        }

        return $s;
    }

    private static function pick(array $classes, string $prefix, array $allowed): ?string
    {
        foreach ($classes as $cl) {
            if (str_starts_with($cl, $prefix) && in_array(substr($cl, strlen($prefix)), $allowed, true)) {
                return $cl;
            }
        }

        return null;
    }
}
