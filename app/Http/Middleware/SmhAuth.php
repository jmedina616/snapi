<?php

namespace App\Http\Middleware;

use Closure;
use Kaltura\Client\Configuration as KalturaConfiguration;
use Kaltura\Client\Client as KalturaClient;
use Kaltura\Client\Enum\SessionType as KalturaSessionType;
use Kaltura\Client\ApiException;
use Kaltura\Client\ClientException;
use App\Exceptions\SmhAPIException;

class SmhAuth {

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next) {
        $pid = $request->route('partner_id');
        $ks = $request->route('ks');
        if ($this->validateTokenSession($pid, $ks)) {
            return $next($request);
        }
        throw new SmhAPIException('not_authorized');
    }

    //Valid KS with Media Platform backend
    public function validateTokenSession($pid, $ks) {
        $config = new KalturaConfiguration($pid);
        $config->serviceUrl = 'https://mediaplatform.streamingmediahosting.com/';
        $client = new KalturaClient($config);
        $partnerFilter = null;
        $pager = null;
        $client->setKs($ks);

        try {
            $result = $client->partner->get($pid);
            $partner_id = $result->id;

            if (isset($partner_id) && $partner_id == $pid) {
                return true;
            } else {
                return false;
            }
        } catch (Exception $ex) {
            Log::error($ex->message);
            return false;
        }
    }

}
