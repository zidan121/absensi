<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class Validation extends BaseConfig
{
    public array $ruleSets = [
        \CodeIgniter\Validation\Rules::class,
        \CodeIgniter\Validation\FormatRules::class,
        \CodeIgniter\Validation\FileRules::class,
        \CodeIgniter\Validation\CreditCardRules::class,
    ];
    public string $errorView = 'CodeIgniter\\Validation\\Views\\list';
    public array $templates = [
        'list' => 'CodeIgniter\\Validation\\Views\\list',
        'single' => 'CodeIgniter\\Validation\\Views\\single',
    ];
}