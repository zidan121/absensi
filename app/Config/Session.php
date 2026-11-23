<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class Session extends BaseConfig
{
    public string $driver = 'CodeIgniter\\Session\\Handlers\\FileHandler';
    public string $cookieName = 'neuroabsensi_session';
    public int $expiration = 7200;
    public string $savePath = WRITEPATH . 'session';
    public bool $matchIP = false;
    public int $timeToUpdate = 300;
    public bool $regenerateDestroy = false;
    public string $DBGroup = 'default';
    public ?string $table = null;
    public string $lockRetryInterval = '100000';
    public int $lockMaxRetries = 300;
}