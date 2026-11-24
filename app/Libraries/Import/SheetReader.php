<?php

namespace App\Libraries\Import;

/**
 * Lee y escribe hojas de cálculo sencillas sin librerías externas.
 *
 * - read(): .xlsx (ZipArchive + XML de la primera hoja) o .csv (con o sin BOM, «;» o «,», UTF-8 o Windows-1252).
 *   Devuelve filas como arreglos de texto (las celdas numéricas se leen como texto: no se pierden los ceros iniciales).
 * - writeXlsx(): un .xlsx mínimo de una hoja (encabezados en negrita, todo como texto), para las plantillas.
 */
class SheetReader
{
    public const MAX_BYTES = 2 * 1024 * 1024;
    public const MAX_ROWS  = 2000;

    /**
     * @return array{rows?: list<list<string>>, error?: string}
     */
    public static function read(string $path, string $ext): array
    {
        $ext = strtolower($ext);
        if (! is_file($path) || filesize($path) > self::MAX_BYTES) {
            return ['error' => 'El archivo debe pesar como máximo 2 MB.'];
        }
        $rows = match ($ext) {
            'xlsx'  => self::readXlsx($path),
            'csv'   => self::readCsv((string) file_get_contents($path)),
            default => null,
        };
        if ($rows === null) {
            return ['error' => $ext === 'xlsx' ? 'No se pudo leer el Excel. Guárdelo de nuevo como «Libro de Excel (.xlsx)» e intente otra vez.' : 'Suba un archivo .xlsx o .csv.'];
        }
        // Sin filas vacías al final.
        $rows = array_values(array_filter($rows, static fn ($r) => implode('', array_map('trim', $r)) !== ''));
        if (count($rows) > self::MAX_ROWS + 1) {
            return ['error' => 'El archivo tiene más de ' . self::MAX_ROWS . ' filas. Divídalo en varios archivos.'];
        }

        return ['rows' => $rows];
    }

    /** CSV: quita BOM, detecta el separador y la codificación. */
    public static function readCsv(string $raw): array
    {
        if (str_starts_with($raw, "\xEF\xBB\xBF")) {
            $raw = substr($raw, 3);
        }
        if (! mb_check_encoding($raw, 'UTF-8')) {
            $raw = mb_convert_encoding($raw, 'UTF-8', 'Windows-1252');
        }
        $first = strtok($raw, "\n") ?: '';
        $sep   = substr_count($first, ';') >= substr_count($first, ',') ? ';' : ',';
        $rows  = [];
        $h     = fopen('php://memory', 'r+');
        fwrite($h, $raw);
        rewind($h);
        while (($r = fgetcsv($h, 0, $sep, '"', '')) !== false) {
            $rows[] = array_map(static fn ($c) => trim((string) $c), $r);
        }
        fclose($h);

        return $rows;
    }

    /** XLSX: primera hoja, con textos compartidos y en línea. */
    private static function readXlsx(string $path): ?array
    {
        if (! class_exists(\ZipArchive::class)) {
            return null;
        }
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            return null;
        }
        $shared = [];
        if (($xml = $zip->getFromName('xl/sharedStrings.xml')) !== false) {
            $sst = @simplexml_load_string($xml, \SimpleXMLElement::class, LIBXML_NONET);
            foreach ($sst ? $sst->si : [] as $si) {
                $shared[] = isset($si->t) ? (string) $si->t : implode('', array_map(static fn ($r) => (string) $r->t, iterator_to_array($si->r, false)));
            }
        }
        $sheetPath = self::firstSheet($zip);
        $xml       = $sheetPath ? $zip->getFromName($sheetPath) : false;
        $zip->close();
        if ($xml === false) {
            return null;
        }
        $sheet = @simplexml_load_string($xml, \SimpleXMLElement::class, LIBXML_NONET);
        if (! $sheet || ! isset($sheet->sheetData)) {
            return null;
        }
        $rows = [];
        foreach ($sheet->sheetData->row as $row) {
            $cells = [];
            foreach ($row->c as $c) {
                $col  = self::colIndex((string) $c['r']);
                $type = (string) $c['t'];
                $val  = match ($type) {
                    's'         => $shared[(int) $c->v] ?? '',
                    'inlineStr' => (string) ($c->is->t ?? ''),
                    'b'         => ((string) $c->v) === '1' ? 'VERDADERO' : 'FALSO',
                    default     => self::number((string) $c->v),
                };
                $cells[$col] = trim($val);
            }
            if ($cells) {
                $line = array_fill(0, max(array_keys($cells)) + 1, '');
                foreach ($cells as $i => $v) {
                    $line[$i] = $v;
                }
                $rows[] = $line;
            }
        }

        return $rows;
    }

    /** Ruta de la primera hoja según workbook.xml (por si no es sheet1.xml). */
    private static function firstSheet(\ZipArchive $zip): ?string
    {
        $wb   = $zip->getFromName('xl/workbook.xml');
        $rels = $zip->getFromName('xl/_rels/workbook.xml.rels');
        if ($wb !== false && $rels !== false) {
            $w = @simplexml_load_string($wb, \SimpleXMLElement::class, LIBXML_NONET);
            $r = @simplexml_load_string($rels, \SimpleXMLElement::class, LIBXML_NONET);
            if ($w && $r && isset($w->sheets->sheet[0])) {
                $rid = (string) $w->sheets->sheet[0]->attributes('r', true)['id'];
                foreach ($r->Relationship as $rel) {
                    if ((string) $rel['Id'] === $rid) {
                        $t = ltrim((string) $rel['Target'], '/');

                        return str_starts_with($t, 'xl/') ? $t : 'xl/' . $t;
                    }
                }
            }
        }

        return $zip->locateName('xl/worksheets/sheet1.xml') !== false ? 'xl/worksheets/sheet1.xml' : null;
    }

    /** «AB12» → 27. */
    private static function colIndex(string $ref): int
    {
        $letters = preg_replace('/\d/', '', $ref);
        $n       = 0;
        foreach (str_split(strtoupper($letters)) as $ch) {
            $n = $n * 26 + (ord($ch) - 64);
        }

        return max(0, $n - 1);
    }

    /** Números de Excel como texto: 1012345678.0 → «1012345678»; 1.0E+9 → «1000000000». */
    private static function number(string $v): string
    {
        if ($v === '' || ! is_numeric($v)) {
            return $v;
        }
        $f = (float) $v;

        return floor($f) === $f && abs($f) < 1e15 ? number_format($f, 0, '', '') : $v;
    }

    /** Escribe un .xlsx de una hoja: encabezados en negrita, todas las celdas como texto. Devuelve el contenido binario. */
    public static function writeXlsx(array $header, array $rows, string $sheetName = 'Datos'): string
    {
        $esc  = static fn (string $s): string => htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $col  = static function (int $i): string {
            $s = '';
            for ($i++; $i > 0; $i = intdiv($i - 1, 26)) {
                $s = chr(65 + ($i - 1) % 26) . $s;
            }

            return $s;
        };
        $data = '';
        foreach (array_merge([$header], $rows) as $r => $row) {
            $data .= '<row r="' . ($r + 1) . '">';
            foreach (array_values($row) as $c => $v) {
                $data .= '<c r="' . $col($c) . ($r + 1) . '" t="inlineStr" s="' . ($r === 0 ? 1 : 2) . '"><is><t>' . $esc((string) $v) . '</t></is></c>';
            }
            $data .= '</row>';
        }
        $cols = '<cols>';
        foreach ($header as $i => $h) {
            $cols .= '<col min="' . ($i + 1) . '" max="' . ($i + 1) . '" width="' . max(14, min(40, mb_strlen($h) + 4)) . '" customWidth="1"/>';
        }
        $cols .= '</cols>';

        $files = [
            '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>',
            '_rels/.rels'         => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>',
            'xl/workbook.xml'     => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="' . $esc($sheetName) . '" sheetId="1" r:id="rId1"/></sheets></workbook>',
            'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>',
            // Estilos: 0 normal, 1 encabezado (negrita, fondo), 2 texto («@» para que Excel no convierta documentos en números).
            'xl/styles.xml'       => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts><fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FFE8F5E9"/></patternFill></fill></fills><borders count="1"><border/></borders><cellStyleXfs count="1"><xf/></cellStyleXfs><cellXfs count="3"><xf/><xf fontId="1" fillId="2" applyFont="1" applyFill="1"/><xf numFmtId="49" applyNumberFormat="1"/></cellXfs></styleSheet>',
            'xl/worksheets/sheet1.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>' . $cols . '<sheetData>' . $data . '</sheetData></worksheet>',
        ];
        $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
        $zip = new \ZipArchive();
        $zip->open($tmp, \ZipArchive::OVERWRITE);
        foreach ($files as $name => $content) {
            $zip->addFromString($name, $content);
        }
        $zip->close();
        $bin = (string) file_get_contents($tmp);
        @unlink($tmp);

        return $bin;
    }
}
