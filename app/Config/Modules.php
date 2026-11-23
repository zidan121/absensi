<?php

namespace Config;

use CodeIgniter\Modules\Modules as BaseModules;

/**
 * Modules Configuration.
 *
 * This class is loaded before the Composer autoloader is initialized.
 */
class Modules extends BaseModules
{
    public $enabled = true;

    public $discoverInComposer = true;

    public $composerPackages = [];

    public $aliases = [
        'events',
        'filters',
        'registrars',
        'routes',
        'services',
    ];
}
