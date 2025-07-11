<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('admin');
});

// Add login route that redirects to Filament login
Route::get('/login', function () {
    return redirect('/admin/login');
})->name('login');
