<?php
namespace App\Controllers;
use RuntimeException;
use Throwable;
class SditBackup extends BaseController
{
    private function sditTables(): array
    {
        $tables = array_values(array_filter(db_connect()->listTables(), static fn ($table) => str_starts_with($table, 'sdit_')));
        sort($tables);
        return $tables;
    }
    public function index()
    {
        $db = db_connect();
        $tables = [];
        foreach ($this->sditTables() as $table) $tables[] = ['name' => $table, 'count' => (int) $db->table($table)->countAllResults()];
        return view('sdit/backup', ['title' => 'Backup & Restore SDIT', 'tables' => $tables]);
    }
    public function download()
    {
        $db = db_connect();
        $tables = $this->sditTables();
        $lines = ['-- NEURO ABSENSI - BACKUP DATABASE SDIT', '-- Dibuat: ' . date('Y-m-d H:i:s'), '-- Struktur dan seluruh data tabel sdit_*', '-- Restore non-destruktif: CREATE TABLE IF NOT EXISTS dan INSERT IGNORE.', 'SET FOREIGN_KEY_CHECKS=0;', ''];
        $tick = chr(96);
        foreach ($tables as $table) {
            $result = $db->query('SHOW CREATE TABLE ' . $db->protectIdentifiers($table))->getRowArray();
            $createSql = $result['Create Table'] ?? array_values($result)[1] ?? null;
            if (!$createSql) throw new RuntimeException('Struktur tabel ' . $table . ' tidak dapat dibaca.');
            $createSql = preg_replace('/^CREATE TABLE\s+/i', 'CREATE TABLE IF NOT EXISTS ', $createSql, 1);
            $lines[] = '-- Struktur: ' . $table;
            $lines[] = $createSql . ';';
            $lines[] = '';
        }
        foreach ($tables as $table) {
            $rows = $db->table($table)->get()->getResultArray();
            if (!$rows) continue;
            $columns = array_keys($rows[0]);
            $quotedColumns = implode(', ', array_map(static fn ($column) => $tick . str_replace($tick, $tick . $tick, $column) . $tick, $columns));
            foreach (array_chunk($rows, 100) as $chunk) {
                $valueRows = [];
                foreach ($chunk as $row) {
                    $values = [];
                    foreach ($columns as $column) $values[] = $row[$column] === null ? 'NULL' : $db->escape($row[$column]);
                    $valueRows[] = '(' . implode(', ', $values) . ')';
                }
                $lines[] = 'INSERT IGNORE INTO ' . $tick . $table . $tick . ' (' . $quotedColumns . ') VALUES';
                $lines[] = implode(",\n", $valueRows) . ';';
            }
            $lines[] = '';
        }
        $lines[] = 'SET FOREIGN_KEY_CHECKS=1;';
        return $this->response->setHeader('Content-Type', 'application/sql; charset=utf-8')
            ->setHeader('Content-Disposition', 'attachment; filename="Backup-SDIT-Lengkap-' . date('Ymd-His') . '.sql"')
            ->setBody(implode("\n", $lines) . "\n");
    }
    public function downloadJson()
    {
        $db = db_connect();
        $payload = ['format' => 'neuro-absensi-sdit-backup', 'version' => 1, 'created_at' => date('c'), 'tables' => []];
        foreach ($this->sditTables() as $table) $payload['tables'][$table] = $db->table($table)->get()->getResultArray();
        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($json === false) return redirect()->to('/sdit/backup')->with('error', 'Data SDIT gagal disiapkan untuk backup.');
        return $this->response->setHeader('Content-Type', 'application/json; charset=utf-8')
            ->setHeader('Content-Disposition', 'attachment; filename="Backup-SDIT-' . date('Ymd-His') . '.json"')->setBody($json);
    }
    private function splitSqlStatements(string $sql): array
    {
        $statements = []; $buffer = ''; $quote = null; $escaped = false; $length = strlen($sql);
        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];
            if ($quote !== null) {
                $buffer .= $char;
                if ($escaped) $escaped = false;
                elseif ($char === chr(92)) $escaped = true;
                elseif ($char === $quote) {
                    if ($quote === chr(96) && ($sql[$i + 1] ?? '') === chr(96)) $buffer .= $sql[++$i];
                    else $quote = null;
                }
                continue;
            }
            if ($char === "'" || $char === '"' || $char === chr(96)) { $quote = $char; $buffer .= $char; }
            elseif ($char === ';') { if (trim($buffer) !== '') $statements[] = trim($buffer); $buffer = ''; }
            else $buffer .= $char;
        }
        if (trim($buffer) !== '') $statements[] = trim($buffer);
        if ($quote !== null) throw new RuntimeException('File SQL memiliki tanda kutip yang tidak lengkap.');
        return $statements;
    }
    public function restoreSql()
    {
        $file = $this->request->getFile('sql_file');
        if (!$file || !$file->isValid() || strtolower($file->getExtension()) !== 'sql') return redirect()->to('/sdit/backup')->with('error', 'Pilih file backup SDIT berekstensi .sql.');
        if ($file->getSizeByUnit('mb') > 500) return redirect()->to('/sdit/backup')->with('error', 'Ukuran file maksimal 500 MB.');
        if ($this->request->getPost('confirm_sql_restore') !== '1') return redirect()->to('/sdit/backup')->with('error', 'Centang konfirmasi sebelum restore SQL.');
        $sql = file_get_contents($file->getTempName());
        if ($sql === false || trim($sql) === '') return redirect()->to('/sdit/backup')->with('error', 'File SQL kosong atau tidak dapat dibaca.');
        $db = db_connect(); $db->transBegin();
        try {
            $db->query('SET FOREIGN_KEY_CHECKS=0'); $created = 0; $inserted = 0;
            foreach ($this->splitSqlStatements($sql) as $statement) {
                $statement = trim(preg_replace('/^(?:\s*--[^\r\n]*(?:\r?\n|$))+/', '', $statement));
                if ($statement === '') continue;
                if (preg_match('/^SET\s+FOREIGN_KEY_CHECKS\s*=\s*[01]$/i', $statement)) continue;
                if (preg_match('/^CREATE TABLE IF NOT EXISTS\s+\x60(sdit_[a-zA-Z0-9_]+)\x60/i', $statement)) {
                    $db->query($statement); $created++; continue;
                }
                if (preg_match('/^INSERT IGNORE INTO\s+\x60(sdit_[a-zA-Z0-9_]+)\x60\s/i', $statement)) {
                    $db->query($statement); $inserted += max(0, (int) $db->affectedRows()); continue;
                }
                throw new RuntimeException('File berisi perintah SQL yang tidak diizinkan. Hanya CREATE TABLE IF NOT EXISTS dan INSERT IGNORE untuk tabel sdit_* yang diterima.');
            }
            $db->query('SET FOREIGN_KEY_CHECKS=1');
            if ($db->transStatus() === false) throw new RuntimeException('Database menolak proses restore.');
            $db->transCommit();
            return redirect()->to('/sdit/backup')->with('success', 'Restore SQL selesai. Perintah struktur diproses: ' . $created . '; baris data baru ditambahkan: ' . $inserted . '. Data lama tidak ditimpa.');
        } catch (Throwable $e) {
            $db->query('SET FOREIGN_KEY_CHECKS=1'); $db->transRollback();
            log_message('error', 'SDIT SQL restore failed: {message}', ['message' => $e->getMessage()]);
            return redirect()->to('/sdit/backup')->with('error', 'Restore SQL dibatalkan: ' . $e->getMessage());
        }
    }
    public function restore()
    {
        $file = $this->request->getFile('backup_file');
        if (!$file || !$file->isValid() || strtolower($file->getExtension()) !== 'json') return redirect()->to('/sdit/backup')->with('error', 'Pilih file backup SDIT JSON yang valid.');
        if ($file->getSizeByUnit('mb') > 200) return redirect()->to('/sdit/backup')->with('error', 'Ukuran file maksimal 200 MB.');
        if ($this->request->getPost('confirm_merge') !== '1') return redirect()->to('/sdit/backup')->with('error', 'Centang konfirmasi sebelum restore.');
        $raw = file_get_contents($file->getTempName()); $backup = $raw === false ? null : json_decode($raw, true);
        if (!is_array($backup) || ($backup['format'] ?? '') !== 'neuro-absensi-sdit-backup' || (int) ($backup['version'] ?? 0) !== 1 || !is_array($backup['tables'] ?? null)) return redirect()->to('/sdit/backup')->with('error', 'Format backup tidak dikenali atau file rusak.');
        $db = db_connect(); $allowed = $this->sditTables(); $inserted = 0; $db->transBegin();
        try {
            $db->query('SET FOREIGN_KEY_CHECKS=0');
            foreach ($backup['tables'] as $table => $rows) {
                if (!is_string($table) || !str_starts_with($table, 'sdit_') || !in_array($table, $allowed, true) || !is_array($rows)) throw new RuntimeException('Backup memuat tabel yang bukan bagian dari SDIT.');
                $columns = $db->getFieldNames($table);
                foreach ($rows as $row) {
                    if (!is_array($row) || array_diff(array_keys($row), $columns)) throw new RuntimeException('Struktur data tabel ' . $table . ' tidak sesuai. Gunakan Restore SQL Lengkap jika struktur belum tersedia.');
                    $builder = $db->table($table); $builder->ignore(true); $builder->insert($row);
                    $inserted += max(0, (int) $db->affectedRows());
                }
            }
            $db->query('SET FOREIGN_KEY_CHECKS=1');
            if ($db->transStatus() === false) throw new RuntimeException('Database menolak proses restore.');
            $db->transCommit();
            return redirect()->to('/sdit/backup')->with('success', $inserted . ' baris data baru ditambahkan. Data lama tidak ditimpa.');
        } catch (Throwable $e) {
            $db->query('SET FOREIGN_KEY_CHECKS=1'); $db->transRollback();
            log_message('error', 'SDIT JSON restore failed: {message}', ['message' => $e->getMessage()]);
            return redirect()->to('/sdit/backup')->with('error', 'Restore dibatalkan: ' . $e->getMessage());
        }
    }
}
