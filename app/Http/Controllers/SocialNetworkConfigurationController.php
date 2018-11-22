<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\GoogleController;
use App\Libraries\SocialMedia\GoogleClientApi;
use App\Libraries\SocialMedia\TwitchClientApi;
use App\SocialNetworkServices\Youtube;
use App\SocialNetworkServices\Twitch;
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
        switch ($request->action) {
            case 'get_config':
                return $this->getUserSocialNetworkConfiguration($request->partner_id, $request->ks, $request->projection);
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
    public function destroy($id) {
        //
    }

    // Get a user's social media configurations
    public function getUserSocialNetworkConfiguration($partner_id, $ks, $projection) {
        $platform_configs = array();
        $user_data = $this->createUserDataObject($partner_id, $ks, $projection);

        $youtube_channel = new Youtube(new GoogleClientApi);
        $youtube = $youtube_channel->getConfiguration($user_data);

        $twitch_channel = new Twitch(new TwitchClientApi);
        $twitch = $twitch_channel->getConfiguration($user_data);

        array_push($platform_configs, $youtube, $twitch);
        if ($platform_configs) {
            return new SocialNetworkConfigurationResource($platform_configs);
        } else {
            throw new SmhAPIException('config_not_found', $partner_id);
        }
    }

    protected function createUserDataObject($partner_id, $ks, $projection){
      $user_data = new \stdClass();
      $user_data->pid = $partner_id;
      $user_data->ks = $ks;
      $user_data->projection = $projection;
      return $user_data;
    }

}
