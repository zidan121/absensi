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
$routes->group('akun', ['filter' => 'auth'], static function ($routes) {
    $routes->get('/', 'AccountSettings::index');
    $routes->post('simpan', 'AccountSettings::update');
});
$routes->group('backup', ['filter' => 'auth'], static function ($routes) {
    $routes->get('/', 'BackupExports::index');
    $routes->post('simpan-sumber', 'BackupExports::saveSource');
    $routes->get('sumber/(:segment)', 'BackupExports::downloadSource/$1');
    $routes->get('neuro', 'BackupExports::downloadNeuro');
    $routes->post('pemulihan/preview', 'BackupExports::previewRestore');
    $routes->post('pemulihan/proses', 'BackupExports::restoreTest');
});
$routes->group('backup/fingerspot', ['filter' => 'auth'], static function ($routes) {
    $routes->get('/', 'FingerspotExport::index');
    $routes->post('review', 'FingerspotExport::review');
    $routes->post('generate', 'FingerspotExport::generate');
    $routes->get('hasil', 'FingerspotExport::result');
    $routes->get('download', 'FingerspotExport::download');
});
$routes->group('rekap', ['filter' => 'auth'], static function ($routes) {
    $routes->get('/', 'Recap::index');
    $routes->get('export', 'Recap::export');
});
$routes->group('pengaturan-absensi', ['filter' => 'auth'], static function ($routes) {
    $routes->get('/', 'AttendanceSettings::index');
    $routes->post('simpan-unit', 'AttendanceSettings::saveUnitRule');
    $routes->post('perbarui/(:num)', 'AttendanceSettings::updateRule/$1');
    $routes->post('hapus/(:num)', 'AttendanceSettings::deleteRule/$1');
});
$routes->group('kalender-kerja', ['filter' => 'auth'], static function ($routes) {
    $routes->get('/', 'WorkCalendar::index');
    $routes->post('simpan', 'WorkCalendar::store');
    $routes->post('hapus/(:num)', 'WorkCalendar::delete/$1');
});
$routes->group('jadwal-hari-kerja', ['filter' => 'auth'], static function ($routes) {
    $routes->get('/', 'WeeklySchedules::index');
    $routes->post('simpan', 'WeeklySchedules::store');
    $routes->post('hapus/(:num)', 'WeeklySchedules::delete/$1');
});
$routes->group('pegawai', ['filter' => 'auth'], static function ($routes) {
    $routes->get('/', 'Employees::index');
    $routes->get('baru', 'Employees::create');
    $routes->post('simpan', 'Employees::store');
    $routes->get('(:num)/edit', 'Employees::edit/$1');
    $routes->post('(:num)/update', 'Employees::update/$1');
    $routes->get('(:num)/perangkat', 'EmployeeFingerprintMappings::index/$1');
    $routes->post('(:num)/perangkat/simpan', 'EmployeeFingerprintMappings::save/$1');
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
    $routes->get('preview', 'Imports::showPreview');
    $routes->post('proses', 'Imports::process');
});
$routes->get('health', static fn () => service('response')->setJSON(['app' => 'NEURO ABSENSI', 'status' => 'ok']));
