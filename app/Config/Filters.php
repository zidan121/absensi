<?php

namespace Config;

use CodeIgniter\Config\Filters as BaseFilters;
use CodeIgniter\Filters\CSRF;
use CodeIgniter\Filters\DebugToolbar;
use CodeIgniter\Filters\Honeypot;

class Filters extends BaseFilters
{
    public array $aliases = [
        'csrf' => CSRF::class,
        'toolbar' => DebugToolbar::class,
        'honeypot' => Honeypot::class,
        'auth' => \App\Filters\AuthFilter::class,
    ];

    public array $required = [
        'before' => [],
        'after' => [],
    ];

    public array $globals = [
        'before' => ['csrf'],
        'after' => ['toolbar'],
    ];

    public array $methods = [];

    public array $filters = [];
}
