<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateCoreTables extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'username' => ['type' => 'VARCHAR', 'constraint' => 80],
            'password_hash' => ['type' => 'VARCHAR', 'constraint' => 255],
            'full_name' => ['type' => 'VARCHAR', 'constraint' => 150],
            'is_active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('username');
        $this->forge->createTable('app_users', true);

        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'employee_code' => ['type' => 'VARCHAR', 'constraint' => 40],
            'full_name' => ['type' => 'VARCHAR', 'constraint' => 150],
            'position' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'is_active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('employee_code');
        $this->forge->createTable('employees', true);

        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'device_code' => ['type' => 'VARCHAR', 'constraint' => 40],
            'device_name' => ['type' => 'VARCHAR', 'constraint' => 120],
            'unit_scope' => ['type' => 'VARCHAR', 'constraint' => 80],
            'model_name' => ['type' => 'VARCHAR', 'constraint' => 100],
            'is_active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('device_code');
        $this->forge->createTable('devices', true);

        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'device_id' => ['type' => 'INT', 'unsigned' => true],
            'original_filename' => ['type' => 'VARCHAR', 'constraint' => 255],
            'file_sha256' => ['type' => 'CHAR', 'constraint' => 64],
            'total_rows' => ['type' => 'INT', 'default' => 0],
            'valid_rows' => ['type' => 'INT', 'default' => 0],
            'invalid_rows' => ['type' => 'INT', 'default' => 0],
            'imported_by' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('file_sha256');
        $this->forge->createTable('import_batches', true);

        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'import_batch_id' => ['type' => 'BIGINT', 'unsigned' => true],
            'device_id' => ['type' => 'INT', 'unsigned' => true],
            'machine_no' => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'pin' => ['type' => 'VARCHAR', 'constraint' => 40],
            'employee_name_source' => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'verify_mode' => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'inout_mode' => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'scanned_at' => ['type' => 'DATETIME'],
            'source_row' => ['type' => 'INT', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['device_id', 'pin', 'scanned_at'], 'uq_device_pin_scan');
        $this->forge->addKey(['pin', 'scanned_at']);
        $this->forge->createTable('attendance_logs', true);
    }

    public function down()
    {
        foreach (['attendance_logs', 'import_batches', 'devices', 'employees', 'app_users'] as $table) {
            $this->forge->dropTable($table, true);
        }
    }
}