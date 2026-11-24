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
