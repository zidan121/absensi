<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class Cache extends BaseConfig
{
    public string $handler = 'file';
    public string $backupHandler = 'dummy';
    public string $storePath = WRITEPATH . 'cache/';
    public int $cacheQueryString = false;
    public array $prefix = '';
    public int $ttl = 60;
    public string $reservedCharacters = '{}()/\\@:';
    public array $file = ['storePath' => WRITEPATH . 'cache/', 'mode' => 0640];
    public array $memcached = ['host' => '127.0.0.1', 'port' => 11211, 'weight' => 1, 'raw' => false];
    public array $redis = ['host' => '127.0.0.1', 'password' => null, 'port' => 6379, 'timeout' => 0, 'database' => 0];
    public string $validHandlers = 'CodeIgniter\\Cache\\Handlers\\FileHandler';
}