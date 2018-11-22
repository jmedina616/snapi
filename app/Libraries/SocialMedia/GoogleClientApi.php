<?php

namespace App\Libraries\SocialMedia;

use Illuminate\Support\Facades\Log;
use App\Exceptions\SmhAPIException;
use App\Libraries\SocialMedia\SocialMedia;

class GoogleClientApi implements SocialMedia {

    protected $OAUTH2_CLIENT_ID;
    protected $OAUTH2_CLIENT_SECRET;
    protected $REDIRECT_URL;

    public function __construct() {
        $this->OAUTH2_CLIENT_ID = env('GOOGLE_CLIENT_ID');
        $this->OAUTH2_CLIENT_SECRET = env('GOOGLE_OAUTH2_CLIENT_SECRET');
        $this->REDIRECT_URL = env('GOOGLE_REDIRECT_URI');
    }

    public function getRedirectURL($user_data) {
        try {
            $client = new \Google_Client();
            $client->setClientId($this->OAUTH2_CLIENT_ID);
            $client->setClientSecret($this->OAUTH2_CLIENT_SECRET);
            $client->addScope('https://www.googleapis.com/auth/youtube');
            $client->setAccessType("offline");
            $client->setApprovalPrompt('force');
            $redirect = filter_var($this->REDIRECT_URL, FILTER_SANITIZE_URL);
            $client->setRedirectUri($redirect);
            $client->setState($user_data->pid . "|" . $user_data->ks . "|" . $user_data->projection);
            $authUrl = $client->createAuthUrl();
            return $authUrl;
        } catch (Google_Service_Exception $e) {
            $error = array('Google', "Caught Google service Exception " . $e->getCode() . " message is " . $e->getMessage());
            throw new SmhAPIException('socail_media_api_error', $error);
        } catch (Exception $e) {
            $error = array('Google', "Caught Google service Exception " . $e->getCode() . " message is " . $e->getMessage());
            throw new SmhAPIException('socail_media_api_error', $error);
        }
    }

    public function checkAuthToken($pid, $token) {
        $success = array('isValid' => false);
        try {
            $client = new \Google_Client();
            $client->setClientId($this->OAUTH2_CLIENT_ID);
            $client->setClientSecret($this->OAUTH2_CLIENT_SECRET);
            $client->addScope('https://www.googleapis.com/auth/youtube');
            $client->setAccessType("offline");
            $client->setApprovalPrompt('auto');
            $redirect = filter_var($this->REDIRECT_URL, FILTER_SANITIZE_URL);
            $client->setRedirectUri($redirect);
            $client->setAccessToken($token);

            if ($this->validateToken($token['access_token'])) {
                $success = array(
                  'isValid' => true,
                  'message' => 'valid_access_token',
                  'access_token' => $token,
                );
            } else {
                $check_refresh_token = $client->refreshToken($token['refresh_token']);
                if (isset($check_refresh_token['error'])) {
                    $success = array(
                      'isValid' => false,
                      'message' => $check_refresh_token['error_description'],
                    );
                } else {
                    $new_access_token = $client->getAccessToken();
                    $success = array(
                      'isValid' => true,
                      'message' => 'new_access_token',
                      'access_token' => $new_access_token,
                    );
                }
            }
            return $success;
        } catch (Google_Service_Exception $e) {
            $success = array('isValid' => false, 'message' => "Caught Google service Exception " . $e->getCode() . " message is " . $e->getMessage());
            return $success;
        } catch (Exception $e) {
            $success = array('isValid' => false, 'message' => "Caught Google service Exception " . $e->getCode() . " message is " . $e->getMessage());
            return $success;
        }
    }

    public function validateToken($token) {
        $valid = false;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://www.googleapis.com/oauth2/v3/tokeninfo?access_token=" . $token);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $response = json_decode(curl_exec($ch));
        curl_close($ch);

        if (isset($response->aud)) {
            $valid = true;
        } else if (isset($response->error_description)) {
            $valid = false;
        }

        return $valid;
    }

    public function getVerificationStatus($pid, $access_token) {
        $status_result = array('status' => false);
        try {
            $client = new \Google_Client();
            $client->setClientId($this->OAUTH2_CLIENT_ID);
            $client->setClientSecret($this->OAUTH2_CLIENT_SECRET);
            $client->addScope('https://www.googleapis.com/auth/youtube');
            $redirect = filter_var($this->$REDIRECT_URL, FILTER_SANITIZE_URL);
            $client->setRedirectUri($redirect);
            $client->setAccessToken($access_token);

            $youtube = new \Google_Service_YouTube($client);
            if ($client->getAccessToken()) {
                try {
                    $channelResponse = $youtube->channels->listChannels('id,snippet,status', array(
                        'mine' => 'false'
                    ));

                    if (count($channelResponse['items']) >= 0) {
                        $is_verified = $channelResponse['items'][0]['status']['longUploadsStatus'];
                        $status_result = array(
                          'status' => true,
                          'is_verified' => $is_verified,
                        );
                    } else {
                        $status_result = array(
                          'status' => false,
                          'message' => 'Channel is not verified.',
                        );
                    }
                    return $status_result;
                } catch (Google_Service_Exception $e) {
                    $error = array('Google', "Caught Google service Exception " . $e->getCode() . " message is " . $e->getMessage());
                    throw new SmhAPIException('socail_media_api_error', $error);
                } catch (Google_Exception $e) {
                    $error = array('Google', "Caught Google service Exception " . $e->getCode() . " message is " . $e->getMessage());
                    throw new SmhAPIException('socail_media_api_error', $error);
                }
            }
        } catch (Google_Service_Exception $e) {
            $error = array('Google', "Caught Google service Exception " . $e->getCode() . " message is " . $e->getMessage());
            throw new SmhAPIException('socail_media_api_error', $error);
        } catch (Exception $e) {
            $error = array('Google', "Caught Google service Exception " . $e->getCode() . " message is " . $e->getMessage());
            throw new SmhAPIException('socail_media_api_error', $error);
        }
    }

    public function isLiveStreamEnabled($pid, $access_token) {
        $success = array('success' => false);
        try {
            $client = new \Google_Client();
            $client->setClientId($this->OAUTH2_CLIENT_ID);
            $client->setClientSecret($this->OAUTH2_CLIENT_SECRET);
            $client->addScope('https://www.googleapis.com/auth/youtube');
            $redirect = filter_var($this->$REDIRECT_URL, FILTER_SANITIZE_URL);
            $client->setRedirectUri($redirect);
            $client->setAccessToken($access_token);

            $youtube = new \Google_Service_YouTube($client);
            if ($client->getAccessToken()) {
                try {
                    $broadcastsResponse = $youtube->liveBroadcasts->listLiveBroadcasts('snippet,contentDetails,status', array(
                        'mine' => 'false',
                        'maxResults' => 50
                    ));

                    if (count($broadcastsResponse['items']) >= 0) {
                        $success = array(
                          'success' => true,
                        );
                    } else {
                        $success = array(
                          'success' => false,
                        );
                    }
                    return $success;
                } catch (Google_Service_Exception $e) {
                    $error = array('Google', "Caught Google service Exception " . $e->getCode() . " message is " . $e->getMessage());
                    throw new SmhAPIException('socail_media_api_error', $error);
                } catch (Google_Exception $e) {
                    $error = array('Google', "Caught Google service Exception " . $e->getCode() . " message is " . $e->getMessage());
                    throw new SmhAPIException('socail_media_api_error', $error);
                }
            }
        } catch (Google_Service_Exception $e) {
            $error = array('Google', "Caught Google service Exception " . $e->getCode() . " message is " . $e->getMessage());
            throw new SmhAPIException('socail_media_api_error', $error);
        } catch (Exception $e) {
            $error = array('Google', "Caught Google service Exception " . $e->getCode() . " message is " . $e->getMessage());
            throw new SmhAPIException('socail_media_api_error', $error);
        }
    }

}
