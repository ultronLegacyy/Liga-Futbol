<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

use App\Http\Controllers\TemporadaController;

Route::resource('temporadas', TemporadaController::class);