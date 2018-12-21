<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Resources\SocialNetworkConfigurationResource;
use App\Http\Resources\SocialNetworkConfigurationResyncResource;
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
        switch ($request->platform) {
            case 'youtube':
                return \App::make('App\SocialNetworkServices\Youtube')->updateChannelSettings($request->partner_id, $request->auto_upload);
                break;
            case 'twitch':
                return \App::make('App\SocialNetworkServices\Twitch')->updateChannelSettings($request->partner_id, $request->auto_upload);
                break;
            default:
                throw new SmhAPIException('platform_not_found', $request->platform);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request) {
        //Create the user data object
        $user_data = $this->createUserDataObject($request->partner_id, $request->ks);
        switch ($request->action) {
            case 'get':
                //Get user's social media configurations
                $youtube = \App::make('App\SocialNetworkServices\Youtube')->getConfiguration($user_data);
                $twitch = \App::make('App\SocialNetworkServices\Twitch')->getConfiguration($user_data);

                //Build and return social media configurations array
                $platform_configs = array();
                array_push($platform_configs, $youtube, $twitch);
                if (count($platform_configs) > 0) {
                    return new SocialNetworkConfigurationResource($platform_configs);
                } else {
                    throw new SmhAPIException('config_not_found', $request->partner_id);
                }
                break;
            case 'resync':
                switch ($request->platform) {
                    case 'youtube':
                        return new SocialNetworkConfigurationResyncResource(\App::make('App\SocialNetworkServices\Youtube')->resyncAccount($user_data));
                        break;
                    case 'twitch':
                        return new SocialNetworkConfigurationResyncResource(\App::make('App\SocialNetworkServices\Twitch')->resyncAccount($user_data));
                        break;
                    default:
                        throw new SmhAPIException('platform_not_found', $request->platform);
                }
                break;
            default:
                throw new SmhAPIException('action_not_found', $request->action);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request) {
        switch ($request->platform) {
            case 'youtube':
                return \App::make('App\SocialNetworkServices\Youtube')->removePlatformAuthorization($request->partner_id);
                break;
            case 'twitch':
                return \App::make('App\SocialNetworkServices\Twitch')->removePlatformAuthorization($request->partner_id);
                break;
            default:
                throw new SmhAPIException('platform_not_found', $request->platform);
        }
    }

    //Creates a user data object
    protected function createUserDataObject($partner_id, $ks) {
        $user_data = new \stdClass();
        $user_data->pid = $partner_id;
        $user_data->ks = $ks;
        return $user_data;
    }

}
