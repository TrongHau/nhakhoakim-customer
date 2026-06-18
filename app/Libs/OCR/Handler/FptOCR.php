<?php

namespace App\Libs\OCR\Handler;

use GuzzleHttp\Client as GuzzClient;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Support\Facades\Log;

class FptOCR extends BaseOCR
{
    public function exec()
    {
        if (empty($this->api)) {
            Log::error("FPT OCR missing API remote");
            return false;
        }
        if (empty($this->secretKey)) {
            Log::error("FPT OCR missing Secret key");
            return false;
        }
        if (empty($this->secretKey)) {
            Log::error("FPT OCR missing Image");
            return false;
        }

        try {
            $options = [
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
                    'api-key' => $this->secretKey
                ],
                'multipart' => [
                    [
                        'name' => 'filename',
                        'contents' => $this->image->getClientOriginalName()
                    ],
                    [
                        'name' => 'image',
                        'contents' => Utils::tryFopen($this->image->getPathName(), 'r'),
                        'filename' => $this->image->getClientOriginalName()
                    ]
                ]
            ];
            $guzzClient = new GuzzClient();
            $res = $guzzClient->request('POST', $this->api, $options);
            
            if ($res && $res->getStatusCode() == 200) {
                return $res->getBody()->getContents();
            }
        } catch (\Exception $ex) {
            Log::error("FPT OCR error: " . $ex->getMessage());
            return false;
        }
        return false;
    }
}
