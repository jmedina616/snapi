<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Http\Resources\Json\Resource;
use Illuminate\Support\Facades\Schema;
use DB;
use Log;

class AppServiceProvider extends ServiceProvider {

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot() {
        Resource::withoutWrapping();

        Schema::defaultStringLength(191);

        DB::listen(function($query) {
            Log::info(
                    $query->sql, $query->bindings, $query->time
            );
        });
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register() {
        //
    }

}
