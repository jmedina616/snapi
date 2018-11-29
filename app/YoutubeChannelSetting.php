<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class YoutubeChannelSetting extends Model {

    protected $fillable = ['partner_id', 'embed', 'auto_upload', 'projection'];

    public function channel() {
        return $this->belongsTo('App\YoutubeChannel', 'partner_id', 'partner_id');
    }

}
