<?php

use Illuminate\Support\Facades\Route;


use App\Http\Controllers\AdmsController;

Route::get('/', function () {
    return view('welcome');
});

Route::group(['prefix' => 'iclock'], function () {
    Route::get('cdata', [AdmsController::class, 'cdata']);
    Route::post('cdata', [AdmsController::class, 'cdata']);
    Route::get('getrequest', [AdmsController::class, 'getrequest']);
    Route::post('devicecmd', [AdmsController::class, 'devicecmd']);
});
