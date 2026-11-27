<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateSditIsolatedTables extends Migration
{
    public function up()
    {
        $this->db->query("CREATE TABLE IF NOT EXISTS sdit_employees (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            source_employee_id VARCHAR(32) NULL,
            pin VARCHAR(32) NOT NULL,
            employee_code VARCHAR(50) NULL,
            full_name VARCHAR(255) NOT NULL,
            position VARCHAR(100) NULL,
            phone VARCHAR(30) NULL,
            division_1 VARCHAR(100) NULL,
            division_2 VARCHAR(100) NULL,
            division_3 VARCHAR(100) NULL,
            employment_start DATE NULL,
            employment_end DATE NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NULL,
            updated_at DATETIME NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_sdit_employees_pin (pin)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $this->db->query("CREATE TABLE IF NOT EXISTS sdit_attendance_logs (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            import_batch_id BIGINT UNSIGNED NULL,
            machine_serial VARCHAR(40) NOT NULL DEFAULT 'FS2010195000514',
            pin VARCHAR(32) NOT NULL,
            scanned_at DATETIME NOT NULL,
            verify_mode INT NOT NULL DEFAULT 1,
            inout_mode INT NOT NULL DEFAULT 0,
            source_format VARCHAR(10) NOT NULL DEFAULT 'SQL',
            source_row INT NULL,
            created_at DATETIME NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_sdit_scan_identity (machine_serial, pin, scanned_at),
            KEY idx_sdit_pin_time (pin, scanned_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $this->db->query("CREATE TABLE IF NOT EXISTS sdit_invalid_scans (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            import_batch_id BIGINT UNSIGNED NULL,
            machine_serial VARCHAR(40) NOT NULL DEFAULT 'FS2010195000514',
            pin VARCHAR(32) NOT NULL,
            original_scanned_at VARCHAR(40) NOT NULL,
            reason VARCHAR(100) NOT NULL DEFAULT 'Waktu Mesin Tidak Valid',
            source_format VARCHAR(10) NOT NULL DEFAULT 'BIN',
            source_row INT NULL,
            created_at DATETIME NULL,
            PRIMARY KEY (id),
            KEY idx_sdit_invalid_pin_time (pin, original_scanned_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $this->db->query("CREATE TABLE IF NOT EXISTS sdit_import_batches (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            source_format VARCHAR(10) NOT NULL,
            original_filename VARCHAR(255) NOT NULL,
            file_sha256 CHAR(64) NOT NULL,
            total_rows INT NOT NULL DEFAULT 0,
            valid_rows INT NOT NULL DEFAULT 0,
            invalid_rows INT NOT NULL DEFAULT 0,
            duplicate_rows INT NOT NULL DEFAULT 0,
            status VARCHAR(20) NOT NULL DEFAULT 'completed',
            created_at DATETIME NULL,
            PRIMARY KEY (id),
            KEY idx_sdit_import_hash (file_sha256, source_format)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    public function down()
    {
        $this->forge->dropTable('sdit_import_batches', true);
        $this->forge->dropTable('sdit_invalid_scans', true);
        $this->forge->dropTable('sdit_attendance_logs', true);
        $this->forge->dropTable('sdit_employees', true);
    }
}
