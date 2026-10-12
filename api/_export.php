<?php
/**
 * SSMUNIE — Helpers d'export (B3) : CSV, XLSX (ZipArchive), PDF (minimal),
 * VCF (vCard 3.0 / WhatsApp).
 * Aucune dépendance externe.
 */

declare(strict_types=1);

if (!function_exists('ssmunie_xml_escape')) {
    function ssmunie_xml_escape(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}

if (!function_exists('ssmunie_col_letter')) {
    /** 0 → A, 25 → Z, 26 → AA. */
    function ssmunie_col_letter(int $n): string
    {
        $s = '';
        $n++;
        while ($n > 0) {
            $n--;
            $s = chr(65 + ($n % 26)) . $s;
            $n = intdiv($n, 26);
        }
        return $s;
    }
}

if (!function_exists('ssmunie_export_csv')) {
    function ssmunie_export_csv(array $headers, array $rows): string
    {
        $fp = fopen('php://temp', 'r+');
        fwrite($fp, "\xEF\xBB\xBF"); // BOM UTF-8 (Excel)
        fputcsv($fp, $headers, ';');
        foreach ($rows as $r) {
            fputcsv($fp, array_values($r), ';');
        }
        rewind($fp);
        $data = stream_get_contents($fp);
        fclose($fp);
        return $data;
    }
}

if (!function_exists('ssmunie_export_xlsx')) {
    function ssmunie_export_xlsx(array $headers, array $rows, string $sheetName = 'Feuille1'): string
    {
        $colCount = max(1, count($headers));
        $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "
"
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';

        $all = array_merge([$headers], $rows);
        foreach ($all as $ri => $row) {
            $rn = $ri + 1;
            $cells = '';
            for ($ci = 0; $ci < $colCount; $ci++) {
                $val  = $row[$ci] ?? '';
                $ref  = ssmunie_col_letter($ci) . $rn;
                if (is_numeric($val) && preg_match('/^-?\d+(\.\d+)?$/', (string) $val)) {
                    $cells .= '<c r="' . $ref . '"><v>' . ssmunie_xml_escape((string) $val) . '</v></c>';
                } else {
                    $cells .= '<c r="' . $ref . '" t="inlineStr"><is><t xml:space="preserve">' . ssmunie_xml_escape((string) $val) . '</t></is></c>';
                }
            }
            $sheetXml .= '<row r="' . $rn . '">' . $cells . '</row>';
        }
        $sheetXml .= '</sheetData></worksheet>';

        $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "
"
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '</Types>';

        $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "
"
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>';

        $workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "
"
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="' . ssmunie_xml_escape($sheetName) . '" sheetId="1" r:id="rId1"/></sheets>'
            . '</workbook>';

        $workbookRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "
"
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '</Relationships>';

        $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
        $zip = new ZipArchive();
        if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) {
            return '';
        }
        $zip->addFromString('[Content_Types].xml', $contentTypes);
        $zip->addFromString('_rels/.rels', $rels);
        $zip->addFromString('xl/workbook.xml', $workbook);
        $zip->addFromString('xl/_rels/workbook.xml.rels', $workbookRels);
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);
        $zip->close();

        $data = (string) file_get_contents($tmp);
        @unlink($tmp);
        return $data;
    }
}

if (!function_exists('ssmunie_pdf_escape')) {
    function ssmunie_pdf_escape(string $s): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $s);
    }
}

if (!function_exists('ssmunie_pdf_cell')) {
    function ssmunie_pdf_cell($text, float $width, float $fs): string
    {
        $text     = (string) $text;
        $maxChars = max(4, (int) floor(($width - 6) / (0.5 * $fs)));
        if (function_exists('mb_strlen') && mb_strlen($text) > $maxChars) {
            $text = mb_substr($text, 0, $maxChars - 1) . '…';
        } elseif (strlen($text) > $maxChars) {
            $text = substr($text, 0, $maxChars - 1) . '.';
        }
        $conv = @iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $text);
        if ($conv === false) {
            $conv = preg_replace('/[^\x20-\x7E]/', '', $text);
        }
        return ssmunie_pdf_escape((string) $conv);
    }
}

if (!function_exists('ssmunie_export_pdf')) {
    function ssmunie_export_pdf(array $headers, array $rows, string $title): string
    {
        $pageW = 595.28; $pageH = 841.89;
        $margin = 36.0;
        $contentW = $pageW - 2 * $margin;
        $ncol = max(1, count($headers));
        $colW = $contentW / $ncol;
        $fs = 8.0; $fsHead = 8.5; $fsTitle = 14.0;
        $lh = 14.0;

        $pages = [];
        $cur   = [];
        $y     = $pageH - $margin;

        $addText = function (float $x, float $yy, string $txt, string $font, float $size) use (&$cur) {
            $cur[] = "BT /$font $size Tf $x $yy Td (" . ssmunie_pdf_escape($txt) . ") Tj ET";
        };

        $newRow = function (float $top) use (&$pages, &$cur, &$y, $lh, $margin) {
            if ($y < $margin + $lh) {
                $pages[] = $cur;
                $cur = [];
                $y = $top;
            }
        };

        // Titre
        $addText($margin, $y - 2, $title, 'F2', $fsTitle);
        $y -= 30;

        // En-têtes
        foreach ($headers as $i => $h) {
            $x = $margin + $i * $colW;
            $addText($x + 2, $y, ssmunie_pdf_cell($h, $colW, $fsHead), 'F2', $fsHead);
        }
        $y -= $lh;

        foreach ($rows as $row) {
            if ($y < $margin + $lh) {
                $pages[] = $cur;
                $cur = [];
                $y = $pageH - $margin;
            }
            foreach ($headers as $i => $h) {
                $val = $row[$i] ?? '';
                $x = $margin + $i * $colW;
                $addText($x + 2, $y, ssmunie_pdf_cell($val, $colW, $fs), 'F1', $fs);
            }
            $y -= $lh;
        }
        $pages[] = $cur;

        $nPages = count($pages);
        $objs = [];
        $objs[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $kids = [];
        for ($i = 0; $i < $nPages; $i++) $kids[] = (5 + 2 * $i) . ' 0 R';
        $objs[2] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . $nPages . ' >>';
        $objs[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objs[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';

        for ($i = 0; $i < $nPages; $i++) {
            $pageId = 5 + 2 * $i;
            $contId = 6 + 2 * $i;
            $content = implode("
", $pages[$i]);
            $objs[$pageId] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents ' . $contId . ' 0 R >>';
            $objs[$contId] = '<< /Length ' . strlen($content) . ' >>' . "
" . 'stream' . "
" . $content . "
" . 'endstream';
        }

        $out = "%PDF-1.4
";
        $offsets = [];
        foreach ($objs as $id => $body) {
            $offsets[$id] = strlen($out);
            $out .= "$id 0 obj
" . $body . "
endobj
";
        }
        $xrefPos = strlen($out);
        $count = count($objs) + 1;
        $out .= "xref
0 $count
";
        $out .= "0000000000 65535 f 
";
        for ($id = 1; $id <= count($objs); $id++) {
            $out .= sprintf("%010d 00000 n 
", $offsets[$id]);
        }
        $out .= "trailer
<< /Size $count /Root 1 0 R >>
startxref
$xrefPos
%%EOF";
        return $out;
    }
}

if (!function_exists('ssmunie_export_vcf')) {
    /** $contacts = [ ['nom'=>, 'prenom'=>, 'tel'=>], ... ] → vCard 3.0. */
    function ssmunie_export_vcf(array $contacts): string
    {
        $out = '';
        foreach ($contacts as $c) {
            $nom    = trim((string) ($c['nom'] ?? ''));
            $prenom = trim((string) ($c['prenom'] ?? ''));
            $tel    = trim((string) ($c['tel'] ?? ''));
            if ($tel === '') continue;
            $out .= "BEGIN:VCARD
VERSION:3.0
";
            $out .= 'N:' . $nom . ';' . $prenom . ";;;
";
            $out .= 'FN:' . trim($prenom . ' ' . $nom) . "
";
            $out .= 'TEL;TYPE=CELL:' . preg_replace('/[^0-9+]/', '', $tel) . "
";
            $out .= "END:VCARD
";
        }
        return $out;
    }
}

if (!function_exists('ssmunie_export_send')) {
    /** Envoie un export avec les bons en-têtes. */
    function ssmunie_export_send(string $data, string $filename, string $mime): void
    {
        header('Content-Type: ' . $mime);
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($data));
        header('Pragma: no-cache');
        header('Expires: 0');
        echo $data;
        exit;
    }
}
