<?php

use Illuminate\Support\Facades\Route;

// Dashboard SPA — catch-all route serves the Blade layout
Route::get('/{any?}', 'DashboardController@index')->where('any', '.*');
