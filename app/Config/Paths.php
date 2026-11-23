<?php

namespace Config;

class Paths
{
    public string $systemDirectory;
    public string $appDirectory;
    public string $writableDirectory;
    public string $testsDirectory;
    public string $viewDirectory;

    public function __construct()
    {
        $this->systemDirectory = ROOTPATH . 'vendor/codeigniter4/framework/system';
        $this->appDirectory = APPPATH;
        $this->writableDirectory = ROOTPATH . 'writable';
        $this->testsDirectory = ROOTPATH . 'tests';
        $this->viewDirectory = APPPATH . 'Views';
    }
}