<?php

namespace Config;

use CodeIgniter\Modules\Modules as BaseModules;

class Modules extends BaseModules
{
    public bool $enabled = true;
    public bool $discoverInComposer = true;
    public bool $composerPackages = true;
    public array $aliases = [];
}