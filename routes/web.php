<?php

use Illuminate\Support\Facades\Route;

Route::livewire('/', 'pages::search')->name('search');
Route::livewire('sponsor', 'pages::sponsor')->name('sponsor');
Route::view('privacy', 'pages.privacy')->name('privacy');
