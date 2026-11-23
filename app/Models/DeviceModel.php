<?php

namespace App\Models;

use CodeIgniter\Model;

class DeviceModel extends Model
{
    protected $table = 'devices';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = ['device_code', 'device_name', 'unit_scope', 'model_name', 'is_active', 'created_at', 'updated_at'];
    protected $useTimestamps = true;
}