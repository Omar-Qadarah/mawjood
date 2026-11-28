<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::view('/signup', 'customer-signup')->name('customer.signup');
Route::view('/admin', 'admin.pharmacies')->name('admin.pharmacies');
