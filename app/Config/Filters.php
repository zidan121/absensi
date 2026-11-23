<?php

namespace Config;

use CodeIgniter\Config\Filters as BaseFilters;

class Filters extends BaseFilters
{
    public array $aliases = [
        'csrf' => \CodeIgniter\Filters\CSRF::class,
        'toolbar' => \CodeIgniter\Filters\DebugToolbar::class,
        'honeypot' => \CodeIgniter\Filters\Honeypot::class,
        'auth' => \App\Filters\AuthFilter::class,
    ];

    public array $globals = [
        'before' => ['csrf'],
        'after' => ['toolbar'],
    ];

    public array $methods = [];
    public array $filters = [];
}