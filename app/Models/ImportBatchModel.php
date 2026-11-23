<?php

namespace App\Models;

use CodeIgniter\Model;

class ImportBatchModel extends Model
{
    protected $table = 'import_batches';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = ['device_id', 'original_filename', 'file_sha256', 'total_rows', 'valid_rows', 'invalid_rows', 'imported_by', 'created_at'];
    protected $useTimestamps = false;
}