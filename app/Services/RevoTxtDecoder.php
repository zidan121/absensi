<?php

namespace App\Services;

use RuntimeException;

class RevoTxtDecoder
{
    /**
     * Decode Revo TXT bytes. Each byte is XOR-ed with 0xD7.
     * This method returns text only; parsing/validation is deliberately separate.
     */
    public function decode(string $raw): string
    {
        if ($raw === '') {
            throw new RuntimeException('File log kosong.');
        }

        $decoded = $raw ^ str_repeat("\xD7", strlen($raw));

        if (! str_contains($decoded, "\t") && ! str_contains($decoded, ';')) {
            throw new RuntimeException('Struktur hasil dekode tidak dikenali (delimiter tidak ditemukan).');
        }

        return preg_replace('/^\xEF\xBB\xBF/', '', $decoded) ?? $decoded;
    }

    /**
     * Parse tab-delimited or semicolon-delimited text with a header row.
     * Expected semantic columns: No, Mchn, EnNo, Name, Mode, IOMd, DateTime.
     */
    public function parse(string $decoded): array
    {
        $lines = preg_split('/\r\n|\n|\r/', trim($decoded));
        if (! $lines || count($lines) < 2) {
            throw new RuntimeException('File tidak memiliki baris data.');
        }

        $delimiter = str_contains($lines[0], "\t") ? "\t" : ';';
        $header = array_map(static fn ($v) => strtolower(trim($v)), str_getcsv(array_shift($lines), $delimiter));
        $required = ['no', 'mchn', 'enno', 'name', 'mode', 'iomd', 'datetime'];
        $index = [];
        foreach ($required as $column) {
            $position = array_search($column, $header, true);
            if ($position === false) {
                throw new RuntimeException('Kolom wajib tidak ditemukan: ' . $column);
            }
            $index[$column] = $position;
        }

        $records = [];
        $invalid = [];
        foreach ($lines as $lineNumber => $line) {
            if (trim($line) === '') {
                continue;
            }
            $fields = str_getcsv($line, $delimiter);
            $pin = trim((string) ($fields[$index['enno']] ?? ''));
            $dateTime = trim((string) ($fields[$index['datetime']] ?? ''));
            $time = strtotime($dateTime);

            if ($pin === '' || $time === false || count($fields) <= max($index)) {
                $invalid[] = ['line' => $lineNumber + 2, 'reason' => 'PIN/tanggal tidak valid atau kolom kurang', 'raw' => $line];
                continue;
            }

            $records[] = [
                'source_row' => $lineNumber + 2,
                'machine_no' => trim((string) $fields[$index['mchn']]),
                'pin' => $pin,
                'employee_name_source' => trim((string) $fields[$index['name']]),
                'verify_mode' => trim((string) $fields[$index['mode']]),
                'inout_mode' => trim((string) $fields[$index['iomd']]),
                'scanned_at' => date('Y-m-d H:i:s', $time),
            ];
        }

        return ['records' => $records, 'invalid' => $invalid];
    }
}