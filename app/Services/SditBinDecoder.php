<?php

namespace App\Services;

use DateTimeImmutable;
use RuntimeException;

/**
 * Decoder record BIN Fingerspot Neo W-201BNC (SDIT).
 * Setiap record 16 byte: uint64 PIN + uint64 timestamp bitfield, little-endian.
 */
class SditBinDecoder
{
    public const RECORD_LENGTH = 16;
    public const INVALID_YEAR_BEFORE = 2020;

    public function parse(string $raw): array
    {
        if ($raw === '') {
            throw new RuntimeException('File BIN kosong.');
        }
        if (strlen($raw) % self::RECORD_LENGTH !== 0) {
            throw new RuntimeException('Ukuran file bukan kelipatan 16 byte. File tidak diproses agar record tidak terpotong.');
        }

        $records = [];
        $invalid = [];
        $pins = [];
        $duplicates = [];
        $seen = [];
        $total = intdiv(strlen($raw), self::RECORD_LENGTH);

        for ($offset = 0, $number = 1; $offset < strlen($raw); $offset += self::RECORD_LENGTH, $number++) {
            $parts = unpack('Ppin/Pvalue', substr($raw, $offset, self::RECORD_LENGTH));
            if ($parts === false) {
                throw new RuntimeException('Gagal membaca record BIN ke-' . $number . '.');
            }

            $pin = (string) $parts['pin'];
            $value = $parts['value'];
            $year = 2000 + ($value & 0x7F);
            $month = ($value >> 7) & 0x0F;
            $day = ($value >> 11) & 0x1F;
            $unknownA = ($value >> 16) & 0xFFFF;
            $unknownB = ($value >> 32) & 0x7FFF;
            $hour = ($value >> 47) & 0x1F;
            $minute = ($value >> 52) & 0x3F;
            $second = ($value >> 58) & 0x3F;

            $dateValid = checkdate($month, $day, $year)
                && $hour <= 23 && $minute <= 59 && $second <= 59;
            $scannedAt = null;
            if ($dateValid) {
                $scannedAt = sprintf('%04d-%02d-%02d %02d:%02d:%02d', $year, $month, $day, $hour, $minute, $second);
            }

            $timeInvalid = ! $dateValid || $year < self::INVALID_YEAR_BEFORE;
            $key = $pin . '|' . ($scannedAt ?? 'INVALID-' . $number);
            $isDuplicate = isset($seen[$key]);
            $seen[$key] = true;
            $pins[$pin] = true;

            $record = [
                'record_no' => $number,
                'pin' => $pin,
                'scanned_at' => $scannedAt,
                'original_scanned_at' => $scannedAt,
                'year' => $year,
                'month' => $month,
                'day' => $day,
                'hour' => $hour,
                'minute' => $minute,
                'second' => $second,
                'unknown_a' => $unknownA,
                'unknown_b' => $unknownB,
                'time_invalid' => $timeInvalid,
                'duplicate' => $isDuplicate,
            ];

            if ($isDuplicate) {
                $duplicates[] = $record;
            }
            if ($timeInvalid) {
                $invalid[] = $record;
            } else {
                $records[] = $record;
            }
        }

        return [
            'total' => $total,
            'valid' => count($records),
            'invalid' => count($invalid),
            'unique_pins' => count($pins),
            'duplicates' => count($duplicates),
            'records' => $records,
            'invalid_records' => $invalid,
            'duplicate_records' => $duplicates,
        ];
    }
}
