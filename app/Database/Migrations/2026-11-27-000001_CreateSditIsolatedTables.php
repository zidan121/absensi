<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateSditIsolatedTables extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'source_employee_id' => ['type' => 'VARCHAR', 'constraint' => 32, 'null' => true],
            'pin' => ['type' => 'VARCHAR', 'constraint' => 32],
            'employee_code' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'full_name' => ['type' => 'VARCHAR', 'constraint' => 255],
            'position' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'phone' => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'division_1' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'division_2' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'division_3' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'employment_start' => ['type' => 'DATE', 'null' => true],
            'employment_end' => ['type' => 'DATE', 'null' => true],
            'is_active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('pin', 'uq_sdit_employees_pin');
        $this->forge->createTable('sdit_employees', true);

        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'import_batch_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'machine_serial' => ['type' => 'VARCHAR', 'constraint' => 40, 'default' => 'FS2010195000514'],
            'pin' => ['type' => 'VARCHAR', 'constraint' => 32],
            'scanned_at' => ['type' => 'DATETIME'],
            'verify_mode' => ['type' => 'INT', 'default' => 1],
            'inout_mode' => ['type' => 'INT', 'default' => 0],
            'source_format' => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'SQL'],
            'source_row' => ['type' => 'INT', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['machine_serial', 'pin', 'scanned_at'], 'uq_sdit_scan_identity');
        $this->forge->addKey(['pin', 'scanned_at'], false, false, 'idx_sdit_pin_time');
        $this->forge->createTable('sdit_attendance_logs', true);

        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'import_batch_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'machine_serial' => ['type' => 'VARCHAR', 'constraint' => 40, 'default' => 'FS2010195000514'],
            'pin' => ['type' => 'VARCHAR', 'constraint' => 32],
            'original_scanned_at' => ['type' => 'VARCHAR', 'constraint' => 40],
            'reason' => ['type' => 'VARCHAR', 'constraint' => 100, 'default' => 'Waktu Mesin Tidak Valid'],
            'source_format' => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'BIN'],
            'source_row' => ['type' => 'INT', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['pin', 'original_scanned_at'], false, false, 'idx_sdit_invalid_pin_time');
        $this->forge->createTable('sdit_invalid_scans', true);

        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'source_format' => ['type' => 'VARCHAR', 'constraint' => 10],
            'original_filename' => ['type' => 'VARCHAR', 'constraint' => 255],
            'file_sha256' => ['type' => 'CHAR', 'constraint' => 64],
            'total_rows' => ['type' => 'INT', 'default' => 0],
            'valid_rows' => ['type' => 'INT', 'default' => 0],
            'invalid_rows' => ['type' => 'INT', 'default' => 0],
            'duplicate_rows' => ['type' => 'INT', 'default' => 0],
            'status' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'completed'],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('sdit_import_batches', true);
    }

    public function down()
    {
        $this->forge->dropTable('sdit_import_batches', true);
        $this->forge->dropTable('sdit_invalid_scans', true);
        $this->forge->dropTable('sdit_attendance_logs', true);
        $this->forge->dropTable('sdit_employees', true);
    }
}
