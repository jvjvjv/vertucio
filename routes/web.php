<?php

use Illuminate\Support\Facades\Route;

Route::livewire('/', 'pages::search')->name('search')->middleware('throttle:60,1');
Route::livewire('sponsor', 'pages::sponsor')->name('sponsor')->middleware('throttle:20,1');
Route::view('privacy', 'pages.privacy')->name('privacy');
