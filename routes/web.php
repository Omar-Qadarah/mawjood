<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::view('/signup', 'customer-signup')->name('customer.signup');
Route::view('/admin', 'admin.pharmacies')->name('admin.pharmacies');
Route::view('/admin/forgot-password', 'admin.password-recovery', ['mode' => 'request'])->name('admin.password.request');
Route::view('/admin/reset-password', 'admin.password-recovery', ['mode' => 'reset'])->name('admin.password.reset');
