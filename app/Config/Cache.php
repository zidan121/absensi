<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class Cache extends BaseConfig
{
    public $handler = 'file';
    public $backupHandler = 'dummy';
    public $storePath = WRITEPATH . 'cache/';
    public $cacheQueryString = false;
    public $prefix = '';
    public $ttl = 60;
    public $reservedCharacters = '{}()/\\@:';
    public $file = ['storePath' => WRITEPATH . 'cache/', 'mode' => 0640];
    public $memcached = ['host' => '127.0.0.1', 'port' => 11211, 'weight' => 1, 'raw' => false];
    public $redis = ['host' => '127.0.0.1', 'password' => null, 'port' => 6379, 'timeout' => 0, 'database' => 0];
    public $validHandlers = [
        'dummy' => \CodeIgniter\Cache\Handlers\DummyHandler::class,
        'file' => \CodeIgniter\Cache\Handlers\FileHandler::class,
    ];
}