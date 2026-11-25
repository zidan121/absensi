<?php

namespace App\Models;

use CodeIgniter\Model;

class EmployeeFingerprintMappingModel extends Model
{
    protected $table = 'employee_fingerprint_mappings';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'employee_id',
        'device_id',
        'fingerprint_pin',
        'target_pegawai_id',
        'effective_from',
        'created_at',
        'updated_at',
    ];
    protected $useTimestamps = true;

    public function findForDevice(int $deviceId): array
    {
        return $this->where('device_id', $deviceId)
            ->orderBy('fingerprint_pin', 'ASC')
            ->findAll();
    }

    public function findForEmployee(int $employeeId): array
    {
        return $this->where('employee_id', $employeeId)
            ->orderBy('effective_from', 'ASC')
            ->findAll();
    }
}
