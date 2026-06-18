<?php

namespace App\Libs\OCR;

use App\Libs\OCR\Handler\BaseOCR;
use GuzzleHttp\Client as GuzzClient;
use Illuminate\Support\Facades\Log;

class OCR
{
    public static function exec(BaseOCR $orc)
    {
        if (empty($orc)) {
            return false;
        }
        try {
            return $orc->exec();
        } catch (\Exception $exception) {
            Log::error("OCR Lib fail: ", [$exception->getMessage()]);
            return false;
        }
        return false;
    }
}