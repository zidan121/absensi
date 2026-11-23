<?php

namespace App\Models;

use CodeIgniter\Model;

class UserModel extends Model
{
    protected $table = 'app_users';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = ['username', 'password_hash', 'full_name', 'is_active', 'created_at', 'updated_at'];
    protected $useTimestamps = true;
}