<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Signed file serving (signature validates access, no auth middleware needed)
Route::get('/files/serve', [\App\Http\Controllers\FileServeController::class, 'serve'])->name('files.serve');

// Redirect /api/documentation to Scramble's default docs path
Route::get('/api/documentation', function () {
    return redirect('/docs/api');
});
