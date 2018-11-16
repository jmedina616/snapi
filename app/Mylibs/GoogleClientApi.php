<?php

namespace App\Mylibs;

use Illuminate\Support\Facades\Log;
use App\Exceptions\SmhAPIException;

class GoogleClientApi {

    protected $OAUTH2_CLIENT_ID;
    protected $OAUTH2_CLIENT_SECRET;
    protected $REDIRECT_URL;

    public function __construct() {
        $this->OAUTH2_CLIENT_ID = '625514053094-0rdhl4tub0dn2kd4edk9onfcd38i1uci.apps.googleusercontent.com';
        $this->OAUTH2_CLIENT_SECRET = 'o9fEzEUdCq_mXLMGDMHboE6m';
        $this->REDIRECT_URL = 'http://devplatform.streamingmediahosting.com/apps/sn/v1.0/oauth2callback.php';
    }

    public function getRedirectURL($pid, $ks, $projection) {
        try {
            $client = new \Google_Client();
            $client->setClientId($this->OAUTH2_CLIENT_ID);
            $client->setClientSecret($this->OAUTH2_CLIENT_SECRET);
            $client->addScope('https://www.googleapis.com/auth/youtube');
            $client->setAccessType("offline");
            $client->setApprovalPrompt('force');
            $redirect = filter_var($this->REDIRECT_URL, FILTER_SANITIZE_URL);
            $client->setRedirectUri($redirect);
            $client->setState($pid . "|" . $ks . "|" . $projection);
            $authUrl = $client->createAuthUrl();
            return $authUrl;
        } catch (Google_Service_Exception $e) {
            throw new SmhAPIException('google_api_error', "Caught Google service Exception " . $e->getCode() . " message is " . $e->getMessage());
        } catch (Exception $e) {
            throw new SmhAPIException('google_api_error', "Caught Google service Exception " . $e->getCode() . " message is " . $e->getMessage());
        }
    }

    public function checkAuthToken($pid, $token) {
        $success = array('success' => false);
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
                $success = array('success' => true, 'message' => 'valid_access_token', 'access_token' => $token);
            } else {
                $check_refresh_token = $client->refreshToken($token['refresh_token']);
                if (isset($check_refresh_token['error'])) {
                    $success = array('success' => false, 'message' => $check_refresh_token['error_description']);
                } else {
                    $new_access_token = $client->getAccessToken();
                    $success = array('success' => true, 'message' => 'new_access_token', 'access_token' => $new_access_token);
                }
            }
            return $success;
        } catch (Google_Service_Exception $e) {
            $success = array('success' => false, 'message' => "Caught Google service Exception " . $e->getCode() . " message is " . $e->getMessage());
            return $success;
        } catch (Exception $e) {
            $success = array('success' => false, 'message' => "Caught Google service Exception " . $e->getCode() . " message is " . $e->getMessage());
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

    public function getVerification($pid, $access_token) {
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
                    $channelResponse = $youtube->channels->listChannels('id,snippet,status', array(
                        'mine' => 'false'
                    ));

                    if (count($channelResponse['items']) >= 0) {
                        $is_verified = $channelResponse['items'][0]['status']['longUploadsStatus'];
                        $success = array('success' => true, 'is_verified' => $is_verified);
                    } else {
                        $success = array('success' => false, 'message' => 'Channel is not verified.');
                    }
                    return $success;
                } catch (Google_Service_Exception $e) {
                    throw new SmhAPIException('google_api_error', "Caught Google service Exception " . $e->getCode() . " message is " . $e->getMessage());
                } catch (Google_Exception $e) {
                    throw new SmhAPIException('google_api_error', "Caught Google service Exception " . $e->getCode() . " message is " . $e->getMessage());
                }
            }
        } catch (Google_Service_Exception $e) {
            throw new SmhAPIException('google_api_error', "Caught Google service Exception " . $e->getCode() . " message is " . $e->getMessage());
        } catch (Exception $e) {
            throw new SmhAPIException('google_api_error', "Caught Google service Exception " . $e->getCode() . " message is " . $e->getMessage());
        }
    }

    public function isLsEnabled($pid, $access_token) {
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
                        $success = array('success' => true);
                    } else {
                        $success = array('success' => false);
                    }
                    return $success;
                } catch (Google_Service_Exception $e) {
                    throw new SmhAPIException('google_api_error', "Caught Google service Exception " . $e->getCode() . " message is " . $e->getMessage());
                } catch (Google_Exception $e) {
                    throw new SmhAPIException('google_api_error', "Caught Google service Exception " . $e->getCode() . " message is " . $e->getMessage());
                }
            }
        } catch (Google_Service_Exception $e) {
            throw new SmhAPIException('google_api_error', "Caught Google service Exception " . $e->getCode() . " message is " . $e->getMessage());
        } catch (Exception $e) {
            throw new SmhAPIException('google_api_error', "Caught Google service Exception " . $e->getCode() . " message is " . $e->getMessage());
        }
    }

}
