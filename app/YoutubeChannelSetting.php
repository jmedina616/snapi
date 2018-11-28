<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class YoutubeChannelSetting extends Model
{
    public function channel(){
        return $this->belongsTo('App\YoutubeChannel', 'partner_id', 'partner_id');
    }
}
