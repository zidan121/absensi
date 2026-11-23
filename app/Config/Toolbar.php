<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class Toolbar extends BaseConfig
{
    public bool $collectVarData = true;
    public array $collectors = [];
    public array $views = [];
    public array $maxHistory = 20;
    public bool $enabled = false;
}