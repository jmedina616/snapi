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
Route::group(['middleware' => ['smhAuth', 'smhSocialBroadcastingService']], function () {
    Route::prefix('sn/configuration/')->group(function () {
        //Get social network configurations or Resync platform account
        Route::get('{action}/{platform}/pid/{partner_id}/ks/{ks}', 'SocialNetworkConfigurationController@show');
        // Update platform settings
        Route::put('{platform}/pid/{partner_id}/ks/{ks}', 'SocialNetworkConfigurationController@store');
        //Remove platform authentication
        Route::delete('{platform}/pid/{partner_id}/ks/{ks}', 'SocialNetworkConfigurationController@destroy');
    });
});


// Throw exception if endpoint does not match
Route::fallback('SmhApiController@notFound');
