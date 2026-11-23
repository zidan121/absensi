<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;
use CodeIgniter\Debug\ExceptionHandler;
use Psr\Log\LogLevel;

class Exceptions extends BaseConfig
{
    public bool $log = true;
    public array $ignoredCodes = [404];
    public string $errorViewPath = APPPATH . 'Views/errors';
    public array $logLevels = [
        LogLevel::EMERGENCY, LogLevel::ALERT, LogLevel::CRITICAL,
        LogLevel::ERROR, LogLevel::WARNING, LogLevel::NOTICE,
        LogLevel::INFO, LogLevel::DEBUG,
    ];
}