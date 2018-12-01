<?php

namespace App\Http\Middleware;

use Closure;
use App\Exceptions\SmhAPIException;

class SmhSocialBroadcastingService {

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next) {
        $pid = $request->route('partner_id');
        $client = new \GuzzleHttp\Client(['http_errors' => false]);
        $req = $client->get("https://mediaplatform.streamingmediahosting.com/apps/services/v1.0/index.php?pid=" . $pid . "&action=get_services");
        $response = json_decode($req->getBody());

        if ($response->social_network) {
            return $next($request);
        }

        throw new SmhAPIException('service_not_authorized');
    }

}
