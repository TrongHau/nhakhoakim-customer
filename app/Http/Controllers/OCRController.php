<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Libs\Helper;
use App\Libs\OCR\Handler\FptOCR;
use App\Libs\OCR\OCR;
use App\Repositories\OCRTrackingRepository;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class OCRController extends Controller
{
    /**
     * OCRTrackingRepository
     * @var OCRTrackingRepository
     */
    protected $OCRTrackingRepo;

    public function __construct(OCRTrackingRepository $OCRTrackingRepo)
    {
        parent::__construct();
        $this->OCRTrackingRepo = $OCRTrackingRepo;
    }

    public function check(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'Image'    => 'required|file'
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'ERR001', self::$ERROR);
            return $this->json(false, 'bool');
        }

        $results = [];

        try {
            //Upload file
            $urlFile = Helper::uploadFileToServer($request->file('Image'), 'CCCD/'.date('Y-m-d'));

            if (!$urlFile || empty($urlFile)) {
                $this->addMessage("Upload Hình ảnh, tài liệu lên hệ thống CDN không thành công", 'ERR001', self::$ERROR);
                return $this->json(false, 'bool');
            }
            $imageURL = API_MEDIA .'/'. $urlFile;

            //OCR
            $fptOCR = new FptOCR();
            $fptOCR->setAPI(config('constants.orc.api'))
            ->setSecretKey(config('constants.orc.secretKey'))
            ->setImage($request->file('Image'));
    
            $response = OCR::exec($fptOCR);
            if ($response && Helper::isJSON($response)) {
                $response = json_decode($response);
            }
            $dataResponse = null;
            if ($response && isset($response->data)) {
                $dataResponse = $response->data ?? null;
                if (is_array($dataResponse)) {
                    $dataResponse = array_pop($dataResponse);
                }
            }
            $data = $this->OCRTrackingRepo->createTracking($imageURL, $dataResponse);
            $results[] = $this->formatData('OCRInfoCheck', $data);
        } catch (\Exception $ex) {
            Log::error("OCR check fail ", [$ex->getMessage()]);
        }

        
        return $this->json($results);
    }
}
