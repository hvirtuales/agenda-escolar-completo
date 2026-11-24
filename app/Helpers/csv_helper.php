<?php

/**
 * Exportación CSV única de la plataforma: separador «;», UTF-8 con BOM (Excel en español la abre bien) y saltos CRLF.
 * Cada celda va entre comillas y las que empiezan por = + - @ (o tabulador/retorno) se neutralizan con un apóstrofo
 * para que Excel no las ejecute como fórmula (inyección CSV). Los números negativos se dejan como están.
 */
if (! function_exists('csv_cell')) {
    function csv_cell($value): string
    {
        $v = (string) ($value ?? '');
        if ($v !== '' && preg_match('/^[=+\-@\t\r]/', $v) && ! preg_match('/^-?\d+([.,]\d+)?$/', $v)) {
            $v = "'" . $v;
        }

        return '"' . str_replace('"', '""', $v) . '"';
    }
}

if (! function_exists('csv_build')) {
    /** @param list<string> $head  @param iterable<list<scalar|null>> $rows */
    function csv_build(array $head, iterable $rows): string
    {
        $out = [implode(';', array_map('csv_cell', $head))];
        foreach ($rows as $r) {
            $out[] = implode(';', array_map('csv_cell', $r));
        }

        return "\xEF\xBB\xBF" . implode("\r\n", $out) . "\r\n";
    }
}
