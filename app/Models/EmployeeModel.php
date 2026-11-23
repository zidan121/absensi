<?php

namespace App\Models;

use CodeIgniter\Model;

class EmployeeModel extends Model
{
    protected $table = 'employees';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = ['employee_code', 'fingerprint_pin', 'fingerprint_device_id', 'unit_code', 'source_employee_id', 'source_division_1', 'source_division_2', 'source_division_3', 'employment_start', 'employment_end', 'full_name', 'position', 'is_active', 'created_at', 'updated_at'];
    protected $useTimestamps = true;

    /**
     * Resolve a fingerprint PIN only within its originating device.
     * PIN values are not globally unique across the foundation's machines.
     */
    public function findByFingerprintIdentity(int $deviceId, string $pin): ?array
    {
        return $this->where('fingerprint_device_id', $deviceId)
            ->where('fingerprint_pin', $pin)
            ->first();
    }
}