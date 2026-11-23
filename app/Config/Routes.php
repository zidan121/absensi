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
});
$routes->group('impor', ['filter' => 'auth'], static function ($routes) {
    $routes->get('/', 'Imports::index');
    $routes->post('preview', 'Imports::preview');
    $routes->post('proses', 'Imports::process');
});
$routes->get('health', static fn () => service('response')->setJSON(['app' => 'NEURO ABSENSI', 'status' => 'ok']));