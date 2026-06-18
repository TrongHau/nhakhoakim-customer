<?php

namespace App\Exports;

use App\Libs\BaseExportStorage;
use App\Libs\Helper;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

class S3ExportStorage extends BaseExportStorage
{
    public function uploadFile($filePath, $saveDir, $fileExportName = false)
    {
        try {
            $filePathExport = new UploadedFile($filePath, basename($filePath), mime_content_type($filePath));
            $responeMediaExport = Helper::uploadFileToServer($filePathExport, $saveDir, $fileExportName);
            if ($responeMediaExport && !empty($responeMediaExport)) {
                $exportFile = API_MEDIA . '/' . $responeMediaExport;
                return $exportFile;
            }
        } catch (\Exception $e) {
            Log::error('Error when upload file to s3: ', [$e->getMessage()]);
        }

        return false;
    }
}

