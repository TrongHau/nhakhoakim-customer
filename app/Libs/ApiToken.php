<?php

namespace App\Libs;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class ApiToken
{

    public function getResult(Request $request){
        return $this->authen($request);
    }
    private function authen(Request $request){
        //Get data from cache
        $data = $this->getCache($request);

        if ($data && !empty($data)) {
            return $data;
        }

        //Remote authen
        $remote = new ApiProcess();
        $remote->request('module.data')
            ->from(env('MAIN_DOMAIN') . 'authen/authorize')
            ->where([
                        'UserAgent' => $request->header('User-Agent'),
                        'Host' => $request->getHttpHost(),
                        'Ip' => $request->getClientIp(),
                        'App' => env('APP_NAME'),
                        'Uri' => $request->getRequestUri(),
                        'MainPage' => $request->MainPage??'',
                        'WorkProfilePositionId' => $request->get('WorkProfilePositionId', $request->get('CurrentWorkProfilePositionId', 0))
                    ])
            ->execute();
        $data = $remote->loadVar(false);
	    if((isset($data->code) && $data->code===false) || !$data){
	    	return false;
	    }

        //Set cache
        $this->setCache($request, $data);
        
        //Return data
        return $data;
    }

    private function getKeyCache(Request $request)
    {
        $token = $request->bearerToken();
        $workProfilePositionId = $request->get('WorkProfilePositionId', $request->get('CurrentWorkProfilePositionId', 0));

        if (!$token || empty($token)) {
            return null;
        }

        return 'authen:token_workprofileposition:'.(explode('.', $token)[2] ?? ''). ':' . $workProfilePositionId;
    }

    private function setCache(Request $request, $data)
    {
        try {
            //Get key cache and lifetime
            $keyCache = $this->getKeyCache($request);

            //set connect redis
			Redis::connect(env('REDIS_HOST'), env('REDIS_PORT'));
            Redis::set($keyCache, json_encode($data));

            //response
            return true;
        } catch (\Exception $ex) {
            Log::error("Redis set cache in authen failed", [$ex->getMessage()]);
        }
        return false;
    }

    private function getCache(Request $request)
    {
        try {
            //Get key cache
            $keyCache = $this->getKeyCache($request);

            //set connect redis
			Redis::connect(env('REDIS_HOST'), env('REDIS_PORT'));
            $data = Redis::get($keyCache);

            if ($data && !empty($data) && Helper::isJSON($data)) {
                $data = json_decode($data);
            }
            return $data;
        } catch (\Exception $ex) {
            Log::error("Redis get cache in authen failed", [$ex->getMessage()]);
        }
        return null;
    }
}
