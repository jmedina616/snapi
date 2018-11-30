<?php

namespace App\SocialNetworkServices;

abstract class SocialPlatform {

    //Creates the platform object
      protected function createPlatformObject ($platform = null, $authorized = false, $channel_details = null, $settings = null, $redirect_url = null){
        $platform_obj = new \stdClass();
        if($authorized){
          $platform_obj->platform = $platform;
          $platform_obj->authorized = $authorized;
          $platform_obj->channel_details = $channel_details;
          $platform_obj->settings = $settings;
        } else {
          $platform_obj->platform = $platform;
          $platform_obj->authorized = $authorized;
          $platform_obj->redirect_url = $redirect_url;
        }
        return $platform_obj;
      }

}
