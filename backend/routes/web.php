<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Redirect /api/documentation to Scramble's default docs path
Route::get('/api/documentation', function () {
    return redirect('/docs/api');
});
