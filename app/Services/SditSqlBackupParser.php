<?php

namespace App\\Services;

use DateTime;

class SditSqlBackupParser
{
    private const EMPLOYEE_COLUMNS = [
        'pegawai_id','pegawai_pin','pegawai_nip','pegawai_nama','pegawai_alias',
        'pegawai_pwd','pegawai_rfid','pegawai_privilege','pegawai_telp','pegawai_status',
        'tempat_lahir','tgl_lahir','pembagian1_id','pembagian2_id','pembagian3_id',
        'tgl_mulai_kerja','tgl_resign','gender','tgl_masuk_pertama','photo_path',
        'tmp_img','nama_bank','nama_rek','no_rek','password_fio_desktop',
        'status_login_fio_desktop','new_pegawai_id'
    ];
    private const LOG_COLUMNS = ['sn','scan_date','pin','verifymode','inoutmode','reserved','work_code','att_id'];

    public function parseEmployees(string $sql): array
    {
        $records = [];
        $invalid = 0;
        $rows = $this->extractInserts($sql, 'pegawai');
        foreach ($rows as $row) {
            if (count($row) !== count(self::EMPLOYEE_COLUMNS)) { $invalid++; continue; }
            $r = array_combine(self::EMPLOYEE_COLUMNS, $row);
            $pin = trim((string)($r['pegawai_pin'] ?? ''));
            $name = trim((string)($r['pegawai_nama'] ?? ''));
            if ($pin === '' || $name === '') { $invalid++; continue; }
            $records[] = [
                'source_employee_id' => (string)$r['pegawai_id'],
                'fingerprint_pin' => $pin,
                'employee_code' => trim((string)($r['pegawai_nip'] ?? '')) ?: $pin,
                'full_name' => $name,
                'phone' => $r['pegawai_telp'] ?? null,
                'is_active' => (int)($r['pegawai_status'] ?? 0) === 1 ? 1 : 0,
                'source_division_1' => $r['pembagian1_id'] ?? null,
                'source_division_2' => $r['pembagian2_id'] ?? null,
                'source_division_3' => $r['pembagian3_id'] ?? null,
                'employment_start' => $this->validDate($r['tgl_mulai_kerja'] ?? null),
                'employment_end' => $this->validDate($r['tgl_resign'] ?? null),
            ];
        }
        return ['records'=>$records,'invalid'=>$invalid,'total'=>count($rows)];
    }

    public function parseLogs(string $sql): array
    {
        $valid = [];
        $invalid = [];
        $rows = $this->extractInserts($sql, 'att_log');
        foreach ($rows as $i => $row) {
            if (count($row) !== count(self::LOG_COLUMNS)) {
                $invalid[] = ['pin'=>'-','original_scanned_at'=>'Format kolom tidak sesuai','source_row'=>$i+1];
                continue;
            }
            $r = array_combine(self::LOG_COLUMNS, $row);
            $pin = trim((string)($r['pin'] ?? ''));
            $date = trim((string)($r['scan_date'] ?? ''));
            $parsed = DateTime::createFromFormat('!Y-m-d H:i:s', $date);
            if ($pin === '' || !$parsed || $parsed->format('Y-m-d H:i:s') !== $date) {
                $invalid[] = ['pin'=>$pin ?: '-', 'original_scanned_at'=>$date ?: 'Tanggal kosong', 'source_row'=>$i+1];
                continue;
            }
            $valid[] = [
                'machine_serial'=>trim((string)($r['sn'] ?? '')) ?: 'FS2010195000514',
                'pin'=>$pin,
                'scanned_at'=>$date,
                'verify_mode'=>(int)($r['verifymode'] ?? 1),
                'inout_mode'=>(int)($r['inoutmode'] ?? 0),
                'source_row'=>$i+1,
            ];
        }
        return ['records'=>$valid,'invalid_records'=>$invalid,'total'=>count($rows)];
    }

    private function validDate($value): ?string
    {
        $value = trim((string)$value);
        if ($value === '' || $value === '0000-00-00') return null;
        $date = DateTime::createFromFormat('!Y-m-d', $value);
        return $date && $date->format('Y-m-d') === $value ? $value : null;
    }

    private function extractInserts(string $sql, string $table): array
    {
        $pattern = '/INSERT\\s+INTO\\s+`?' . preg_quote($table, '/') . '`?\\s+VALUES\\s*/i';
        preg_match_all($pattern, $sql, $matches, PREG_OFFSET_CAPTURE);
        $all = [];
        foreach ($matches[0] as [$match, $offset]) {
            $start = $offset + strlen($match);
            $quote = null; $escaped = false; $depth = 0; $tuple = '';
            for ($i=$start, $length=strlen($sql); $i<$length; $i++) {
                $ch=$sql[$i];
                if ($quote !== null) {
                    $tuple.=$ch;
                    if ($escaped) $escaped=false;
                    elseif ($ch==='\\\\') $escaped=true;
                    elseif ($ch===$quote) {
                        if (($sql[$i+1] ?? '')===$quote) $tuple.=$sql[++$i];
                        else $quote=null;
                    }
                    continue;
                }
                if ($ch==="'" || $ch==='"') { $quote=$ch; $tuple.=$ch; }
                elseif ($ch==='(') { $depth++; if ($depth>1) $tuple.=$ch; }
                elseif ($ch===')') {
                    $depth--;
                    if ($depth===0) { $all[]=$this->parseTuple($tuple); $tuple=''; }
                    elseif ($depth>0) $tuple.=$ch;
                } elseif ($depth>0) $tuple.=$ch;
                elseif ($ch===';') break;
            }
        }
        return $all;
    }

    private function parseTuple(string $tuple): array
    {
        $values=[]; $value=''; $quote=null; $escaped=false;
        for ($i=0,$length=strlen($tuple); $i<$length; $i++) {
            $ch=$tuple[$i];
            if ($quote!==null) {
                if ($escaped) { $value.=$ch; $escaped=false; }
                elseif ($ch==='\\\\') $escaped=true;
                elseif ($ch===$quote) {
                    if (($tuple[$i+1] ?? '')===$quote) { $value.=$quote; $i++; }
                    else $quote=null;
                } else $value.=$ch;
            } elseif ($ch==="'" || $ch==='"') $quote=$ch;
            elseif ($ch===',') { $values[]=trim($value)==='NULL'?null:trim($value); $value=''; }
            else $value.=$ch;
        }
        $values[]=trim($value)==='NULL'?null:trim($value);
        return $values;
    }
}
