<?php

namespace App\Models;

use CodeIgniter\Model;

class EmployeeModel extends Model
{
    protected $table = 'employees';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = ['employee_code', 'full_name', 'position', 'is_active', 'created_at', 'updated_at'];
    protected $useTimestamps = true;
}