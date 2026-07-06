<?php

namespace App\Services\Doc;

use RuntimeException;
use ZipArchive;

/**
 * Ekstraksi teks dari berbagai format dokumen rujukan.
 * PDF: smalot/pdfparser. DOCX: baca word/document.xml. txt/md/csv: plain.
 */
class DocumentTextExtractor
{
    /** @return array{text:string, pages:int} */
    public function extract(string $path, ?string $ext = null): array
    {
        $ext = strtolower($ext ?? pathinfo($path, PATHINFO_EXTENSION));

        return match ($ext) {
            'pdf'                   => $this->fromPdf($path),
            'docx'                  => ['text' => $this->fromDocx($path), 'pages' => 1],
            'txt', 'md', 'markdown', 'csv' => ['text' => (string) file_get_contents($path), 'pages' => 1],
            default                 => throw new RuntimeException("Format '{$ext}' tidak didukung. Gunakan PDF, DOCX, TXT, MD, atau CSV."),
        };
    }

    /** @return array{text:string, pages:int} */
    private function fromPdf(string $path): array
    {
        $parser = new \Smalot\PdfParser\Parser();
        $pdf = $parser->parseFile($path);
        $pages = $pdf->getPages();

        $parts = [];
        foreach ($pages as $page) {
            $parts[] = $page->getText();
        }
        $text = trim(implode("\n\n", $parts));

        if ($text === '') {
            $text = trim($pdf->getText());
        }

        return ['text' => $text, 'pages' => max(1, count($pages))];
    }

    private function fromDocx(string $path): string
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException('Gagal membuka DOCX.');
        }
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        if ($xml === false) {
            throw new RuntimeException('Struktur DOCX tidak valid.');
        }

        // Ubah penanda paragraf/baris jadi newline sebelum strip tag.
        $xml = preg_replace('/<\/w:p>/', "\n", $xml);
        $xml = preg_replace('/<w:br\s*\/>/', "\n", (string) $xml);
        $text = strip_tags((string) $xml);

        return trim(html_entity_decode($text, ENT_QUOTES | ENT_XML1, 'UTF-8'));
    }
}
