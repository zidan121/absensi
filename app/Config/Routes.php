<?php

namespace Config;

use CodeIgniter\Config\BaseService;
use CodeIgniter\Config\BaseConfig;
use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes = Services::routes();
$routes->setDefaultNamespace('App\\Controllers');
$routes->setDefaultController('Dashboard');
$routes->setDefaultMethod('index');
$routes->setTranslateURIDashes(false);
$routes->set404Override();
$routes->setAutoRoute(false);

$routes->get('/', 'Dashboard::index');
$routes->get('login', 'Auth::login');
$routes->post('login', 'Auth::attempt');
$routes->get('logout', 'Auth::logout');
$routes->group('pegawai', ['filter' => 'auth'], static function ($routes) {
    $routes->get('/', 'Employees::index');
    $routes->get('baru', 'Employees::create');
    $routes->post('simpan', 'Employees::store');
    $routes->get('(:num)/edit', 'Employees::edit/$1');
    $routes->post('(:num)/update', 'Employees::update/$1');
});
$routes->group('impor-backup', ['filter' => 'auth'], static function ($routes) {
    $routes->get('/', 'BackupImports::index');
    $routes->post('master/preview', 'BackupImports::masterPreview');
    $routes->post('master/proses', 'BackupImports::masterProcess');
    $routes->post('logs/preview', 'BackupImports::logPreview');
    $routes->post('logs/proses', 'BackupImports::logProcess');
});
$routes->group('impor', ['filter' => 'auth'], static function ($routes) {
    $routes->get('/', 'Imports::index');
    $routes->post('preview', 'Imports::preview');
    $routes->post('proses', 'Imports::process');
});
$routes->get('health', static fn () => service('response')->setJSON(['app' => 'NEURO ABSENSI', 'status' => 'ok']));