<?php

namespace App\SocialNetworkServices;

use App\TwitchChannel;
use App\TwitchChannelSetting;
use App\SocialNetworkServices\SocialNetwork;
use App\SocialNetworkServices\SocialPlatform;
use App\Libraries\SocialMedia\SocialMedia as SocialMediaAPI;
use App\Exceptions\SmhAPIException;

//Twitch service class
class Twitch extends SocialPlatform implements SocialNetwork {

    protected $social_media_client_api;
    protected $platform = 'twitch';

    public function __construct(SocialMediaAPI $social_media_client_api) {
        $this->social_media_client_api = $social_media_client_api;
    }

    //Builds and returns the platform configuration object
    public function getConfiguration($user_data) {
        $platform = new \stdClass();
        $platform_data = $this->getPlatformData($user_data->pid);

        //If platform data is found build and create platform object
        if (count($platform_data) > 0) {
            //Check if the user's access token is still valid
            $authorized = $this->validateToken($user_data->pid, $platform_data);
            if ($authorized['isValid']) {
                //Create channel details array
                $channel_details = $this->createChannelDetails($platform_data);
                //Create channel settings array
                $settings = $this->getSettings($platform_data['id']);
                //Create platform object
                $platform = $this->createPlatformObject($this->platform, $authorized['isValid'], $channel_details, $settings, null);
            } else {
                //Create platform object with redirect URL
                $platform = $this->createPlatformObject($this->platform, $authorized['isValid'], null, null, $this->social_media_client_api->getRedirectURL($user_data));
            }
        } else {
            //Create platform object with redirect URL
            $platform = $this->createPlatformObject($this->platform, false, null, null, $this->social_media_client_api->getRedirectURL($user_data));
        }

        return $platform;
    }

    //Creates channel details array
    protected function createChannelDetails($platform_data) {
        return array(
            'channel_id' => $platform_data['channel_id'],
            'channel_name' => $platform_data['name'],
            'channel_logo' => $platform_data['logo'],
        );
    }

    //Build and return settings array
    public function getSettings($id) {
        $settings = array(
            'auto_upload' => false,
        );
        $settings_data = TwitchChannelSetting::where('twitch_channel_id', '=', $id)->first();
        if ($settings_data) {
            $auto_upload = (bool) $settings_data->auto_upload;
            $settings = array(
                'auto_upload' => $auto_upload,
            );
        }
        return $settings;
    }

    //Checks if access token is valid, if not, use refresh token to generate a new access token
    public function validateToken($pid, $token) {
        $validation_result = array(
            'isValid' => false,
        );
        //Check if access token is valid from twitch
        $token_validation = $this->social_media_client_api->checkAuthToken($pid, $token);
        if ($token_validation['isValid']) {
            //If access token is vaild, return the token
            if ($token_validation['message'] == 'valid_access_token') {
                $validation_result = array(
                    'isValid' => true,
                    'access_token' => $token_validation['access_token'],
                );
            }
            //If access token is not vaild, return the newly generated access token
            if ($token_validation['message'] == 'new_access_token') {
                //Update the DB with the new access token
                $access_token = $this->updateDbTokens($pid, $token_validation['access_token']);
                if ($access_token['success']) {
                    $validation_result = array(
                        'isValid' => true,
                        'access_token' => $token_validation['access_token'],
                    );
                }
            }
        } else {
            $error = array('Twitch', $token_validation['message']);
            throw new SmhAPIException('socail_media_api_error', $error);
        }
        return $validation_result;
    }

    //Updates token in the DB
    protected function updateDbTokens($pid, $tokens) {
        $success = false;
        $twitch_data = TwitchChannel::where('partner_id', '=', $pid)->first();
        if ($twitch_data) {
            $twitch_data->access_token = smhEncrypt($tokens['access_token']);
            $twitch_data->refresh_token = smhEncrypt($tokens['refresh_token']);

            if ($twitch_data->save()) {
                $success = true;
            } else {
                throw new SmhAPIException('internal_database_error', 'Could not update Twitch tokens for account \'' . $pid . '\'');
            }
        }

        return $success;
    }

    //Builds and returns platform data from DB
    public function getPlatformData($pid) {
        $platform_data = array();
        $twitch_data = TwitchChannel::where('partner_id', '=', $pid)->first();
        if ($twitch_data) {
            $platform_data['id'] = $twitch_data->id;
            $platform_data['partner_id'] = $twitch_data->partner_id;
            $platform_data['name'] = $twitch_data->name;
            $platform_data['channel_id'] = smhDecrypt($twitch_data->channel_id);
            $platform_data['logo'] = $twitch_data->logo;
            $platform_data['access_token'] = smhDecrypt($twitch_data->access_token);
            $platform_data['refresh_token'] = smhDecrypt($twitch_data->refresh_token);
        }
        return $platform_data;
    }

    //Removes platform authorization and configuration from DB
    public function remove_platform_authorization($pid) {
        $platform_data = $this->getPlatformData($pid);
    }

}
