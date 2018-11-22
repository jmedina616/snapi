<?php

namespace App\SocialNetworkServices;

use App\YoutubeChannel;
use App\YoutubeChannelSetting;
use App\SocialNetworkServices\SocialNetwork;
use App\SocialNetworkServices\SocialPlatform;
use App\Libraries\SocialMedia\GoogleClientApi;
use App\Exceptions\SmhAPIException;

/**
 *
 */
class Youtube extends SocialPlatform implements SocialNetwork {

  protected $social_media_client_api;
  protected $platform = 'youtube';

  public function __construct(GoogleClientApi $social_media_client_api){
    $this->social_media_client_api = $social_media_client_api;
  }

  public function getConfiguration($user_data){
    $platform = new \stdClass();
    $platform_data = $this->getPlatformData($user_data->pid);

    if (count($platform_data) > 0) {
      $authorized = $this->validateToken($user_data->pid, $platform_data);
      if ($authorized['isValid']) {
        $channel_details = array(
          'channel_title' => $platform_data['name'],
          'channel_thumb' => $platform_data['thumbnail'],
         );

        $settings_obj = $this->createSettingsObject($user_data->pid,$authorized['access_token'],$platform_data);

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

  protected function createSettingsObject($pid, $access_token, $platform_data){
    $settings_obj = new \stdClass();
    $settings_obj->pid = $pid;
    $settings_obj->access_token = $access_token;
    $settings_obj->platform_data = $platform_data;
    return $settings_obj;
  }

  public function getSettings($data){
    $settings = array(
      'embed_status' => false,
      'auto_upload' => false,
      'projection' => 'rectangular',
    );
    $settings_data = YoutubeChannelSetting::where('partner_id', '=', $data->pid)->first();
    if ($settings_data) {
        $is_verified = $this->getVerificationStatus($data->pid, $data->access_token, $data->platform_data['is_verified']);
        $ls_enabled = $this->getLiveStreamStatus($data->pid, $data->access_token, $data->platform_data['ls_enabled']);
        $embed_status = (bool) $settings_data->embed;
        $auto_upload = (bool) $settings_data->auto_upload;
        $projection = $settings_data->projection;
        $settings = array(
          'is_verified' => $is_verified,
          'ls_enabled' => $ls_enabled,
          'embed_status' => $embed_status,
          'auto_upload' => $auto_upload,
          'projection' => $projection,
          );
    }
    return $settings;
  }

  protected function getVerificationStatus($pid, $access_token, $current_status) {
      $is_verified = false;
      if ($current_status == 'allowed') {
          $is_verified = true;
      } else {
          $verification_status_updated = $this->updateVerificationStatus($pid, $access_token);
          if ($verification_status_updated['success']) {
              if ($verification_status_updated['is_verified'] == 'allowed') {
                  $is_verified = true;
              }
          }
      }
      return $is_verified;
  }

  protected function updateVerificationStatus($pid, $access_token) {
      $update_status = array(
        'success' => false,
      );
      $verification = $this->social_media_client_api->getVerificationStatus($access_token);
      if ($verification['status']) {
          $update_verification = $this->updateDbVerificationStatus($pid, $verification['is_verified']);
          if ($update_verification) {
              $update_status = array(
                'success' => true,
                'is_verified' => $verification['is_verified'],
              );
          }
      }
      return $update_status;
  }

  protected function updateDbVerificationStatus($pid, $is_verified) {
      $success = false;
      $youtube_data = YoutubeChannel::where('partner_id', '=', $pid)->first();
      if ($youtube_data) {
          $youtube_data->is_verified = $is_verified;
          if ($youtube_data->save()) {
              $success = true;
          } else {
              throw new SmhAPIException('internal_database_error', 'Could not update YouTube verification for account \'' . $pid . '\'');
          }
      }

      return $success;
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
        $error = array('Google', $token_validation['message']);
        throw new SmhAPIException('socail_media_api_error', $error);
    }
    return $validation_result;
  }

  protected function updateDbTokens($pid, $tokens) {
      $success = false;
      $youtube_data = YoutubeChannel::where('partner_id', '=', $pid)->first();
      if ($youtube_data) {
          $youtube_data->access_token = smhEncrypt($tokens['access_token']);
          $youtube_data->refresh_token = smhEncrypt($tokens['refresh_token']);
          $youtube_data->token_type = $tokens['token_type'];
          $youtube_data->expires_in = $tokens['expires_in'];

          if ($youtube_data->save()) {
              $success = true;
          } else {
              throw new SmhAPIException('internal_database_error', 'Could not update YouTube tokens for account \'' . $pid . '\'');
          }
      }

      return $success;
  }

  public function getPlatformData($pid) {
      $platform_data = array();
      $youtube_data = YoutubeChannel::where('partner_id', '=', $pid)->first();
      if ($youtube_data) {
          $platform_data['partner_id'] = $youtube_data->partner_id;
          $platform_data['name'] = $youtube_data->name;
          $platform_data['thumbnail'] = $youtube_data->thumbnail;
          $platform_data['channel_id'] = smhDecrypt($youtube_data->channel_id);
          $platform_data['is_verified'] = $youtube_data->is_verified;
          $platform_data['ls_enabled'] = $youtube_data->is_enabled;
          $platform_data['access_token'] = smhDecrypt($youtube_data->access_token);
          $platform_data['refresh_token'] = smhDecrypt($youtube_data->refresh_token);
          $platform_data['token_type'] = $youtube_data->token_type;
          $platform_data['expires_in'] = $youtube_data->expires_in;
      }
      return $platform_data;
  }

  protected function getLiveStreamStatus($pid, $access_token, $current_status) {
      $ls_enabled = false;
      if ($current_status) {
          $ls_enabled = true;
      } else {
          $update_status = $this->updateLiveStreamStatus($pid, $access_token);
          if ($update_status) {
            $ls_enabled = true;
          }
      }
      return $ls_enabled;
  }

  protected function updateLiveStreamStatus($pid, $access_token) {
      $success = false;
      $live_stream = $this->isLiveStreamEnabled($pid, $access_token);
      if ($live_stream) {
          $update_status = $this->updateDbLiveStreamStatus($pid, true);
          if ($update_status) {
              $success = true;
          }
      } else {
          $update_status = $this->updateDbLiveStreamStatus($pid, false);
          if ($update_status) {
              $success = true;
          }
      }
      return $success;
  }

  protected function isLiveStreamEnabled($pid, $access_token) {
      $enabled = false;
      $is_enabled = $this->social_media_client_api->isLiveStreamEnabled($access_token);
      if ($is_enabled) {
          $enabled = true;
      }
      return $success;
  }

  protected function updateDbLiveStreamStatus($pid, $ls_enabled) {
      $success = false;
      $youtube_data = self::where('partner_id', '=', $pid)->first();
      if ($youtube_data) {
          $youtube_data->ls_enabled = $ls_enabled;

          if ($youtube_data->save()) {
              $success = true;
          } else {
              throw new SmhAPIException('internal_database_error', 'Could not update YouTube live stream status for account \'' . $pid . '\'');
          }
      }

      return $success;
  }

}
