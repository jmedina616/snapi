<?php

use Illuminate\Http\Request;

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

//Route::middleware('auth:api')->get('/user', function (Request $request) {
//    return $request->user();
//});


// Protected with smhAuth Middleware
Route::middleware('smhAuth')->group(function () {
    Route::prefix('sn')->group(function () {
        // Get users' social network configuration
        Route::get('/configuration/pid/{partner_id}/ks/{ks}/projection/{projection}', 'SocialNetworkConfigurationController@show');
    });
});


// Throw exception if endpoint does not match
Route::fallback('SmhApiController@notFound');
