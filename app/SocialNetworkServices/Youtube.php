<?php

namespace App\SocialNetworkServices;

use App\YoutubeChannel;
use App\YoutubeChannelSetting;
use App\SocialNetworkServices\SocialNetwork;
use App\SocialNetworkServices\SocialPlatform;
use App\Libraries\SocialMedia\SocialMedia as SocialMediaAPI;
use App\Exceptions\SmhAPIException;
use Log;

//Youtube service class
class Youtube extends SocialPlatform implements SocialNetwork {

    protected $social_media_client_api;
    protected $platform = 'youtube';

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
                $settings_obj = $this->createSettingsObject($user_data->pid, $authorized['access_token'], $platform_data);
                $settings = $this->getSettings($settings_obj);
                //Create platform object
                $platform = $this->createPlatformObject($this->platform, $authorized['isValid'], $channel_details, $settings, null);
                //Create platform object with redirect URL
            } else {
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
            'channel_title' => $platform_data['name'],
            'channel_thumb' => $platform_data['thumbnail'],
        );
    }

    //Creates settings object
    protected function createSettingsObject($pid, $access_token, $platform_data) {
        $settings_obj = new \stdClass();
        $settings_obj->pid = $pid;
        $settings_obj->access_token = $access_token;
        $settings_obj->platform_data = $platform_data;
        return $settings_obj;
    }

    //Build and return settings array
    public function getSettings($data) {
        //Default setttings
        $settings = array(
            'embed_status' => false,
            'auto_upload' => false,
            'projection' => 'rectangular',
        );
        $settings_data = YoutubeChannelSetting::where('youtube_channel_id', '=', $data->platform_data['id'])->first();
        if ($settings_data) {
            //Check if channel is verified
            $is_verified = $this->getVerificationStatus($data->pid, $data->access_token, $data->platform_data['is_verified']);
            //Check if live streaming is enabled on channel
            $ls_enabled = $this->getLiveStreamStatus($data->pid, $data->access_token, $data->platform_data['ls_enabled']);
            $embed_status = (bool) $settings_data->embed;
            $auto_upload = (bool) $settings_data->auto_upload;
            $projection = $settings_data->projection;
            $settings = array(
                'is_verified' => $is_verified,
                'ls_enabled' => $ls_enabled,
                'embed_status' => $embed_status,
                'auto_upload' => $auto_upload,
                'projection' => $projection,
            );
        }
        return $settings;
    }

    //Checks if youtube channel is verified
    protected function getVerificationStatus($pid, $access_token, $current_status) {
        $is_verified = false;
        if ($current_status == 'allowed') {
            $is_verified = true;
        } else {
            //Check verification status from youtube and update status in DB
            $verification_status_updated = $this->updateVerificationStatus($pid, $access_token);
            if ($verification_status_updated['success']) {
                if ($verification_status_updated['is_verified'] == 'allowed') {
                    $is_verified = true;
                }
            }
        }
        return $is_verified;
    }

    //Checks verification status from youtube and updates the status in the DB
    protected function updateVerificationStatus($pid, $access_token) {
        $update_status = array(
            'success' => false,
        );
        //Gets verification status from youtube
        $verification = $this->social_media_client_api->getVerificationStatus($access_token);
        if ($verification['status']) {
            //Update verification status in the DB
            $update_verification = $this->updateDbVerificationStatus($pid, $verification['is_verified']);
            if ($update_verification) {
                $update_status = array(
                    'success' => true,
                    'is_verified' => $verification['is_verified'],
                );
            }
        }
        return $update_status;
    }

    //Updates verification status in DB
    protected function updateDbVerificationStatus($pid, $is_verified) {
        $success = false;
        $youtube_data = YoutubeChannel::where('partner_id', '=', $pid)->first();
        if ($youtube_data) {
            $youtube_data->is_verified = $is_verified;
            if ($youtube_data->save()) {
                $success = true;
            } else {
                throw new SmhAPIException('internal_database_error', 'Could not update YouTube verification for account \'' . $pid . '\'');
            }
        }

        return $success;
    }

    //Checks if access token is valid, if not, use refresh token to generate a new access token
    public function validateToken($pid, $token) {
        //Default settings
        $validation_result = array(
            'isValid' => false,
        );
        //Check if access token is valid from youtube
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
                if ($access_token) {
                    $validation_result = array(
                        'isValid' => true,
                        'access_token' => $token_validation['access_token'],
                    );
                }
            }
        } else {
            throw new SmhAPIException('socail_media_api_error', $$token_validation['message']);
        }
        return $validation_result;
    }

    //Updates token in the DB
    protected function updateDbTokens($pid, $tokens) {
        $success = false;
        $youtube_data = YoutubeChannel::where('partner_id', '=', $pid)->first();
        if ($youtube_data) {
            $youtube_data->access_token = smhEncrypt($tokens['access_token']);
            $youtube_data->refresh_token = smhEncrypt($tokens['refresh_token']);
            $youtube_data->token_type = $tokens['token_type'];
            $youtube_data->expires_in = $tokens['expires_in'];

            if ($youtube_data->save()) {
                $success = true;
            } else {
                throw new SmhAPIException('internal_database_error', 'Could not update YouTube tokens for account \'' . $pid . '\'');
            }
        }

        return $success;
    }

    //Builds and returns platform data from DB
    public function getPlatformData($pid) {
        $platform_data = array();
        $youtube_data = YoutubeChannel::where('partner_id', '=', $pid)->first();
        if ($youtube_data) {
            $platform_data['id'] = $youtube_data->id;
            $platform_data['partner_id'] = $youtube_data->partner_id;
            $platform_data['name'] = $youtube_data->name;
            $platform_data['thumbnail'] = $youtube_data->thumbnail;
            $platform_data['channel_id'] = smhDecrypt($youtube_data->channel_id);
            $platform_data['is_verified'] = $youtube_data->is_verified;
            $platform_data['ls_enabled'] = $youtube_data->ls_enabled;
            $platform_data['access_token'] = smhDecrypt($youtube_data->access_token);
            $platform_data['refresh_token'] = smhDecrypt($youtube_data->refresh_token);
            $platform_data['token_type'] = $youtube_data->token_type;
            $platform_data['expires_in'] = $youtube_data->expires_in;
        }
        return $platform_data;
    }

    //Checks live streaming status from youtube
    protected function getLiveStreamStatus($pid, $access_token, $current_status) {
        $ls_enabled = false;
        if ($current_status) {
            $ls_enabled = true;
        } else {
            //Update live streaming status in DB
            $update_status = $this->updateLiveStreamStatus($pid, $access_token);
            if ($update_status) {
                $ls_enabled = true;
            }
        }
        return $ls_enabled;
    }

    //Gets live streaming status from youtube and updates the status in the DB
    protected function updateLiveStreamStatus($pid, $access_token) {
        $success = false;
        //Check live streaming status from youtube
        $live_stream = $this->isLiveStreamEnabled($pid, $access_token);
        if ($live_stream) {
            //Update live streaming status in DB
            $update_status = $this->updateDbLiveStreamStatus($pid, true);
            if ($update_status) {
                $success = true;
            }
        } else {
            //Update live streaming status in DB
            $update_status = $this->updateDbLiveStreamStatus($pid, false);
            if ($update_status) {
                $success = true;
            }
        }
        return $success;
    }

    //Checks if live streaming is enabled from youtube
    protected function isLiveStreamEnabled($pid, $access_token) {
        $enabled = false;
        //Check live streaming status from youtube
        $ls_enabled = $this->social_media_client_api->isLiveStreamEnabled($access_token);
        if ($ls_enabled) {
            $enabled = true;
        }
        return $success;
    }

    //Updates live streaming status in DB
    protected function updateDbLiveStreamStatus($pid, $ls_enabled) {
        $success = false;
        $youtube_data = YoutubeChannel::where('partner_id', '=', $pid)->first();
        if ($youtube_data) {
            $youtube_data->ls_enabled = $ls_enabled;

            if ($youtube_data->save()) {
                $success = true;
            } else {
                throw new SmhAPIException('internal_database_error', 'Could not update YouTube live stream status for account \'' . $pid . '\'');
            }
        }

        return $success;
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
                    $youtube = YoutubeChannel::where('partner_id', '=', $pid)->first();
                    if ($youtube->delete()) {
                        return 'true';
                    } else {
                        throw new SmhAPIException('internal_database_error', 'Could not delete YouTube channel for account \'' . $pid . '\'');
                    }
                } else {
                    throw new SmhAPIException('socail_media_api_error', $removeAuthorization['message']);
                }
            } else {
                throw new SmhAPIException('socail_media_api_error', 'Could not validate youtube access token.');
            }
        } else {
            throw new SmhAPIException('account_not_found', $pid);
        }
    }

}
