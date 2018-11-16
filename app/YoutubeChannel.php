<?php

namespace App;

//error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use App\Mylibs\GoogleClientApi;
use App\Exceptions\SmhAPIException;

class YoutubeChannel extends Model {

    //Build platform configuration object
    public function getPlatformConfig($pid, $ks, $projection) {
        $youtube = [];
        $channel_data_result = $this->getChannelData($pid);
        if (count($channel_data_result) > 0) {
            //Check if YouTube access token is valid
            $youtube_auth = $this->validateYoutubeToken($pid, $channel_data_result);
            $auth = ($youtube_auth['success']) ? true : false;
            if ($auth) {
                $channel_details = array('channel_title' => $channel_data_result['name'], 'channel_thumb' => $channel_data_result['thumbnail']);
                $settings = $this->getYoutubeSettings($pid, $youtube_auth, $channel_data_result);
                $youtube = $this->platformObject($auth, $channel_details, $settings, null);
            } else {
                $google_client_api = new GoogleClientApi();
                $youtube = $this->platformObject($auth, null, null, $google_client_api->getRedirectURL($pid, $ks, $projection));
            }
        } else {
            $google_client_api = new GoogleClientApi();
            $youtube = $this->platformObject(false, null, null, $google_client_api->getRedirectURL($pid, $ks, $projection));
        }

        return $youtube;
    }

    //Platform configuration object
    protected function platformObject($authorized = false, $channel_details = null, $settings = null, $redirect_url = null) {
        return (object) [
                    'platform' => 'youtube',
                    'authorized' => $authorized,
                    'channel_details' => $channel_details,
                    'settings' => $settings,
                    'redirect_url' => $redirect_url
        ];
    }

    //Get YouTube channel data
    protected function getChannelData($pid) {
        $data = array();
        $youtube_data = self::where('partner_id', '=', $pid)->first();
        if ($youtube_data) {
            $data['partner_id'] = $youtube_data->partner_id;
            $data['name'] = $youtube_data->name;
            $data['thumbnail'] = $youtube_data->thumbnail;
            $data['channel_id'] = smhDecrypt($youtube_data->channel_id);
            $data['is_verified'] = $youtube_data->is_verified;
            $data['ls_enabled'] = $youtube_data->is_enabled;
            $data['access_token'] = smhDecrypt($youtube_data->access_token);
            $data['refresh_token'] = smhDecrypt($youtube_data->refresh_token);
            $data['token_type'] = $youtube_data->token_type;
            $data['expires_in'] = $youtube_data->expires_in;
        }
        return $data;
    }

    protected function getYoutubeSettings($pid, $youtube_auth, $channel_data) {
        $settings = array('embed_status' => false, 'auto_upload' => false, 'projection' => 'rectangular');
        $settings_data = DB::table('youtube_channel_settings')->where('partner_id', '=', $pid)->first();
        if ($settings_data) {
            $is_verified = $this->getYoutubeVerificationStatus($pid, $youtube_auth['access_token'], $channel_data['is_verified']);
            $ls_enabled = $this->getYoutubeLsStatus($pid, $youtube_auth['access_token'], $channel_data['ls_enabled']);
            $embed_status = ($settings_data->embed) ? true : false;
            $auto_upload = ($settings_data->auto_upload) ? true : false;
            $projection = $settings_data->projection;
            $settings = array('is_verified' => $is_verified, 'ls_enabled' => $ls_enabled, 'embed_status' => $embed_status, 'auto_upload' => $auto_upload, 'projection' => $projection);
        }
        return $settings;
    }

    protected function validateYoutubeToken($pid, $token) {
        $success = array('success' => false);
        $google_client_api = new GoogleClientApi();
        $tokens_valid = $google_client_api->checkAuthToken($pid, $token);
        if ($tokens_valid['success']) {
            if ($tokens_valid['message'] == 'valid_access_token') {
                $success = array('success' => true, 'access_token' => $tokens_valid['access_token']);
            }
            if ($tokens_valid['message'] == 'new_access_token') {
                $access_token = $this->updateYoutubeTokens($pid, $tokens_valid['access_token']);
                if ($access_token['success']) {
                    $success = array('success' => true, 'access_token' => $tokens_valid['access_token']);
                }
            }
        } else {
            throw new SmhAPIException('google_api_error', $tokens_valid['message']);
        }
        return $success;
    }

    protected function updateYoutubeTokens($pid, $tokens) {
        $success = array('success' => false);
        $youtube_data = self::where('partner_id', '=', $pid)->first();
        if ($youtube_data) {
            $youtube_data->access_token = smhEncrypt($tokens['access_token']);
            $youtube_data->refresh_token = smhEncrypt($tokens['refresh_token']);
            $youtube_data->token_type = $tokens['token_type'];
            $youtube_data->expires_in = $tokens['expires_in'];

            if ($youtube_data->save()) {
                $success = array('success' => true);
            } else {
                //$success = array('success' => true, 'notice' => 'no changes were made');
                throw new SmhAPIException('internal_database_error', 'Could not update YouTube tokens for account \'' . $pid . '\'');
            }
        }

        return $success;
    }

    protected function getYoutubeVerificationStatus($pid, $access_token, $current_status) {
        if ($current_status == 'allowed') {
            $is_verified = true;
        } else {
            $updateYoutubeVerification = $this->updateYoutubeVerification($pid, $access_token);
            if ($updateYoutubeVerification['success']) {
                if ($updateYoutubeVerification['is_verified'] == 'allowed') {
                    $is_verified = true;
                } else {
                    $is_verified = false;
                }
            } else {
                $is_verified = false;
            }
        }
        return $is_verified;
    }

    protected function updateYoutubeVerification($pid, $access_token) {
        $success = array('success' => false);
        $google_client_api = new GoogleClientApi();
        $verification = $google_client_api->getVerification($access_token);
        if ($verification['success']) {
            $updateYoutubeChannelVerification = $this->updateYoutubeChannelVerification($pid, $verification['is_verified']);
            if ($updateYoutubeChannelVerification['success']) {
                $success = array('success' => true, 'is_verified' => $verification['is_verified']);
            }
        }
        return $success;
    }

    protected function updateYoutubeChannelVerification($pid, $is_verified) {
        $success = array('success' => false);
        $youtube_data = self::where('partner_id', '=', $pid)->first();
        if ($youtube_data) {
            $youtube_data->is_verified = $is_verified;

            if ($youtube_data->save()) {
                $success = array('success' => true);
            } else {
                //$success = array('success' => true, 'notice' => 'no changes were made');
                throw new SmhAPIException('internal_database_error', 'Could not update YouTube verification for account \'' . $pid . '\'');
            }
        }

        return $success;
    }

    protected function getYoutubeLsStatus($pid, $access_token, $current_status) {
        if ($current_status) {
            $ls_enabled = true;
        } else {
            $update_youtube_ls_enabled = $this->updateYoutubeLsStatus($pid, $access_token);
            if ($update_youtube_ls_enabled['success']) {
                if ($update_youtube_ls_enabled['ls_enabled']) {
                    $ls_enabled = true;
                } else {
                    $ls_enabled = false;
                }
            } else {
                $ls_enabled = false;
            }
        }
        return $ls_enabled;
    }

    protected function updateYoutubeLsStatus($pid, $access_token) {
        $success = array('success' => false);
        $isYoutubeLsEnabled = $this->isYoutubeLsEnabled($pid, $access_token);
        if ($isYoutubeLsEnabled['success']) {
            $updateYoutubeChannelLsStatus = $this->updateYoutubeChannelLsStatus($pid, true);
            if ($updateYoutubeChannelLsStatus['success']) {
                $success = array('success' => true, 'ls_enabled' => true);
            }
        } else {
            $updateYoutubeChannelLsStatus = $this->updateYoutubeChannelLsStatus($pid, false);
            if ($updateYoutubeChannelLsStatus['success']) {
                $success = array('success' => true, 'ls_enabled' => false);
            }
        }
        return $success;
    }

    protected function isYoutubeLsEnabled($pid, $access_token) {
        $success = array('success' => false);
        $google_client_api = new GoogleClientApi();
        $is_enabled = $google_client_api->isLsEnabled($access_token);
        if ($is_enabled['success']) {
            $success = array('success' => true);
        }
        return $success;
    }

    protected function updateYoutubeChannelLsStatus($pid, $ls_enabled) {
        $success = array('success' => false);
        $youtube_data = self::where('partner_id', '=', $pid)->first();
        if ($youtube_data) {
            $youtube_data->ls_enabled = $ls_enabled;

            if ($youtube_data->save()) {
                $success = array('success' => true);
            } else {
                //$success = array('success' => true, 'notice' => 'no changes were made');
                throw new SmhAPIException('internal_database_error', 'Could not update YouTube live stream status for account \'' . $pid . '\'');
            }
        }

        return $success;
    }

}
