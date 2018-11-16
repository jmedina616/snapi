<?php

namespace App;

//error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use App\Mylibs\TwitchClientApi;

class TwitchChannel extends Model {

    public function getPlatformConfig($pid, $ks) {
        $twitch = [];
        $channel_data_result = $this->getChannelData($pid);
        if (count($channel_data_result) > 0) {
            $twitch_auth = $this->validateTwitchToken($pid, $channel_data_result);
            $auth = ($twitch_auth['success']) ? true : false;
            if ($auth) {
                $settings = $this->getTwitchSettings($pid);
                $channel_details = array('channel_id' => $channel_data_result['channel_id'], 'channel_name' => $channel_data_result['name'], 'channel_logo' => $channel_data_result['logo']);
                $twitch = $this->platformObject($auth, $channel_details, $settings, null);
            } else {
                $twitch_api_client = new TwitchClientApi();
                $twitch = $this->platformObject($auth, null, null, $twitch_api_client->getRedirectURL($pid, $ks));
            }
        } else {
            $twitch_api_client = new TwitchClientApi();
            $twitch = $this->platformObject(false, null, null, $twitch_api_client->getRedirectURL($pid, $ks));
        }

        return $twitch;
    }

    //Platform configuration object
    protected function platformObject($authorized = false, $channel_details = null, $settings = null, $redirect_url = null) {
        return (object) [
                    'platform' => 'twitch',
                    'authorized' => $authorized,
                    'channel_details' => $channel_details,
                    'settings' => $settings,
                    'redirect_url' => $redirect_url
        ];
    }

    public function getChannelData($pid) {
        $data = array();
        $twitch_data = self::where('partner_id', '=', $pid)->first();
        if ($twitch_data) {
            $data['partner_id'] = $twitch_data->partner_id;
            $data['name'] = $twitch_data->name;
            $data['channel_id'] = smhDecrypt($twitch_data->channel_id);
            $data['logo'] = $twitch_data->logo;
            $data['access_token'] = smhDecrypt($twitch_data->access_token);
            $data['refresh_token'] = smhDecrypt($twitch_data->refresh_token);
        }
        return $data;
    }

    public function getTwitchSettings($pid) {
        $settings = array('auto_upload' => false);
        $settings_data = DB::table('twitch_channel_settings')->where('partner_id', '=', $pid)->first();
        if ($settings_data) {
            $auto_upload = ($settings_data->auto_upload) ? true : false;
            $settings = array('auto_upload' => $auto_upload);
        }
        return $settings;
    }

    public function validateTwitchToken($pid, $token) {
        $success = array('success' => false);
        $twitch_api_client = new TwitchClientApi();
        $tokens_valid = $twitch_api_client->checkAuthToken($pid, $token);
        if ($tokens_valid['success']) {
            if ($tokens_valid['message'] == 'valid_access_token') {
                $success = array('success' => true, 'access_token' => $tokens_valid['access_token']);
            }
            if ($tokens_valid['message'] == 'new_access_token') {
                $access_token = $this->updateTwitchTokens($pid, $tokens_valid);
                if ($access_token['success']) {
                    $success = array('success' => true, 'access_token' => $tokens_valid['access_token']);
                }
            }
        } else {
            throw new SmhAPIException('twitch_api_error', $tokens_valid['message']);
        }

        return $success;
    }

    public function updateTwitchTokens($pid, $tokens) {
        $success = array('success' => false);
        $twitch_data = self::where('partner_id', '=', $pid)->first();
        if ($twitch_data) {
            $twitch_data->access_token = smhEncrypt($tokens['access_token']);
            $twitch_data->refresh_token = smhEncrypt($tokens['refresh_token']);

            if ($twitch_data->save()) {
                $success = array('success' => true);
            } else {
                //$success = array('success' => true, 'notice' => 'no changes were made');
                throw new SmhAPIException('internal_database_error', 'Could not update Twitch tokens for account \'' . $pid . '\'');
            }
        }

        return $success;
    }

}
