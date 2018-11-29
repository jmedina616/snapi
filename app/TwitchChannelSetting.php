<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class TwitchChannelSetting extends Model {
    
    protected $fillable = ['partner_id', 'auto_upload'];

    public function channel() {
        return $this->belongsTo('App\TwitchChannel', 'partner_id', 'partner_id');
    }

}
