<?php

use Illuminate\Support\Facades\Route;

// This app is an API backend; the UI is served by the Nuxt frontend.
// Hitting the API root just bounces to the frontend so the default
// Laravel welcome page is never shown.
Route::get('/', function () {
    return redirect()->away(env('FRONTEND_URL', 'http://localhost:3000'));
});
