<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\GoogleController;
use App\YoutubeChannel;
use App\TwitchChannel;
use App\Http\Resources\SnConfigResource;
use App\Exceptions\SmhAPIException;

class SnConfigController extends Controller {

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
                return $this->getUserSnConfig($request->partner_id, $request->ks, $request->projection);
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
    public function getUserSnConfig($partner_id, $ks, $projection) {
        $platform_configs = array();
        $youtube_channel = new YoutubeChannel();
        $youtube = $youtube_channel->getPlatformConfig($partner_id, $ks, $projection);

        $twitch_channel = new TwitchChannel();
        $twitch = $twitch_channel->getPlatformConfig($partner_id, $ks);

        array_push($platform_configs, $youtube, $twitch);
        if ($platform_configs) {
            return new SnConfigResource($platform_configs);
        } else {
            throw new SmhAPIException('config_not_found', $partner_id);
        }
    }

}
