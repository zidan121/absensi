<?php

namespace App\Services;

/**
 * Read-only parser for the SQL INSERT format produced by Fingerspot Personnel.
 * It never executes SQL and only extracts the two approved tables.
 */
class FingerspotSqlBackupParser
{
    private const EMPLOYEE_COLUMNS = [
        'pegawai_id', 'pegawai_pin', 'pegawai_nip', 'pegawai_nama', 'pegawai_alias',
        'pegawai_pwd', 'pegawai_rfid', 'pegawai_privilege', 'pegawai_telp',
        'pegawai_status', 'tempat_lahir', 'tgl_lahir', 'pembagian1_id',
        'pembagian2_id', 'pembagian3_id', 'tgl_mulai_kerja', 'tgl_resign',
        'gender', 'tgl_masuk_pertama', 'photo_path', 'tmp_img', 'nama_bank',
        'nama_rek', 'no_rek', 'new_pegawai_id',
    ];

    private const LOG_COLUMNS = [
        'sn', 'scan_date', 'pin', 'verifymode', 'inoutmode',
        'reserved', 'work_code', 'att_id',
    ];

    public function parse(string $sql, string $kind): array
    {
        $table = $kind === 'employees' ? 'pegawai' : ($kind === 'logs' ? 'att_log' : '');
        if ($table === '') {
            throw new \InvalidArgumentException('Jenis impor tidak dikenal.');
        }

        $columns = $kind === 'employees' ? self::EMPLOYEE_COLUMNS : self::LOG_COLUMNS;
        $rows = $this->extractInserts($sql, $table);
        $records = [];
        $invalid = 0;

        foreach ($rows as $row) {
            if (count($row) !== count($columns)) {
                $invalid++;
                continue;
            }
            $record = array_combine($columns, $row);
            if ($kind === 'employees') {
                $pin = trim((string) ($record['pegawai_pin'] ?? ''));
                $name = trim((string) ($record['pegawai_nama'] ?? ''));
                if ($pin === '' || $name === '') {
                    $invalid++;
                    continue;
                }
                $records[] = [
                    'source_employee_id' => (string) $record['pegawai_id'],
                    'fingerprint_pin' => $pin,
                    'employee_code' => $pin,
                    'full_name' => $name,
                    'is_active' => (int) $record['pegawai_status'] === 1 ? 1 : 0,
                    'source_division_1' => (string) $record['pembagian1_id'],
                    'source_division_2' => (string) $record['pembagian2_id'],
                    'source_division_3' => (string) $record['pembagian3_id'],
                    'employment_start' => $this->validDate($record['tgl_mulai_kerja']),
                    'employment_end' => $this->validDate($record['tgl_resign']),
                ];
            } else {
                $pin = trim((string) ($record['pin'] ?? ''));
                $date = trim((string) ($record['scan_date'] ?? ''));
                $parsedDate = \DateTime::createFromFormat('!Y-m-d H:i:s', $date);
                if ($pin === '' || ! $parsedDate || $parsedDate->format('Y-m-d H:i:s') !== $date) {
                    $invalid++;
                    continue;
                }
                $records[] = [
                    'machine_no' => (string) $record['sn'],
                    'pin' => $pin,
                    'verify_mode' => (string) $record['verifymode'],
                    'inout_mode' => (string) $record['inoutmode'],
                    'scanned_at' => $date,
                ];
            }
        }

        return ['records' => $records, 'invalid' => $invalid, 'total' => count($rows)];
    }

    private function validDate($value): ?string
    {
        $value = trim((string) $value);
        if ($value === '' || $value === '0000-00-00') {
            return null;
        }
        $date = \DateTime::createFromFormat('!Y-m-d', $value);
        return $date && $date->format('Y-m-d') === $value ? $value : null;
    }

    private function extractInserts(string $sql, string $table): array
    {
        $pattern = '/INSERT\s+INTO\s+`?' . preg_quote($table, '/') . '`?\s+VALUES\s*/i';
        if (! preg_match_all($pattern, $sql, $matches, PREG_OFFSET_CAPTURE)) {
            return [];
        }

        $all = [];
        foreach ($matches[0] as [$match, $offset]) {
            $start = $offset + strlen($match);
            $length = strlen($sql);
            $quote = null;
            $escaped = false;
            $depth = 0;
            $tuple = '';
            for ($i = $start; $i < $length; $i++) {
                $char = $sql[$i];
                if ($quote !== null) {
                    $tuple .= $char;
                    if ($escaped) {
                        $escaped = false;
                    } elseif ($char === '\\') {
                        $escaped = true;
                    } elseif ($char === $quote) {
                        if (($sql[$i + 1] ?? '') === $quote) {
                            $tuple .= $sql[++$i];
                        } else {
                            $quote = null;
                        }
                    }
                    continue;
                }
                if ($char === "'" || $char === '"') {
                    $quote = $char;
                    $tuple .= $char;
                } elseif ($char === '(') {
                    $depth++;
                    if ($depth > 1) $tuple .= $char;
                } elseif ($char === ')') {
                    $depth--;
                    if ($depth === 0) {
                        $all[] = $this->parseTuple($tuple);
                        $tuple = '';
                    } elseif ($depth > 0) {
                        $tuple .= $char;
                    }
                } elseif ($depth > 0) {
                    $tuple .= $char;
                } elseif ($char === ';') {
                    break;
                }
            }
        }
        return $all;
    }

    private function parseTuple(string $tuple): array
    {
        $values = [];
        $value = '';
        $quote = null;
        $escaped = false;
        $length = strlen($tuple);
        for ($i = 0; $i < $length; $i++) {
            $char = $tuple[$i];
            if ($quote !== null) {
                if ($escaped) {
                    $value .= $char;
                    $escaped = false;
                } elseif ($char === '\\') {
                    $escaped = true;
                } elseif ($char === $quote) {
                    if (($tuple[$i + 1] ?? '') === $quote) {
                        $value .= $quote;
                        $i++;
                    } else {
                        $quote = null;
                    }
                } else {
                    $value .= $char;
                }
            } elseif ($char === "'" || $char === '"') {
                $quote = $char;
            } elseif ($char === ',') {
                $values[] = trim($value) === 'NULL' ? null : trim($value);
                $value = '';
            } else {
                $value .= $char;
            }
        }
        $values[] = trim($value) === 'NULL' ? null : trim($value);
        return $values;
    }
}
