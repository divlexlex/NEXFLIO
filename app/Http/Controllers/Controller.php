<?php

namespace App\Http\Controllers;

abstract class Controller
{
    //
}
use App\Http\Controllers\API\ServiceController;

Route::get('/services', [ServiceController::class, 'index']);
Route::post('/services', [ServiceController::class, 'store']);