<?php

namespace App;

//error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);

use Illuminate\Database\Eloquent\Model;

class TwitchChannel extends Model {
    
    protected $fillable = ['partner_id', 'name', 'channel_id', 'logo', 'access_token', 'refresh_token'];

    public function channelSettings() {
        return $this->hasOne('App\TwitchChannelSetting', 'partner_id', 'partner_id');
    }

}
