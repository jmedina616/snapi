<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class TwitchChannelSetting extends Model {
    
    protected $fillable = ['twitch_channel_id', 'auto_upload'];

    public function channel() {
        return $this->belongsTo('App\TwitchChannel', 'twitch_channel_id');
    }

}
