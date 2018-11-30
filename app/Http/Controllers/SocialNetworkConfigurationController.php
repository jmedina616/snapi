<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Resources\SocialNetworkConfigurationResource;
use App\Exceptions\SmhAPIException;

class SocialNetworkConfigurationController extends Controller {

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index() {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request) {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request) {
        //Create the user data object
        $user_data = $this->createUserDataObject($request->partner_id, $request->ks, $request->projection);
        
        //Get user's social media configurations
        $youtube = \App::make('App\SocialNetworkServices\Youtube')->getConfiguration($user_data);
        $twitch = \App::make('App\SocialNetworkServices\Twitch')->getConfiguration($user_data);

        //Build and return social media configurations array
        $platform_configs = array();
        array_push($platform_configs, $youtube, $twitch);
        if ($platform_configs) {
            return new SocialNetworkConfigurationResource($platform_configs);
        } else {
            throw new SmhAPIException('config_not_found', $request->partner_id);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request) {
        //
        return 'true';
    }

    //Creates a user data object
    protected function createUserDataObject($partner_id, $ks, $projection) {
        $user_data = new \stdClass();
        $user_data->pid = $partner_id;
        $user_data->ks = $ks;
        $user_data->projection = $projection;
        return $user_data;
    }

}
