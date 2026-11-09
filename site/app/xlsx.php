<?php
declare(strict_types=1);

/** Small Excel reader and writer, so the app needs no installed libraries. */

function zip_xml(ZipArchive $zip, string $path): ?SimpleXMLElement
{
    $text = $zip->getFromName($path);
    if ($text === false) {
        return null;
    }
    $previous = libxml_use_internal_errors(true);
    $xml = simplexml_load_string($text, SimpleXMLElement::class, LIBXML_NONET);
    libxml_use_internal_errors($previous);
    if ($xml === false) {
        throw new RuntimeException("Invalid XML in $path");
    }
    return $xml;
}

function xlsx_date_styles(ZipArchive $zip): array
{
    $styles = zip_xml($zip, 'xl/styles.xml');
    if (!$styles) {
        return [];
    }
    $custom = [];
    foreach ($styles->numFmts->numFmt ?? [] as $format) {
        $code = preg_replace('/\[[^\]]*\]|"[^"]*"/', '', (string) $format['formatCode']);
        $custom[(int) $format['numFmtId']] = (bool) preg_match('/[dmyh]/i', (string) $code);
    }
    $dates = [];
    $index = 0;
    foreach ($styles->cellXfs->xf ?? [] as $xf) {
        $id = (int) $xf['numFmtId'];
        $dates[$index++] = ($id >= 14 && $id <= 22) || ($id >= 45 && $id <= 47) || !empty($custom[$id]);
    }
    return $dates;
}

function xlsx_column(string $reference): int
{
    $letters = preg_replace('/\d/', '', $reference);
    $column = 0;
    foreach (str_split((string) $letters) as $letter) {
        $column = $column * 26 + ord(strtoupper($letter)) - 64;
    }
    return $column - 1;
}

function xml_text(SimpleXMLElement $node): string
{
    $text = '';
    if (isset($node->t)) {
        $text = (string) $node->t;
    }
    foreach ($node->r ?? [] as $run) {
        $text .= (string) $run->t;
    }
    return $text;
}

/** Returns rows of the first worksheet as ['number' => n, 'cells' => [...]]. */
function read_xlsx(string $path): array
{
    if (!class_exists(ZipArchive::class)) {
        throw new UserError('Excel files are not available on this server. Upload a CSV file instead.');
    }
    $zip = new ZipArchive();
    if ($zip->open($path, ZipArchive::RDONLY) !== true) {
        throw new RuntimeException('Not a zip file');
    }
    try {
        $workbook = zip_xml($zip, 'xl/workbook.xml');
        $relations = zip_xml($zip, 'xl/_rels/workbook.xml.rels');
        if (!$workbook || !$relations || !isset($workbook->sheets->sheet[0])) {
            throw new RuntimeException('Not a workbook');
        }
        $sheetId = (string) $workbook->sheets->sheet[0]->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];
        $target = '';
        foreach ($relations->Relationship as $relation) {
            if ((string) $relation['Id'] === $sheetId) {
                $target = ltrim((string) $relation['Target'], '/');
            }
        }
        $target = str_starts_with($target, 'xl/') ? $target : 'xl/' . $target;
        $strings = [];
        $shared = zip_xml($zip, 'xl/sharedStrings.xml');
        foreach ($shared->si ?? [] as $item) {
            $strings[] = xml_text($item);
        }
        $dateStyles = xlsx_date_styles($zip);
        $sheet = zip_xml($zip, $target);
        if (!$sheet) {
            throw new RuntimeException('Missing worksheet');
        }
        $table = [];
        $number = 0;
        foreach ($sheet->sheetData->row ?? [] as $row) {
            $number = isset($row['r']) ? (int) $row['r'] : $number + 1;
            $cells = [];
            foreach ($row->c as $cell) {
                $column = isset($cell['r']) ? xlsx_column((string) $cell['r']) : count($cells);
                $type = (string) $cell['t'];
                $raw = (string) $cell->v;
                if ($type === 's') {
                    $value = $strings[(int) $raw] ?? '';
                } elseif ($type === 'inlineStr') {
                    $value = xml_text($cell->is);
                } elseif ($type === 'e') {
                    $value = null;
                } elseif ($raw === '') {
                    $value = null;
                } elseif ($type === '' || $type === 'n') {
                    if (!empty($dateStyles[(int) $cell['s']])) {
                        $value = (new DateTimeImmutable('1899-12-30'))->modify('+' . (int) floor((float) $raw) . ' days');
                    } else {
                        $value = $raw;
                    }
                } else {
                    $value = $raw;
                }
                while (count($cells) < $column) {
                    $cells[] = null;
                }
                $cells[$column] = $value;
            }
            $table[] = ['number' => $number, 'cells' => $cells];
        }
        return $table;
    } finally {
        $zip->close();
    }
}

function xlsx_column_name(int $index): string
{
    $name = '';
    for ($index++; $index > 0; $index = (int) (($index - 1) / 26)) {
        $name = chr(65 + ($index - 1) % 26) . $name;
    }
    return $name;
}

function xml_safe(string $text): string
{
    return htmlspecialchars((string) preg_replace('/[^\x09\x0A\x0D\x20-\x{D7FF}\x{E000}-\x{FFFD}]/u', '', $text), ENT_QUOTES | ENT_XML1, 'UTF-8');
}

/** Sends a workbook download. $sheets is [name => [rows]], with the first row bold. */
function send_xlsx(string $filename, array $sheets): never
{
    if (!class_exists(ZipArchive::class)) {
        http_response_code(500);
        exit('Excel files are not available on this server.');
    }
    $path = tempnam(sys_get_temp_dir(), 'xlsx');
    $zip = new ZipArchive();
    $zip->open($path, ZipArchive::OVERWRITE);
    $overrides = '';
    $entries = '';
    $relations = '';
    $number = 0;
    foreach ($sheets as $name => $table) {
        $number++;
        $xml = '';
        foreach ($table as $rowIndex => $cells) {
            $xml .= '<row r="' . ($rowIndex + 1) . '">';
            foreach (array_values($cells) as $columnIndex => $cell) {
                $reference = xlsx_column_name($columnIndex) . ($rowIndex + 1);
                $style = $rowIndex === 0 ? ' s="1"' : '';
                if (is_int($cell) || is_float($cell)) {
                    $xml .= "<c r=\"$reference\"$style><v>$cell</v></c>";
                } else {
                    $xml .= "<c r=\"$reference\"$style t=\"inlineStr\"><is><t xml:space=\"preserve\">" . xml_safe((string) $cell) . '</t></is></c>';
                }
            }
            $xml .= '</row>';
        }
        $zip->addFromString("xl/worksheets/sheet$number.xml", '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetFormatPr defaultRowHeight="15"/><cols><col min="1" max="12" width="22" customWidth="1"/></cols><sheetData>' . $xml . '</sheetData></worksheet>');
        $overrides .= "<Override PartName=\"/xl/worksheets/sheet$number.xml\" ContentType=\"application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml\"/>";
        $entries .= '<sheet name="' . xml_safe($name) . "\" sheetId=\"$number\" r:id=\"rId$number\"/>";
        $relations .= "<Relationship Id=\"rId$number\" Type=\"http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet\" Target=\"worksheets/sheet$number.xml\"/>";
    }
    $stylesId = $number + 1;
    $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>' . $overrides . '</Types>');
    $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
    $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>' . $entries . '</sheets></workbook>');
    $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' . $relations . "<Relationship Id=\"rId$stylesId\" Type=\"http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles\" Target=\"styles.xml\"/></Relationships>");
    $zip->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts><fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills><borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/></cellXfs></styleSheet>');
    $zip->close();
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($path));
    readfile($path);
    unlink($path);
    exit;
}
