<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->away(env('FRONTEND_URL', 'http://localhost:3000'));
});
