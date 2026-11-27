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
    $routes->get('export-rekap-pegawai', 'Recap::exportEmployeeSummary');
});
$routes->group('pengaturan-urutan-pegawai', ['filter' => 'auth'], static function ($routes) {
    $routes->get('/', 'EmployeeRecapOrders::index');
    $routes->post('simpan', 'EmployeeRecapOrders::save');
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
    $routes->post('(:num)/status', 'Employees::toggleStatus/$1');
    $routes->get('(:num)/edit', 'Employees::edit/$1');
    $routes->post('(:num)/update', 'Employees::update/$1');
    $routes->get('(:num)/perangkat', 'EmployeeFingerprintMappings::index/$1');
    $routes->post('(:num)/perangkat/simpan', 'EmployeeFingerprintMappings::save/$1');
    $routes->get('(:num)/jadwal', 'EmployeeSchedules::index/$1');
    $routes->post('(:num)/jadwal/simpan', 'EmployeeSchedules::save/$1');
    $routes->post('(:num)/jadwal/hapus/(:num)', 'EmployeeSchedules::delete/$1/$2');
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
$routes->group('sdit', ['filter' => 'auth'], static function ($routes) {
    $routes->get('/', 'Sdit::index');
    $routes->get('impor', 'Sdit::imports');
    $routes->post('impor/preview', 'Sdit::previewImport');
    $routes->post('impor/proses-bin', 'Sdit::processBinImport');
    $routes->post('impor/sql/preview', 'Sdit::previewSqlEmployees');
    $routes->post('impor/sql/proses', 'Sdit::processSqlEmployees');
    $routes->post('impor/sql-scan/preview', 'Sdit::previewSqlLogs');
    $routes->post('impor/sql-scan/proses', 'Sdit::processSqlLogs');
    $routes->get('impor/preview', 'Sdit::showPreview');
    $routes->get('scan', 'Sdit::scans');
    $routes->get('rekap', 'Sdit::recap');
    $routes->get('pemeriksaan-waktu', 'Sdit::timeCheck');
    $routes->get('pemetaan-pin', 'Sdit::pinMapping');
});
$routes->get('health', static fn () => service('response')->setJSON(['app' => 'NEURO ABSENSI', 'status' => 'ok']));
