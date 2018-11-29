<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateYoutubeChannelsTable extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up() {
        Schema::create('youtube_channels', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->integer('partner_id');
            $table->string('name', 255);
            $table->string('thumbnail', 255);
            $table->longText('channel_id');
            $table->string('is_verified', 100);
            $table->integer('ls_enabled');
            $table->longText('access_token');
            $table->longText('refresh_token');
            $table->string('token_type', 100);
            $table->integer('expires_in');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down() {
        Schema::dropIfExists('youtube_channels');
    }

}
