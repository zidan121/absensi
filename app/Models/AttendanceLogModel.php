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
}