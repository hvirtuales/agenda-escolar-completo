<?php

namespace App\Libraries;

/**
 * Centro de ayuda (Config\HelpCenter): artículos visibles según el rol, búsqueda y navegación. Es la base de
 * conocimiento de la Ayuda y del asistente flotante. Úsela con service('help').
 */
class HelpCenter
{
    private ?array $visible = null;

    /** Públicos que ve quien navega (además de «all»). */
    public function audiences(): array
    {
        $t = service('tenant');
        if (service('auth')->isSuperadmin() && ! $t->institution()) {
            return ['all', 'platform', 'admin', 'teacher'];
        }

        return match (true) {
            $t->isStudent()                => ['all', 'student'],
            $t->isGuardian()               => ['all', 'guardian'],
            $t->can('tenant.users.manage') || $t->can('academic.structure.manage') => ['all', 'admin', 'teacher'] + (service('auth')->isSuperadmin() ? [3 => 'platform'] : []),
            $t->can('content.edit')        => ['all', 'teacher'],
            default                        => ['all'],
        };
    }

    /** Artículos visibles para quien navega, en el orden de las categorías. Clave: slug. */
    public function articles(): array
    {
        if ($this->visible !== null) {
            return $this->visible;
        }
        $cfg  = config('HelpCenter');
        $aud  = $this->audiences();
        $list = array_filter($cfg->articles, static fn ($a) => array_intersect($a['audience'], $aud) !== []);
        $ord  = array_flip($cfg->categories);
        uksort($list, static fn ($x, $y) => [$ord[$list[$x]['category']] ?? 99, array_search($x, array_keys($cfg->articles), true)]
            <=> [$ord[$list[$y]['category']] ?? 99, array_search($y, array_keys($cfg->articles), true)]);

        return $this->visible = $list;
    }

    /** Índice: [categoría => [slug => título]]. */
    public function index(): array
    {
        $out = [];
        foreach ($this->articles() as $slug => $a) {
            $out[$a['category']][$slug] = $a['title'];
        }

        return $out;
    }

    public function find(string $slug): ?array
    {
        $a = $this->articles()[$slug] ?? null;

        return $a ? $a + ['slug' => $slug] : null;
    }

    /** Anterior y siguiente dentro del índice visible. */
    public function neighbors(string $slug): array
    {
        $keys = array_keys($this->articles());
        $i    = array_search($slug, $keys, true);

        return [$i > 0 ? $this->find($keys[$i - 1]) : null, $i !== false && $i < count($keys) - 1 ? $this->find($keys[$i + 1]) : null];
    }

    public static function norm(string $s): string
    {
        return strtr(mb_strtolower($s), ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']);
    }

    /** Texto completo de un artículo (para buscar y para el contexto de la IA). */
    public static function text(array $a): string
    {
        return trim($a['title'] . ". " . $a['summary'] . "\n" . implode("\n", array_map(static fn ($s, $i) => ($i + 1) . '. ' . $s, $a['steps'] ?? [], array_keys($a['steps'] ?? [])))
            . "\n" . implode("\n", $a['body'] ?? []) . (isset($a['tip']) ? "\nConsejo: " . $a['tip'] : ''));
    }

    /**
     * Busca entre los artículos visibles. Puntaje: palabras clave (3), título (2), resumen y texto (1), siempre por
     * palabra completa o prefijo («calific» encuentra «calificar»), nunca dentro de otra palabra («ia» ≠ «Francia»).
     * Devuelve [[slug, score, article], …] ordenados de mayor a menor, solo con puntaje > 0.
     */
    public function search(string $query, int $limit = 5): array
    {
        $stop  = ['como', 'donde', 'que', 'cual', 'para', 'por', 'una', 'uno', 'los', 'las', 'del', 'con', 'mis', 'mi', 'el', 'la', 'de', 'en', 'un', 'y', 'o', 'a', 'se', 'es', 'lo', 'puedo', 'hago', 'quiero', 'veo', 'esta'];
        $words = array_values(array_filter(preg_split('/[^a-z0-9]+/', self::norm($query)), static fn ($w) => mb_strlen($w) > 1 && ! in_array($w, $stop, true)));
        $full  = ' ' . trim(preg_replace('/[^a-z0-9]+/', ' ', self::norm($query))) . ' '; // con «de», «mi»…: para las frases clave
        $res   = [];
        $mine  = array_diff($this->audiences(), ['all']);
        foreach ($this->articles() as $slug => $a) {
            $score = 0;
            foreach ($a['keywords'] as $kw) { // frases clave completas (una frase más larga pesa más)
                if (preg_match('/(?<![a-z0-9])' . preg_quote(self::norm($kw), '/') . '/u', $full)) {
                    $score += 3 + 2 * substr_count($kw, ' ');
                }
            }
            $title = ' ' . self::norm($a['title']) . ' ';
            $body  = ' ' . self::norm(self::text($a)) . ' ';
            foreach ($words as $w) {
                $re = '/(?<![a-z0-9])' . preg_quote(mb_strlen($w) > 5 ? mb_substr($w, 0, -1) : $w, '/') . '/u';
                $score += preg_match($re, $title) ? 2 : 0;
                $score += preg_match($re, $body) ? 1 : 0;
            }
            if ($score > 0) {
                // Ante un empate, gana el artículo escrito para el rol de quien pregunta (p. ej. el de acudientes).
                $score += array_intersect($a['audience'], $mine) && ! in_array('all', $a['audience'], true) ? 2 : 0;
                $res[] = [$slug, $score, $a + ['slug' => $slug]];
            }
        }
        usort($res, static fn ($x, $y) => $y[1] <=> $x[1]);

        return array_slice($res, 0, $limit);
    }
}
