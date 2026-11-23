<?php

namespace App\Services;

use DateTime;
use RuntimeException;

class RevoTxtDecoder
{
    public function decode(string $raw): string
    {
        if ($raw === '') {
            throw new RuntimeException('File log kosong.');
        }

        $decoded = $raw ^ str_repeat("\xD7", strlen($raw));
        if (! str_contains($decoded, "\t") || ! preg_match('/(?:^|\t)DateTime(?:\t|$)/i', $decoded)) {
            throw new RuntimeException('Header hasil dekode tidak dikenali.');
        }

        return preg_replace('/^\xEF\xBB\xBF/', '', $decoded) ?? $decoded;
    }

    public function parse(string $decoded): array
    {
        $lines = preg_split('/\r\n|\n|\r/', trim($decoded));
        if (! $lines || count($lines) < 2) {
            throw new RuntimeException('File tidak memiliki baris data.');
        }

        $delimiter = str_contains($lines[0], "\t") ? "\t" : ';';
        $headerRaw = str_getcsv(array_shift($lines), $delimiter);
        // Export header contains blank spacer columns; data rows omit those spacers.
        $header = array_values(array_filter(
            array_map(static fn ($v) => strtolower(trim((string) $v)), $headerRaw),
            static fn ($v) => $v !== ''
        ));

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
            $parsedDate = false;
            foreach (['!Y/m/d H:i:s', '!Y-m-d H:i:s', '!d/m/Y H:i:s'] as $format) {
                $candidate = DateTime::createFromFormat($format, $dateTime);
                if ($candidate && $candidate->format('Y') !== '1970') {
                    $parsedDate = $candidate;
                    break;
                }
            }

            if ($pin === '' || ! $parsedDate || count($fields) <= max($index)) {
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
                'scanned_at' => $parsedDate->format('Y-m-d H:i:s'),
            ];
        }

        return ['records' => $records, 'invalid' => $invalid];
    }
}