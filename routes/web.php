<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Config;

Route::post(Config::get('payfast.webhook'), 'FintechSystems\Payfast\Http\Controllers\WebhookController')->name('vendor.payfast.webhook');

Route::get('/payfast/success', function() {
    return view('vendor.payfast.success');
});

Route::get('/payfast/cancel', function() {
    return view('vendor.payfast.cancel');
});
