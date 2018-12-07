<?php

namespace App\Libraries\SocialMedia;

use Illuminate\Support\Facades\Log;
use App\Exceptions\SmhAPIException;
use App\Libraries\SocialMedia\SocialMedia;

//Google API class
class GoogleClientApi implements SocialMedia {

    protected $OAUTH2_CLIENT_ID;
    protected $OAUTH2_CLIENT_SECRET;
    protected $REDIRECT_URL;

    public function __construct() {
        $this->OAUTH2_CLIENT_ID = env('GOOGLE_CLIENT_ID');
        $this->OAUTH2_CLIENT_SECRET = env('GOOGLE_OAUTH2_CLIENT_SECRET');
        $this->REDIRECT_URL = env('GOOGLE_REDIRECT_URI');
    }

    //Builds and returns the redirect URL
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
            $client->setState($user_data->pid . "|" . $user_data->ks);
            $authUrl = $client->createAuthUrl();
            return $authUrl;
        } catch (Google_Service_Exception $e) {
            throw new SmhAPIException('socail_media_api_error', "Caught Google service Exception " . $e->getCode() . " message is " . $e->getMessage());
        } catch (Exception $e) {
            throw new SmhAPIException('socail_media_api_error', "Caught Google service Exception " . $e->getCode() . " message is " . $e->getMessage());
        }
    }

    //Check if access token is valid, if not, use refresh token to generate new access token
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

            //If access token is valid, return the token
            if ($this->validateToken($token['access_token'])) {
                $success = array(
                    'isValid' => true,
                    'message' => 'valid_access_token',
                    'access_token' => $token,
                );
            } else {
                //If access token is not valid, use refresh token to get new access token
                $check_refresh_token = $client->refreshToken($token['refresh_token']);
                if (isset($check_refresh_token['error'])) {
                    $success = array(
                        'isValid' => false,
                        'message' => $check_refresh_token['error_description'],
                    );
                    Log::error('Something went wrong with the youtube refresh token: ' . $check_refresh_token['error_description']);
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
            Log::error('Something went wrong with checking if youtube access token is valid: ' . $e->getCode() . " message is " . $e->getMessage());
            $success = array('isValid' => false, 'message' => "Could not check if youtube access token is valid.");
            return $success;
        } catch (Exception $e) {
            Log::error('Something went wrong with checking if youtube access token is valid: ' . $e->getCode() . " message is " . $e->getMessage());
            $success = array('isValid' => false, 'message' => "Could not check if youtube access token is valid.");
            return $success;
        }
    }

    //Determines if access token is valid
    public function validateToken($token) {
        $valid = false;
        $client = new \GuzzleHttp\Client(['http_errors' => false]);
        $request = $client->get("https://www.googleapis.com/oauth2/v3/tokeninfo?access_token=" . $token);
        $response = json_decode($request->getBody());

        if (isset($response->aud)) {
            $valid = true;
        } else if (isset($response->error_description)) {
            $valid = false;
        }

        return $valid;
    }

    //Checks if channel is verified
    public function getVerificationStatus($pid, $access_token) {
        $status_result = array('status' => false);
        try {
            $client = new \Google_Client();
            $client->setClientId($this->OAUTH2_CLIENT_ID);
            $client->setClientSecret($this->OAUTH2_CLIENT_SECRET);
            $client->addScope('https://www.googleapis.com/auth/youtube');
            $redirect = filter_var($this->REDIRECT_URL, FILTER_SANITIZE_URL);
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
                    throw new SmhAPIException('socail_media_api_error', "Caught Google service Exception " . $e->getCode() . " message is " . $e->getMessage());
                } catch (Google_Exception $e) {
                    throw new SmhAPIException('socail_media_api_error', "Caught Google service Exception " . $e->getCode() . " message is " . $e->getMessage());
                }
            }
        } catch (Google_Service_Exception $e) {
            throw new SmhAPIException('socail_media_api_error', "Caught Google service Exception " . $e->getCode() . " message is " . $e->getMessage());
        } catch (Exception $e) {
            throw new SmhAPIException('socail_media_api_error', "Caught Google service Exception " . $e->getCode() . " message is " . $e->getMessage());
        }
    }

    //Checks if live streaming is enabled
    public function isLiveStreamEnabled($pid, $access_token) {
        $success = array('success' => false);
        try {
            $client = new \Google_Client();
            $client->setClientId($this->OAUTH2_CLIENT_ID);
            $client->setClientSecret($this->OAUTH2_CLIENT_SECRET);
            $client->addScope('https://www.googleapis.com/auth/youtube');
            $redirect = filter_var($this->REDIRECT_URL, FILTER_SANITIZE_URL);
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
                    throw new SmhAPIException('socail_media_api_error', "Caught Google service Exception " . $e->getCode() . " message is " . $e->getMessage());
                } catch (Google_Exception $e) {
                    throw new SmhAPIException('socail_media_api_error', "Caught Google service Exception " . $e->getCode() . " message is " . $e->getMessage());
                }
            }
        } catch (Google_Service_Exception $e) {
            throw new SmhAPIException('socail_media_api_error', "Caught Google service Exception " . $e->getCode() . " message is " . $e->getMessage());
        } catch (Exception $e) {
            throw new SmhAPIException('socail_media_api_error', "Caught Google service Exception " . $e->getCode() . " message is " . $e->getMessage());
        }
    }

    //Revokes access to youtube account
    public function removeAuthorization($access_token) {
        $success = array('success' => false);
        try {
            $client = new \Google_Client();
            $client->setClientId($this->OAUTH2_CLIENT_ID);
            $client->setClientSecret($this->OAUTH2_CLIENT_SECRET);
            $client->addScope('https://www.googleapis.com/auth/youtube');
            $redirect = filter_var($this->REDIRECT_URL, FILTER_SANITIZE_URL);
            $client->setRedirectUri($redirect);
            if ($client->revokeToken($access_token)) {
                $success = array('success' => true);
            } else {
                $success = array('success' => false, 'message' => 'YouTube: could not remove authorization');
            }
            return $success;
        } catch (Google_Service_Exception $e) {
            Log::info('Something went wrong with revoking youtube authorization: ' . $e->getCode() . " message is " . $e->getMessage());
            $success = array('success' => false, 'message' => "Could not remove youtube authorization.");
            return $success;
        } catch (Exception $e) {
            Log::info('Something went wrong with revoking youtube authorization: ' . $e->getCode() . " message is " . $e->getMessage());
            $success = array('success' => false, 'message' => "Could not remove youtube authorization.");
            return $success;
        }
    }

    //Retrieve channel data
    public function getChannelData($access_token) {
        $success = array('success' => false);
        try {
            $client = new \Google_Client();
            $client->setClientId($this->OAUTH2_CLIENT_ID);
            $client->setClientSecret($this->OAUTH2_CLIENT_SECRET);
            $client->addScope('https://www.googleapis.com/auth/youtube');
            $redirect = filter_var($this->REDIRECT_URL, FILTER_SANITIZE_URL);
            $client->setRedirectUri($redirect);
            $client->setAccessToken($access_token);

            $youtube = new \Google_Service_YouTube($client);
            if ($client->getAccessToken()) {
                try {
                    $channelResponse = $youtube->channels->listChannels('id,snippet,status', array(
                        'mine' => 'false'
                    ));

                    if (count($channelResponse['items']) >= 0) {
                        $title = $channelResponse['items'][0]['snippet']['title'];
                        $thumbnail = $channelResponse['items'][0]['snippet']['thumbnails']['high']['url'];
                        $channel_id = $channelResponse['items'][0]['id'];
                        $is_verified = $channelResponse['items'][0]['status']['longUploadsStatus'];
                        $success = array('success' => true, 'channel_title' => $title, 'channel_thumb' => $thumbnail, 'channel_id' => $channel_id, 'is_verified' => $is_verified);
                    } else {
                        $success = array('success' => false);
                    }
                    return $success;
                } catch (Google_Service_Exception $e) {
                    Log::info('Something went wrong with getting youtube channel data: ' . $e->getCode() . " message is " . $e->getMessage());
                    $success = array('success' => false, 'message' => "Could not get youtube channel data.");
                    return $success;
                } catch (Google_Exception $e) {
                    Log::info('Something went wrong with getting youtube channel data: ' . $e->getCode() . " message is " . $e->getMessage());
                    $success = array('success' => false, 'message' => "Could not get youtube channel data.");
                    return $success;
                }
            }
        } catch (Google_Service_Exception $e) {
            Log::info('Something went wrong with getting youtube channel data: ' . $e->getCode() . " message is " . $e->getMessage());
            $success = array('success' => false, 'message' => "Could not get youtube channel data.");
            return $success;
        } catch (Exception $e) {
            Log::info('Something went wrong with getting youtube channel data: ' . $e->getCode() . " message is " . $e->getMessage());
            $success = array('success' => false, 'message' => "Could not get youtube channel data.");
            return $success;
        }
    }

}
