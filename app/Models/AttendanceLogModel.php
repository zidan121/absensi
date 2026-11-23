<?php

namespace App\Models;

use CodeIgniter\Model;

class AttendanceLogModel extends Model
{
    protected $table = 'attendance_logs';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = ['import_batch_id', 'device_id', 'machine_no', 'pin', 'employee_name_source', 'verify_mode', 'inout_mode', 'scanned_at', 'source_row', 'created_at'];
    protected $useTimestamps = false;

    /**
     * Join attendance scans to the employee using both device ID and PIN.
     * This prevents identical PINs on different machines from being mixed.
     */
    public function withMatchedEmployee()
    {
        return $this->select('attendance_logs.*, employees.id AS matched_employee_id, employees.full_name AS matched_employee_name, employees.unit_code AS matched_unit_code')
            ->join(
                'employees',
                'employees.fingerprint_device_id = attendance_logs.device_id AND employees.fingerprint_pin = attendance_logs.pin',
                'left'
            );
    }
}