<?php

use App\Http\Controllers\ZohoAPIController;
use App\Http\Controllers\ZohoContactController;
use App\Http\Controllers\ZohoDealController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});


//task actions
Route::post('contact', [ZohoContactController::class, 'addContact']);
Route::post('deal', [ZohoDealController::class, 'addDeal']);

//just if you want to login.
Route::get('login', [ZohoAPIController::class, 'doZohoLogin']);

//auth actions
Route::get('logout', [ZohoAPIController::class, 'doZohoLogout']);
Route::get('refresh-token', [ZohoAPIController::class, 'refreshAccessToken']);
