<?php

namespace App\SocialNetworkServices;

interface SocialNetwork {
  public function getConfiguration($user_data);
  public function getSettings($data);
  public function validateToken($pid, $token);
  public function getPlatformData($pid);
  public function removePlatformAuthorization($pid);
}
