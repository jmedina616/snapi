<?php

namespace App\SocialNetworkServices;

interface SocialNetwork {

    //Builds and returns the platform configuration object
    public function getConfiguration($user_data);

    //Build and return settings array
    public function getSettings($data);

    //Checks if access token is valid, if not, use refresh token to generate a new access token
    public function validateToken($pid, $token);

    //Builds and returns platform data from DB
    public function getPlatformData($pid);

    //Removes platform authorization and configuration from DB
    public function removePlatformAuthorization($pid);

    //Resyncs platform data
    public function resyncAccount($user_data);
}
