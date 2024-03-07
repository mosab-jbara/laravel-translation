<?php

use Illuminate\Support\Facades\Route;
use Mosab\Translation\Controllers\LanguagesController;

Route::get('languages/available', [LanguagesController::class, 'show']);
Route::apiResource('languages', LanguagesController::class)->except('update', 'show');
