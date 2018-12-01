<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class YoutubeChannelSetting extends Model {

    protected $fillable = ['youtube_channel_id', 'embed', 'auto_upload', 'projection'];

    public function channel() {
        return $this->belongsTo('App\YoutubeChannel','youtube_channel_id');
    }

}
