<?php

use Illuminate\Support\Facades\Route;

// Dashboard Stats
Route::get('/stats', 'DashboardController@stats');
Route::get('/recent', 'DashboardController@recent');
Route::get('/certificate-status', 'DashboardController@certificateStatus');

// Invoices
Route::get('/invoices', 'InvoiceController@index');
Route::get('/invoices/{uuid}', 'InvoiceController@show');

// API Logs
Route::get('/api-logs', 'ApiLogController@index');
Route::get('/api-logs/{uuid}', 'ApiLogController@show');

// Certificates
Route::get('/certificates', 'CertificateController@index');

// Devices
Route::get('/devices', 'DeviceController@index');

// Chain
Route::get('/chain', 'ChainController@index');

// Settings
Route::get('/settings', 'SettingsController@index');
