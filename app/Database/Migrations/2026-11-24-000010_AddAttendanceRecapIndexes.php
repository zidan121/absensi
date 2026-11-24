<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddAttendanceRecapIndexes extends Migration
{
    public function up()
    {
        // Date-range recap scans all devices/units; a leading scanned_at index
        // lets MySQL seek the requested period instead of scanning the full log table.
        $this->db->query('ALTER TABLE attendance_logs ADD INDEX idx_attendance_scanned_at (scanned_at)');
        $this->db->query('ALTER TABLE attendance_logs ADD INDEX idx_attendance_device_pin_date (device_id, pin, scanned_at)');
        $this->db->query('ALTER TABLE employee_schedules ADD INDEX idx_employee_schedule_effective (employee_id, effective_from, effective_to)');
        $this->db->query('ALTER TABLE unit_attendance_rules ADD INDEX idx_unit_rule_effective (unit_code, effective_from, effective_to)');
        $this->db->query('ALTER TABLE unit_weekly_schedules ADD INDEX idx_weekly_schedule_effective (unit_code, effective_from, effective_to)');
        $this->db->query('ALTER TABLE work_calendar_events ADD INDEX idx_calendar_date_target (event_date, target_type, unit_code, employee_id)');
    }

    public function down()
    {
        $this->db->query('ALTER TABLE attendance_logs DROP INDEX idx_attendance_scanned_at');
        $this->db->query('ALTER TABLE attendance_logs DROP INDEX idx_attendance_device_pin_date');
        $this->db->query('ALTER TABLE employee_schedules DROP INDEX idx_employee_schedule_effective');
        $this->db->query('ALTER TABLE unit_attendance_rules DROP INDEX idx_unit_rule_effective');
        $this->db->query('ALTER TABLE unit_weekly_schedules DROP INDEX idx_weekly_schedule_effective');
        $this->db->query('ALTER TABLE work_calendar_events DROP INDEX idx_calendar_date_target');
    }
}
