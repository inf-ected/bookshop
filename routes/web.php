<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Temporary design system preview — remove before Phase 2
Route::get('/design-preview', function () {
    return view('design-preview');
});
