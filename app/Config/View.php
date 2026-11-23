<?php

namespace Config;

use CodeIgniter\Config\View as BaseView;
use CodeIgniter\View\ViewDecoratorInterface;

class View extends BaseView
{
    public $saveData = true;

    /** @var array<string, callable|string> */
    public $filters = [];

    /** @var array<string, callable|array|string> */
    public $plugins = [];

    /** @var list<class-string<ViewDecoratorInterface>> */
    public array $decorators = [];
}
