<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class Exceptions extends BaseConfig
{
    public bool $log = true;

    // CodeIgniter 4.6 reads this exact property name.
    public array $ignoreCodes = [404];

    public string $errorViewPath = APPPATH . 'Views/errors';

    public array $sensitiveDataInTrace = [];

    public bool $logDeprecations = false;

    public string $deprecationLogLevel = 'warning';
}
