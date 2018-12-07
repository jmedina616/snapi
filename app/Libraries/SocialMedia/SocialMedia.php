<?php

namespace App\Libraries\SocialMedia;

/**
 *
 */
interface SocialMedia {

    //Builds and returns the redirect URL
    public function getRedirectURL($user_data);

    //Check if access token is valid, if not, use refresh token to generate new access token
    public function checkAuthToken($pid, $token);

    //Revokes access to social media account
    public function removeAuthorization($access_token);

    //Retrieve channel data
    public function getChannelData($access_token);
}
