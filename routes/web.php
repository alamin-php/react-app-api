<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::resource('Invoices', App\Http\Controllers\InvoiceController::class);