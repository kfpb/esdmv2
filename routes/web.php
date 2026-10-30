<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Home', [
        'appName' => config('app.name'),
    ]);
})->name('home');

Route::get('/components', function () {
    return Inertia::render('Components');
})->name('components');

Route::get('/datatable', function () {
    return Inertia::render('Datatable');
})->name('datatable');

Route::get('/forms', function () {
    return Inertia::render('Forms');
})->name('forms');

Route::get('/charts', function () {
    return Inertia::render('Charts');
})->name('charts');
