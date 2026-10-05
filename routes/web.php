<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('dashboard', 'pages::dashboard')->name('dashboard');
    Route::livewire('shops', 'pages::shops.index')->name('shops.index');
    Route::livewire('legal-texts', 'pages::legal-texts.index')->name('legal-texts.index');
    Route::livewire('legal-texts/{type}', 'pages::legal-texts.edit')->name('legal-texts.edit');
});

require __DIR__.'/settings.php';
