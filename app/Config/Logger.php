<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;
use CodeIgniter\Log\Handlers\FileHandler;

class Logger extends BaseConfig
{
    public $threshold = 4;
    public $dateFormat = 'Y-m-d H:i:s';
    public $handlers = [
        FileHandler::class => [
            'handles' => ['critical', 'alert', 'emergency', 'debug', 'error', 'info', 'notice', 'warning'],
            'fileExtension' => 'log',
            'filePermissions' => 0644,
        ],
    ];
}