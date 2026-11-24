<?php

namespace App\Controllers;

class BackupExports extends BaseController
{
    private const SOURCES = [
        'smp' => ['label' => 'Backup SMPIT', 'filename' => 'Backup-SMP-Fingerspot.sql'],
        'rakb' => ['label' => 'Backup RA&KB', 'filename' => 'Backup-RA-KB-Fingerspot.sql'],
    ];

    private function backupDirectory(): string
    {
        $dir = WRITEPATH . 'backups' . DIRECTORY_SEPARATOR . 'fingerspot';
        if (! is_dir($dir) && ! mkdir($dir, 0770, true) && ! is_dir($dir)) {
            throw new \RuntimeException('Folder penyimpanan backup tidak dapat dibuat.');
        }
        return $dir;
    }

    public function index()
    {
        $dir = $this->backupDirectory();
        $sources = [];
        foreach (self::SOURCES as $key => $source) {
            $path = $dir . DIRECTORY_SEPARATOR . $key . '.sql';
            $sources[$key] = [
                'label' => $source['label'],
                'filename' => $source['filename'],
                'exists' => is_file($path),
                'size' => is_file($path) ? filesize($path) : 0,
                'modified' => is_file($path) ? date('d-m-Y H:i', filemtime($path)) : null,
            ];
        }

        return view('backup_exports/index', [
            'title' => 'Backup & Pemulihan',
            'sources' => $sources,
        ]);
    }

    public function saveSource()
    {
        $key = (string) $this->request->getPost('source_key');
        if (! isset(self::SOURCES[$key])) {
            return redirect()->back()->with('error', 'Jenis backup sumber tidak valid.');
        }

        $file = $this->request->getFile('backup_file');
        if (! $file || ! $file->isValid() || strtolower($file->getExtension()) !== 'sql') {
            return redirect()->back()->with('error', 'Pilih file SQL yang valid.');
        }
        if ($file->getSizeByUnit('mb') > 200) {
            return redirect()->back()->with('error', 'Ukuran file maksimal 200 MB.');
        }

        try {
            $path = $this->backupDirectory() . DIRECTORY_SEPARATOR . $key . '.sql';
            if (is_file($path) && $this->request->getPost('replace_existing') !== '1') {
                return redirect()->back()->with('error', 'Arsip sudah ada. Centang konfirmasi ganti arsip jika memang ingin menggantinya.');
            }
            if (! $file->move(dirname($path), basename($path), true)) {
                throw new \RuntimeException('File backup gagal disimpan.');
            }
            return redirect()->to('/backup')->with('success', self::SOURCES[$key]['label'] . ' berhasil disimpan sebagai arsip sumber.');
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', 'Gagal menyimpan arsip: ' . $e->getMessage());
        }
    }

    public function downloadSource(string $key)
    {
        if (! isset(self::SOURCES[$key])) {
            return redirect()->to('/backup')->with('error', 'Jenis backup sumber tidak valid.');
        }
        $path = $this->backupDirectory() . DIRECTORY_SEPARATOR . $key . '.sql';
        if (! is_file($path)) {
            return redirect()->to('/backup')->with('error', 'Arsip sumber belum diunggah.');
        }
        return $this->response->download($path, null)->setFileName(self::SOURCES[$key]['filename']);
    }


    private function restoreStagingDirectory(): string
    {
        $dir = WRITEPATH . 'backups' . DIRECTORY_SEPARATOR . 'restore-staging';
        if (! is_dir($dir) && ! mkdir($dir, 0770, true) && ! is_dir($dir)) {
            throw new \RuntimeException('Folder staging pemulihan tidak dapat dibuat.');
        }
        return $dir;
    }

    public function previewRestore()
    {
        $file = $this->request->getFile('restore_file');
        if (! $file || ! $file->isValid() || strtolower($file->getExtension()) !== 'sql') {
            return redirect()->to('/backup')->with('error', 'Pilih file backup NEURO berformat SQL yang valid.');
        }
        if ($file->getSizeByUnit('mb') > 100) {
            return redirect()->to('/backup')->with('error', 'Ukuran file pemulihan maksimal 100 MB.');
        }

        try {
            $content = file_get_contents($file->getTempName());
            if ($content === false || ! str_starts_with(ltrim($content), '-- NEURO ABSENSI database backup')) {
                throw new \RuntimeException('File bukan backup NEURO ABSENSI yang dikenali aplikasi.');
            }
            if (! preg_match('/^-- Database: neuro_absensi\\s*$/m', $content)) {
                throw new \RuntimeException('Nama database sumber tidak cocok. Hanya backup database neuro_absensi yang diterima.');
            }
            if (preg_match('/\\b(DROP|CREATE|ALTER)\\s+DATABASE\\b|\\bUSE\\s+[^;]+;/i', $content)) {
                throw new \RuntimeException('File berisi perintah penggantian database yang tidak diizinkan.');
            }

            $statements = $this->splitSqlStatements($content);
            $tableCount = 0;
            foreach ($statements as $statement) {
                if (preg_match('/^CREATE\\s+TABLE\\s+/i', ltrim($statement))) {
                    $tableCount++;
                } elseif (! preg_match('/^(SET\\s+|DROP\\s+TABLE\\s+|INSERT\\s+INTO\\s+)/i', ltrim($statement))) {
                    throw new \RuntimeException('File berisi perintah SQL yang tidak didukung oleh fitur pemulihan aman.');
                }
            }
            if ($tableCount < 1) {
                throw new \RuntimeException('Tidak ditemukan struktur tabel pada file backup.');
            }

            $token = bin2hex(random_bytes(24));
            $path = $this->restoreStagingDirectory() . DIRECTORY_SEPARATOR . $token . '.sql';
            if (! copy($file->getTempName(), $path)) {
                throw new \RuntimeException('File backup gagal disiapkan untuk pemeriksaan.');
            }
            session()->set('neuro_restore_token', $token);

            return view('backup_exports/restore_preview', [
                'title' => 'Konfirmasi Pemulihan Database',
                'token' => $token,
                'filename' => $file->getClientName(),
                'size' => $file->getSize(),
                'tableCount' => $tableCount,
                'statementCount' => count($statements),
            ]);
        } catch (\Throwable $e) {
            log_message('warning', 'Restore preview rejected: {message}', ['message' => $e->getMessage()]);
            return redirect()->to('/backup')->with('error', $e->getMessage());
        }
    }

    public function restoreTest()
    {
        $token = (string) $this->request->getPost('restore_token');
        if ($token === '' || ! hash_equals((string) session()->get('neuro_restore_token'), $token)
            || ! preg_match('/^[a-f0-9]{48}$/', $token)
            || $this->request->getPost('confirm_restore') !== 'RESTORE') {
            return redirect()->to('/backup')->with('error', 'Konfirmasi pemulihan tidak valid. Silakan unggah ulang file backup.');
        }

        $path = $this->restoreStagingDirectory() . DIRECTORY_SEPARATOR . $token . '.sql';
        if (! is_file($path)) {
            return redirect()->to('/backup')->with('error', 'File staging tidak ditemukan. Silakan unggah ulang.');
        }

        try {
            @set_time_limit(0);
            $content = file_get_contents($path);
            if ($content === false || ! str_starts_with(ltrim($content), '-- NEURO ABSENSI database backup')
                || ! preg_match('/^-- Database: neuro_absensi\\s*$/m', $content)) {
                throw new \RuntimeException('Validasi file backup gagal saat pemulihan.');
            }
            $statements = $this->splitSqlStatements($content);
            $config = config('Database')->default;
            $config['database'] = 'neuro_absensi_test';
            $config['pConnect'] = false;

            $adminDb = db_connect();
            $adminDb->query('CREATE DATABASE IF NOT EXISTS neuro_absensi_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            $testDb = \Config\Database::connect($config, false);
            $testDb->query('SET FOREIGN_KEY_CHECKS=0');

            $tableCount = 0;
            foreach ($statements as $statement) {
                $sql = ltrim($statement);
                if ($sql === '' || str_starts_with($sql, '--')) {
                    continue;
                }
                if (preg_match('/^CREATE\\s+TABLE\\s+/i', $sql)) {
                    $tableCount++;
                } elseif (! preg_match('/^(SET\\s+|DROP\\s+TABLE\\s+|INSERT\\s+INTO\\s+)/i', $sql)) {
                    throw new \RuntimeException('Ditemukan perintah SQL di luar format backup NEURO yang diizinkan.');
                }
                $testDb->query($sql);
            }
            $testDb->query('SET FOREIGN_KEY_CHECKS=1');

            $tables = $testDb->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->getResultArray();
            if (count($tables) !== $tableCount) {
                throw new \RuntimeException('Jumlah tabel hasil pemulihan tidak sesuai dengan file backup.');
            }

            session()->remove('neuro_restore_token');
            @unlink($path);
            return redirect()->to('/backup')->with('success', 'Pemulihan uji berhasil. Database neuro_absensi_test berisi ' . count($tables) . ' tabel. Database utama neuro_absensi tidak diubah.');
        } catch (\Throwable $e) {
            log_message('error', 'Test database restore failed: {message}', ['message' => $e->getMessage()]);
            return redirect()->to('/backup')->with('error', 'Pemulihan uji gagal: ' . $e->getMessage());
        }
    }

    private function splitSqlStatements(string $sql): array
    {
        $statements = [];
        $buffer = '';
        $quote = null;
        $escaped = false;
        $lineComment = false;
        $length = strlen($sql);

        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];
            $next = $sql[$i + 1] ?? '';

            if ($lineComment) {
                if ($char === "\n") {
                    $lineComment = false;
                    $buffer .= $char;
                }
                continue;
            }
            if ($quote !== null) {
                $buffer .= $char;
                if ($escaped) {
                    $escaped = false;
                } elseif ($char === '\\') {
                    $escaped = true;
                } elseif ($char === $quote) {
                    if ($next === $quote) {
                        $buffer .= $sql[++$i];
                    } else {
                        $quote = null;
                    }
                }
                continue;
            }
            if ($char === "'" || $char === '"' || $char === '`') {
                $quote = $char;
                $buffer .= $char;
            } elseif ($char === '-' && $next === '-' && (($sql[$i + 2] ?? ' ') <= ' ')) {
                $lineComment = true;
                $i++;
            } elseif ($char === '#') {
                $lineComment = true;
            } elseif ($char === ';') {
                if (trim($buffer) !== '') {
                    $statements[] = trim($buffer);
                }
                $buffer = '';
            } else {
                $buffer .= $char;
            }
        }
        if (trim($buffer) !== '') {
            $statements[] = trim($buffer);
        }
        return $statements;
    }

    public function downloadNeuro()
    {
        @set_time_limit(0);
        $db = db_connect();
        $database = (string) $db->getDatabase();
        $filename = 'Backup-NEURO-ABSENSI-' . date('Ymd-His') . '.sql';
        $dir = WRITEPATH . 'backups';
        if (! is_dir($dir) && ! mkdir($dir, 0770, true) && ! is_dir($dir)) {
            return redirect()->to('/backup')->with('error', 'Folder backup tidak dapat dibuat.');
        }
        $path = $dir . DIRECTORY_SEPARATOR . $filename;
        $handle = fopen($path, 'wb');
        if ($handle === false) {
            return redirect()->to('/backup')->with('error', 'File backup database tidak dapat dibuat.');
        }

        try {
            fwrite($handle, "-- NEURO ABSENSI database backup\n");
            fwrite($handle, "-- Database: " . str_replace(["\r", "\n"], '', $database) . "\n");
            fwrite($handle, "-- Generated: " . date('Y-m-d H:i:s') . "\n");
            fwrite($handle, "-- Restore: pilih database tujuan, lalu impor file SQL ini.\n\n");
            fwrite($handle, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\nSET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n\n");

            $tables = $db->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->getResultArray();
            foreach ($tables as $tableRow) {
                $table = (string) array_values($tableRow)[0];
                $quotedTable = $db->escapeIdentifiers($table);
                $create = $db->query('SHOW CREATE TABLE ' . $quotedTable)->getRowArray();
                $createSql = (string) ($create['Create Table'] ?? array_values($create)[1] ?? '');
                if ($createSql === '') {
                    throw new \RuntimeException('Struktur tabel ' . $table . ' tidak dapat dibaca.');
                }

                fwrite($handle, "-- Table: " . $table . "\nDROP TABLE IF EXISTS " . $quotedTable . ";\n" . $createSql . ";\n");

                $fields = $db->getFieldData($table);
                $fieldNames = array_map(static fn ($field) => $field->name, $fields);
                $quotedFields = implode(', ', array_map([$db, 'escapeIdentifiers'], $fieldNames));
                $query = $db->query('SELECT * FROM ' . $quotedTable);
                $chunk = [];
                while ($row = $query->getUnbufferedRow('array')) {
                    $values = [];
                    foreach ($fieldNames as $index => $fieldName) {
                        $value = $row[$fieldName] ?? null;
                        if ($value === null) {
                            $values[] = 'NULL';
                            continue;
                        }
                        $type = strtolower((string) ($fields[$index]->type ?? ''));
                        if (preg_match('/(blob|binary|varbinary|bit)/', $type)) {
                            $values[] = '0x' . bin2hex((string) $value);
                        } elseif (preg_match('/^(tinyint|smallint|mediumint|int|bigint|decimal|numeric|float|double|real|year)/', $type) && is_numeric($value)) {
                            $values[] = (string) $value;
                        } else {
                            $values[] = $db->escape((string) $value);
                        }
                    }
                    $chunk[] = '(' . implode(', ', $values) . ')';
                    if (count($chunk) >= 200) {
                        fwrite($handle, 'INSERT INTO ' . $quotedTable . ' (' . $quotedFields . ") VALUES\n" . implode(",\n", $chunk) . ";\n");
                        $chunk = [];
                    }
                }
                if ($chunk !== []) {
                    fwrite($handle, 'INSERT INTO ' . $quotedTable . ' (' . $quotedFields . ") VALUES\n" . implode(",\n", $chunk) . ";\n");
                }
                fwrite($handle, "\n");
                $query->freeResult();
            }

            fwrite($handle, "SET FOREIGN_KEY_CHECKS=1;\n");
            fclose($handle);
            return $this->response->download($path, null)->setFileName($filename);
        } catch (\Throwable $e) {
            fclose($handle);
            @unlink($path);
            log_message('error', 'NEURO database backup failed: {message}', ['message' => $e->getMessage()]);
            return redirect()->to('/backup')->with('error', 'Backup database gagal dibuat. Periksa log aplikasi untuk detail teknis.');
        }
    }
}
