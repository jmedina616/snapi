<?php

namespace App\Libraries\SocialMedia;

use Illuminate\Support\Facades\Log;
use App\Exceptions\SmhAPIException;
use App\Libraries\SocialMedia\SocialMedia;

class TwitchClientApi implements SocialMedia {

    protected $OAUTH2_CLIENT_ID;
    protected $OAUTH2_CLIENT_SECRET;
    protected $REDIRECT_URI;

    public function __construct() {
        $this->OAUTH2_CLIENT_ID = env('TWITCH_CLIENT_ID');
        $this->OAUTH2_CLIENT_SECRET = env('TWITCH_OAUTH2_CLIENT_SECRET');
        $this->REDIRECT_URI = env('TWITCH_REDIRECT_URI');
    }

    public function getRedirectURL($user_data) {
        $state = $user_data->pid . "|" . $user_data->ks;
        $scope = 'channel_editor+channel_read+channel_stream+collections_edit+user_read';
        $authUrl = 'https://api.twitch.tv/kraken/oauth2/authorize?client_id=' . $this->OAUTH2_CLIENT_ID . '&state=' . $state . '&response_type=code&redirect_uri=' . $this->REDIRECT_URI . '&scope=' . $scope;
        return $authUrl;
    }

    public function checkAuthToken($pid, $token) {
        $success = array('isValid' => false);
        $url = 'https://api.twitch.tv/kraken';
        $response = $this->validateToken($url, $token['access_token']);
        if (isset($response['error'])) {
            if ($response['status'] == 401) {
                $new_access_token = $this->refreshToken($pid, $token);
                if ($new_access_token['success']) {
                    $success = array(
                        'isValid' => true,
                        'message' => 'new_access_token',
                        'access_token' => $new_access_token['new_token'],
                    );
                } else {
                    $success = array(
                        'isValid' => false,
                        'message' => 'Could not generate Twitch access token.',
                    );
                }
            }
        } else if ($response['token']['valid']) {
            $success = array(
                'isValid' => true,
                'message' => 'valid_access_token',
                'access_token' => $token,
            );
        }
        return $success;
    }

    public function refreshToken($pid, $token) {
        $success = array('success' => false);
        try {
            $url = 'https://api.twitch.tv/kraken/oauth2/token';
            $scope = 'channel_editor+channel_read+channel_stream+collections_edit+user_read';
            $data = array(
                'client_id' => $this->OAUTH2_CLIENT_ID,
                'client_secret' => $this->OAUTH2_CLIENT_SECRET,
                'grant_type' => 'refresh_token',
                'refresh_token' => $token['refresh_token'],
                'scope' => $scope,
            );
            $response = $this->makeRefreshTokenRequest($url, $data);
            $new_token = array(
                'access_token' => $response['access_token'],
                'refresh_token' => $response['refresh_token'],
            );
            $success = array('success' => true, 'new_token' => $new_token);
            return $success;
        } catch (Exception $e) {
            $error = array('Google', "Caught Twitch service Exception " . $e->getCode() . " message is " . $e->getMessage());
            throw new SmhAPIException('socail_media_api_error', $error);
        }
    }

    public function makeRefreshTokenRequest($url, $data) {
        $client = new \GuzzleHttp\Client(['http_errors' => false]);
        $options = [
            'form_params' => $data
        ];
        $request = $client->post($url, $options);
        $response = json_decode($request->getBody(), true);
        return $response;
    }

    public function validateToken($url, $access_token) {
        $client = new \GuzzleHttp\Client(['http_errors' => false]);
        $options = [
            'headers' => [
                'Accept' => 'application/vnd.twitchtv.v5+json',
                'Authorization' => 'OAuth ' . $access_token
            ]
        ];
        $request = $client->get($url, $options);
        $response = json_decode($request->getBody(), true);
        return $response;
    }

}
