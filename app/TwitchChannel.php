<?php

namespace App;

//error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);

use Illuminate\Database\Eloquent\Model;

class TwitchChannel extends Model {

    public function channelSettings() {
        return $this->hasOne('App\TwitchChannelSetting', 'partner_id', 'partner_id');
    }

}
