<?php
/**
 * Gerador PDF minimo para ambientes sem mPDF instalado.
 *
 * Mantem os documentos exportaveis mesmo em instalacoes offline; quando mPDF
 * estiver disponivel, os controllers continuam usando o layout HTML completo.
 */

class SimplePdf {
    public static function download(string $filename, string $html): void {
        $lines = self::htmlToLines($html);
        $pages = array_chunk($lines, 52);
        if (!$pages) {
            $pages = [['Documento sem conteudo.']];
        }

        $objects = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        ];

        $pageIds = [];
        $nextId = 4;
        foreach ($pages as $pageLines) {
            $pageId = $nextId++;
            $contentId = $nextId++;
            $pageIds[] = $pageId . ' 0 R';

            $stream = self::pageStream($pageLines);
            // B-05: usar strlen() após montagem do stream para garantir byte-length correto
            //       mesmo quando iconv altera o tamanho dos caracteres
            $streamBytes = $stream; // já em Windows-1252 após pdfText()
            $objects[$pageId] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R >> >> /Contents ' . $contentId . ' 0 R >>';
            $objects[$contentId] = "<< /Length " . strlen($streamBytes) . " >>\nstream\n" . $streamBytes . "\nendstream";
        }

        $objects[2] = '<< /Type /Pages /Kids [' . implode(' ', $pageIds) . '] /Count ' . count($pageIds) . ' >>';
        ksort($objects);

        $pdf = "%PDF-1.4\n";
        $offsets = [0 => 0];
        foreach ($objects as $id => $body) {
            $offsets[$id] = strlen($pdf);
            $pdf .= $id . " 0 obj\n" . $body . "\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $maxObjectId = max(array_keys($objects));
        $pdf .= "xref\n0 " . ($maxObjectId + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";
        for ($id = 1; $id <= $maxObjectId; $id++) {
            if (isset($offsets[$id])) {
                $pdf .= sprintf("%010d 00000 n \n", $offsets[$id]);
            } else {
                $pdf .= "0000000000 65535 f \n";
            }
        }
        $pdf .= "trailer\n<< /Size " . ($maxObjectId + 1) . " /Root 1 0 R >>\nstartxref\n" . $xrefOffset . "\n%%EOF";

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . preg_replace('/[^A-Za-z0-9_.-]/', '_', $filename) . '"');
        header('Content-Length: ' . strlen($pdf));
        echo $pdf;
        exit;
    }

    private static function htmlToLines(string $html): array {
        $html = preg_replace('/<(script|style)\b[^>]*>.*?<\/\1>/is', '', $html) ?? $html;
        $html = preg_replace('/<(br|\/p|\/h[1-6]|\/tr|\/div)\b[^>]*>/i', "\n", $html) ?? $html;
        $html = preg_replace('/<\/t[dh]>/i', ' | ', $html) ?? $html;
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace("/[ \t]+/", ' ', $text) ?? $text;
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;

        $lines = [];
        foreach (preg_split("/\r\n|\r|\n/", trim($text)) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') {
                $lines[] = '';
                continue;
            }
            foreach (self::wrapLine($line) as $piece) {
                $lines[] = trim($piece);
            }
        }
        return $lines;
    }

    private static function wrapLine(string $line): array {
        if (preg_match_all('/.{1,95}/u', $line, $matches)) {
            return $matches[0];
        }
        return str_split($line, 95);
    }

    private static function pageStream(array $lines): string {
        $stream = "BT\n/F1 10 Tf\n50 800 Td\n14 TL\n";
        foreach ($lines as $line) {
            $stream .= '(' . self::pdfText($line) . ") Tj\nT*\n";
        }
        return $stream . "ET";
    }

    private static function pdfText(string $text): string {
        // B-05: fallback para ASCII puro quando iconv não consegue converter (emoji, etc.)
        //       para evitar que /Length seja calculado com base em bytes de tamanho errado
        if (function_exists('iconv')) {
            $converted = iconv('UTF-8', 'Windows-1252//IGNORE', $text);
            if ($converted === false) {
                $converted = preg_replace('/[\x80-\xFF]/u', '?', $text) ?? $text;
            }
        } else {
            $converted = preg_replace('/[\x80-\xFF]/u', '?', $text) ?? $text;
        }
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], (string) $converted);
    }
}
