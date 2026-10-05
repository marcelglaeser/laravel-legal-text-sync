<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('dashboard', 'pages::dashboard')->name('dashboard');
    Route::livewire('company-profile', 'pages::company-profile')->name('company-profile.edit');
    Route::livewire('shops', 'pages::shops.index')->name('shops.index');
    Route::livewire('legal-texts', 'pages::legal-texts.index')->name('legal-texts.index');
    Route::livewire('legal-texts/{type}', 'pages::legal-texts.show')->name('legal-texts.show');

    Route::middleware('can:manage-templates')->prefix('admin')->name('admin.')->group(function () {
        Route::livewire('templates', 'pages::admin.templates.index')->name('templates.index');
        Route::livewire('templates/{type}', 'pages::admin.templates.edit')->name('templates.edit');
    });
});

require __DIR__.'/settings.php';
