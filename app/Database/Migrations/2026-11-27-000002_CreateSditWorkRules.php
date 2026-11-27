<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateSditWorkRules extends Migration
{
    public function up()
    {
        $this->db->query("CREATE TABLE IF NOT EXISTS sdit_work_schedules (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            schedule_name VARCHAR(100) NOT NULL DEFAULT 'Jadwal SDIT',
            day_of_week TINYINT(1) NOT NULL,
            start_time TIME NOT NULL,
            end_time TIME NOT NULL,
            late_after_minutes SMALLINT NOT NULL DEFAULT 0,
            is_workday TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NULL,
            updated_at DATETIME NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_sdit_schedule_day (schedule_name, day_of_week)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $this->db->query("CREATE TABLE IF NOT EXISTS sdit_employee_schedules (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            pin VARCHAR(32) NOT NULL,
            day_of_week TINYINT(1) NOT NULL,
            start_time TIME NOT NULL,
            end_time TIME NOT NULL,
            late_after_minutes SMALLINT NOT NULL DEFAULT 0,
            effective_from DATE NULL,
            effective_until DATE NULL,
            is_workday TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NULL,
            updated_at DATETIME NULL,
            PRIMARY KEY (id),
            KEY idx_sdit_employee_schedule (pin, day_of_week, effective_from)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $this->db->query("CREATE TABLE IF NOT EXISTS sdit_work_calendar (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            work_date DATE NOT NULL,
            day_type VARCHAR(30) NOT NULL DEFAULT 'holiday',
            description VARCHAR(255) NULL,
            allow_afternoon_entry TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NULL,
            updated_at DATETIME NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_sdit_calendar_date (work_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    public function down()
    {
        $this->forge->dropTable('sdit_work_calendar', true);
        $this->forge->dropTable('sdit_employee_schedules', true);
        $this->forge->dropTable('sdit_work_schedules', true);
    }
}
