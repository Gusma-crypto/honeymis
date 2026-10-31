<?php

namespace App\Domains\Catalog\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use SimpleXMLElement;
use ZipArchive;

class ProductSpreadsheet
{
    public const array Columns = ['sku', 'name', 'category_slug', 'variant_sku', 'label', 'price', 'member_price', 'cost', 'discount_amount', 'weight_g', 'status', 'commission_eligible', 'description'];

    /** @return list<array<string, string>> */
    public function rows(UploadedFile $file): array
    {
        $rows = strtolower($file->getClientOriginalExtension()) === 'xlsx' ? $this->xlsx($file->getPathname()) : $this->csv($file->getPathname());
        $header = array_shift($rows);
        if ($header !== self::Columns) {
            throw ValidationException::withMessages(['file' => 'Kolom tidak sesuai template. Unduh template, lalu gunakan urutan kolom yang tersedia.']);
        }
        if ($rows === [] || count($rows) > 100) {
            throw ValidationException::withMessages(['file' => 'File harus berisi 1 sampai 100 baris produk/varian.']);
        }
        $result = [];
        foreach ($rows as $index => $row) {
            if (count($row) > count($header)) {
                throw ValidationException::withMessages(['file' => 'Jumlah kolom berlebih pada baris '.($index + 2).'.']);
            }
            $result[] = array_combine($header, array_pad($row, count($header), ''));
        }

        return $result;
    }

    /** @return list<list<string>> */
    private function csv(string $path): array
    {
        $stream = fopen($path, 'r');
        if ($stream === false) {
            throw ValidationException::withMessages(['file' => 'File tidak dapat dibaca.']);
        }
        $rows = [];
        try {
            while (($row = fgetcsv($stream, escape: '')) !== false) {
                if ($row === [null]) {
                    continue;
                }
                $rows[] = array_map(static fn ($value): string => trim((string) $value), $row);
                if (count($rows) > 101) {
                    break;
                }
            }
        } finally {
            fclose($stream);
        }
        if (isset($rows[0][0])) {
            $rows[0][0] = ltrim($rows[0][0], "\xEF\xBB\xBF");
        }

        return $rows;
    }

    /** @return list<list<string>> */
    private function xlsx(string $path): array
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw ValidationException::withMessages(['file' => 'File Excel tidak valid.']);
        }
        try {
            $size = 0;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $entry = $zip->statIndex($i);
                if ($entry === false) {
                    throw ValidationException::withMessages(['file' => 'Arsip Excel rusak.']);
                }
                $size += $entry['size'];
                if ($size > 8_000_000 || $zip->numFiles > 100) {
                    throw ValidationException::withMessages(['file' => 'Isi file Excel terlalu besar. Maksimal 100 baris.']);
                }
            }
            $strings = [];
            $shared = $zip->getFromName('xl/sharedStrings.xml');
            if ($shared !== false) {
                foreach ($this->xml($shared)->si as $item) {
                    $strings[] = implode('', array_map(static fn (SimpleXMLElement $text): string => (string) $text, $item->xpath('.//*[local-name()="t"]')));
                }
            }
            $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
            if ($sheet === false) {
                throw ValidationException::withMessages(['file' => 'Worksheet pertama tidak ditemukan. Gunakan template pada lembar pertama.']);
            }
            $rows = [];
            foreach ($this->xml($sheet)->sheetData->row as $row) {
                $values = [];
                foreach ($row->c as $cell) {
                    if (isset($cell->f)) {
                        throw ValidationException::withMessages(['file' => 'Rumus Excel tidak didukung. Tempel sebagai nilai sebelum mengimpor.']);
                    }
                    preg_match('/^([A-Z]+)[0-9]+$/', (string) $cell['r'], $match);
                    $column = 0;
                    foreach (str_split($match[1] ?? 'A') as $letter) {
                        $column = $column * 26 + ord($letter) - 64;
                    }
                    if ($column < 1 || $column > count(self::Columns)) {
                        throw ValidationException::withMessages(['file' => 'Kolom Excel melebihi template.']);
                    }
                    $value = match ((string) $cell['t']) {
                        's' => $strings[(int) $cell->v] ?? '',
                        'inlineStr' => implode('', array_map(static fn (SimpleXMLElement $text): string => (string) $text, $cell->xpath('.//*[local-name()="t"]'))),
                        default => (string) $cell->v,
                    };
                    $values[$column - 1] = trim($value);
                }
                if ($values !== []) {
                    $line = array_fill(0, max(array_keys($values)) + 1, '');
                    $rows[] = array_values(array_replace($line, $values));
                }
                if (count($rows) > 101) {
                    break;
                }
            }

            return $rows;
        } finally {
            $zip->close();
        }
    }

    private function xml(string $contents): SimpleXMLElement
    {
        if (preg_match('/<!DOCTYPE|<!ENTITY/i', $contents)) {
            throw ValidationException::withMessages(['file' => 'XML Excel tidak diizinkan.']);
        }
        $previous = libxml_use_internal_errors(true);
        try {
            $xml = simplexml_load_string($contents, options: LIBXML_NONET);
            if ($xml === false) {
                throw ValidationException::withMessages(['file' => 'Format XML Excel rusak.']);
            }

            return $xml;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }
}
