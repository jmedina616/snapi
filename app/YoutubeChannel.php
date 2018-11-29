<?php

namespace App;

//error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);

use Illuminate\Database\Eloquent\Model;

class YoutubeChannel extends Model {
    
    protected $fillable = ['partner_id', 'name', 'channel_id', 'is_verified', 'ls_enabled', 'access_token', 'refresh_token', 'token_type', 'expires_in'];

    public function channelSettings(){
        return $this->hasOne('App\YoutubeChannelSetting', 'partner_id', 'partner_id');
    }

}
