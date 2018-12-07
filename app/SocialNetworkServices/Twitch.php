<?php

namespace App\SocialNetworkServices;

use App\TwitchChannel;
use App\TwitchChannelSetting;
use App\SocialNetworkServices\SocialNetwork;
use App\SocialNetworkServices\SocialPlatform;
use App\Libraries\SocialMedia\SocialMedia as SocialMediaAPI;
use App\Exceptions\SmhAPIException;
use Log;

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
            throw new SmhAPIException('socail_media_api_error', $token_validation['message']);
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
    public function removePlatformAuthorization($pid) {
        $platform_data = $this->getPlatformData($pid);
        if (count($platform_data) > 0) {
            //Check if the user's access token is still valid
            $authorized = $this->validateToken($pid, $platform_data);
            if ($authorized['isValid']) {
                //Revoke access to youtube account
                $removeAuthorization = $this->social_media_client_api->removeAuthorization($authorized['access_token']);
                if ($removeAuthorization['success']) {
                    $twitch = TwitchChannel::where('partner_id', '=', $pid)->first();
                    if ($twitch->delete()) {
                        return true;
                    } else {
                        throw new SmhAPIException('internal_database_error', 'Could not delete Twitch channel for account \'' . $pid . '\'');
                    }
                } else {
                    throw new SmhAPIException('socail_media_api_error', $removeAuthorization['message']);
                }
            } else {
                throw new SmhAPIException('account_not_found', $pid);
            }
        } else {
            throw new SmhAPIException('socail_media_api_error', 'Could not validate twitch access token.');
        }
    }

    //Resyncs platform data
    public function resyncAccount($user_data) {
        $success = array('success' => false);
        $platform_data = $this->getPlatformData($user_data->pid);
        if (count($platform_data) > 0) {
            //Check if the user's access token is still valid
            $authorized = $this->validateToken($user_data->pid, $platform_data);
            if ($authorized['isValid']) {
                //Get channel details from twitch
                $channel = $this->resyncChannelData($user_data->pid, $authorized['access_token']['access_token']);
                if ($channel['success']) {
                    //Update channel data in the DB
                    $update_channel = $this->updateChannelData($user_data->pid, $channel['channel_details']);
                    if ($update_channel) {
                        $success = array(
                        'channel_name' => $channel['channel_details']['channel_name'],
                        'channel_logo' => $channel['channel_details']['channel_logo']
                        );
                    }
                } else {
                    throw new SmhAPIException('socail_media_api_error', 'Could not get twitch channel data.');
                }
            } else {
                throw new SmhAPIException('socail_media_api_error', 'Could not validate twitch access token.');
            }
        } else {
            throw new SmhAPIException('account_not_found', $pid);
        }

        return $success;
    }

    //Get channel details from twitch
    public function resyncChannelData($pid, $access_token) {
        $success = array('success' => false);
        //Retrieve channel data from twitch
        $channel_data = $this->social_media_client_api->getChannelData($access_token);
        if ($channel_data['success']) {
            //Check live streaming status from twitch
            $channel_details = array('channel_name' => $channel_data['channel_name'], 'channel_logo' => $channel_data['channel_logo'], 'channel_id' => $channel_data['channel_id']);
            $success = array('success' => true, 'channel_details' => $channel_details);
        } else {
            throw new SmhAPIException('socail_media_api_error', $channel_data['message']);
        }
        return $success;
    }

    //Update channel data in DB
    public function updateChannelData($pid, $channel_data) {
        $success = false;
        $twitch_data = TwitchChannel::where('partner_id', '=', $pid)->first();
        if ($twitch_data) {
            $twitch_data->name = $channel_data['channel_name'];
            $twitch_data->channel_id = smhEncrypt($channel_data['channel_id']);
            $twitch_data->logo = $channel_data['channel_logo'];

            if ($twitch_data->save()) {
                $success = true;
            } else {
                throw new SmhAPIException('internal_database_error', 'Could not update Twitch channel data for account \'' . $pid . '\'');
            }
        }

        return $success;
    }

}
