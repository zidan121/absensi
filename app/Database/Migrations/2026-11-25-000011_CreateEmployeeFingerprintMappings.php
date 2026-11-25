<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateEmployeeFingerprintMappings extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'employee_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'device_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'fingerprint_pin' => ['type' => 'VARCHAR', 'constraint' => 32],
            'target_pegawai_id' => ['type' => 'INT', 'constraint' => 11, 'null' => true],
            'effective_from' => ['type' => 'DATE', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['employee_id', 'device_id'], 'uq_fp_map_employee_device');
        $this->forge->addUniqueKey(['device_id', 'fingerprint_pin'], 'uq_fp_map_device_pin');
        $this->forge->addKey('device_id', false, false, 'idx_fp_map_device');
        $this->forge->createTable('employee_fingerprint_mappings', true);

        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'employee_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'device_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'fingerprint_pin' => ['type' => 'VARCHAR', 'constraint' => 32],
            'target_pegawai_id' => ['type' => 'INT', 'constraint' => 11, 'null' => true],
            'action' => ['type' => 'VARCHAR', 'constraint' => 30],
            'effective_from' => ['type' => 'DATE', 'null' => true],
            'effective_until' => ['type' => 'DATE', 'null' => true],
            'notes' => ['type' => 'TEXT', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['employee_id', 'device_id'], false, false, 'idx_fp_map_history_employee');
        $this->forge->createTable('employee_fingerprint_mapping_history', true);

        // Backfill only unambiguous legacy employee/device/PIN pairs.
        // Conflicting PINs are deliberately left out for manual review.
        $this->db->query("
            INSERT INTO employee_fingerprint_mappings
                (employee_id, device_id, fingerprint_pin, effective_from, created_at, updated_at)
            SELECT e.id, e.fingerprint_device_id, e.fingerprint_pin, e.employment_start, NOW(), NOW()
            FROM employees e
            INNER JOIN (
                SELECT fingerprint_device_id AS device_id, fingerprint_pin
                FROM employees
                WHERE fingerprint_device_id IS NOT NULL
                  AND fingerprint_pin IS NOT NULL
                  AND fingerprint_pin <> ''
                GROUP BY fingerprint_device_id, fingerprint_pin
                HAVING COUNT(*) = 1
            ) unique_pin
              ON unique_pin.device_id = e.fingerprint_device_id
             AND unique_pin.fingerprint_pin = e.fingerprint_pin
            WHERE e.fingerprint_device_id IS NOT NULL
              AND e.fingerprint_pin IS NOT NULL
              AND e.fingerprint_pin <> ''
        ");

        $this->db->query("
            INSERT INTO employee_fingerprint_mapping_history
                (employee_id, device_id, fingerprint_pin, action, effective_from, notes, created_at)
            SELECT employee_id, device_id, fingerprint_pin, 'legacy_backfill', effective_from,
                   'Migrasi dari employees.fingerprint_device_id dan employees.fingerprint_pin; pasangan unik.',
                   NOW()
            FROM employee_fingerprint_mappings
        ");
    }

    public function down()
    {
        $this->forge->dropTable('employee_fingerprint_mapping_history', true);
        $this->forge->dropTable('employee_fingerprint_mappings', true);
    }
}
