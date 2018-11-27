<?php

namespace App\SocialNetworkServices;

use App\TwitchChannel;
use App\TwitchChannelSetting;
use App\SocialNetworkServices\SocialNetwork;
use App\SocialNetworkServices\SocialPlatform;
use App\Libraries\SocialMedia\SocialMedia as SocialMediaAPI;
use App\Exceptions\SmhAPIException;

/**
 *
 */
class Twitch extends SocialPlatform implements SocialNetwork {

  protected $social_media_client_api;
  protected $platform = 'twitch';

  public function __construct(SocialMediaAPI $social_media_client_api){
    $this->social_media_client_api = $social_media_client_api;
  }

  public function getConfiguration($user_data){
    $platform = new \stdClass();
    $platform_data = $this->getPlatformData($user_data->pid);

    if (count($platform_data) > 0) {
      $authorized = $this->validateToken($user_data->pid, $platform_data);
      if ($authorized['isValid']) {
        $channel_details = array(
          'channel_id' => $platform_data['channel_id'],
          'channel_name' => $platform_data['name'],
          'channel_logo' => $platform_data['logo'],
         );

        $settings_obj = $this->createSettingsObject($user_data->pid);

        $settings = $this->getSettings($settings_obj);
        $platform = $this->createPlatformObject(
          $this->platform,
          $authorized['isValid'],
          $channel_details,
          $settings,
          null
        );
      } else {
        $platform = $this->createPlatformObject(
          $this->platform,
          $authorized['isValid'],
          null,
          null,
          $this->social_media_client_api->getRedirectURL($user_data)
        );
      }
    } else {
      $platform = $this->createPlatformObject(
        $this->platform,
        false,
        null,
        null,
        $this->social_media_client_api->getRedirectURL($user_data)
      );
    }

    return $platform;
  }

  protected function createSettingsObject($pid){
    $settings_obj = new \stdClass();
    $settings_obj->pid = $pid;
    return $settings_obj;
  }

  public function getSettings($data){
    $settings = array(
      'auto_upload' => false,
    );
    $settings_data = TwitchChannelSetting::where('partner_id', '=', $data->pid)->first();
    if ($settings_data) {
        $auto_upload = (bool) $settings_data->auto_upload;
        $settings = array(
          'auto_upload' => $auto_upload,
          );
    }
    return $settings;
  }

  public function validateToken($pid, $token){
    $validation_result = array(
      'isValid' => false,
    );
    $token_validation = $this->social_media_client_api->checkAuthToken($pid, $token);
    if ($token_validation['isValid']) {
        if ($token_validation['message'] == 'valid_access_token') {
            $validation_result = array(
              'isValid' => true,
              'access_token' => $token_validation['access_token'],
            );
        }
        if ($token_validation['message'] == 'new_access_token') {
            $access_token = $this->updateDbTokens($pid, $token_validation['access_token']);
            if ($access_token['success']) {
                $validation_result = array(
                  'isValid' => true,
                  'access_token' => $token_validation['access_token'],
                );
            }
        }
    } else {
        $error = array('Twitch',$token_validation['message']);
        throw new SmhAPIException('socail_media_api_error', $error);
    }
    return $validation_result;
  }

  protected function updateDbTokens($pid, $tokens) {
      $success = false;
      $twitch_data = TwitchChannel::where('partner_id', '=', $pid)->first();
      if ($twitch_data) {
          $twitch_data->access_token = smhEncrypt($tokens['access_token']);
          $twitch_data->refresh_token = smhEncrypt($tokens['refresh_token']);

          if ($twitch_data->save()) {
              $success = true;
          } else {
              throw new SmhAPIException('internal_database_error', 'Could not update Twitch tokens for account \'' . $pid . '\'');
          }
      }

      return $success;
  }

  public function getPlatformData($pid) {
      $platform_data = array();
      $twitch_data = TwitchChannel::where('partner_id', '=', $pid)->first();
      if ($twitch_data) {
        $platform_data['partner_id'] = $twitch_data->partner_id;
        $platform_data['name'] = $twitch_data->name;
        $platform_data['channel_id'] = smhDecrypt($twitch_data->channel_id);
        $platform_data['logo'] = $twitch_data->logo;
        $platform_data['access_token'] = smhDecrypt($twitch_data->access_token);
        $platform_data['refresh_token'] = smhDecrypt($twitch_data->refresh_token);
      }
      return $platform_data;
  }


}
