<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class TwitchChannelSetting extends Model {

    public function channel() {
        return $this->belongsTo('App\TwitchChannel', 'partner_id', 'partner_id');
    }

}
