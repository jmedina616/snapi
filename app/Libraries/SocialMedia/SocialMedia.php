<?php

namespace App\Libraries\SocialMedia;
/**
 *
 */
interface SocialMedia
{
  public function getRedirectURL($user_data);
  public function checkAuthToken($pid, $token);
}