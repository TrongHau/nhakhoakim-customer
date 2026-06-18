<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Libs\Helper;
use App\Repositories\CustomerRepository;
use Illuminate\Contracts\Container\BindingResolutionException;
use Exception;
use Illuminate\Container\EntryNotFoundException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Intervention\Image\ImageManager;

class CustomerImageController extends Controller
{
    public const DEFAULT_PHOTO_SIZE = 7*1024*1024; // 7MB

    /**
     * CustomerRepository
     * @var CustomerRepository
     */
    protected $customerRepo;

    public function __construct(CustomerRepository $customerRepo)
    {
        parent::__construct();
        $this->customerRepo = $customerRepo;
    }

    public function getCDNImages(Request $request){
        //Validation
        $validator = Validator::make($request->all(), [
            'CustomerId' => 'required|numeric',
            'Type' => 'required|numeric',
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->formatValidationMessages($errors->all());
            return $this->json(false, 'bool');
        }
        $cdnImages = Helper::getFileToServers($this->getPathByType($request));
       
        return $this->json([$this->formatData('ListCDNImage',$cdnImages)]);
    }

    /**
     * @param Request $request 
     * @return mixed 
     * @throws BindingResolutionException 
     * @throws Exception 
     * @throws EntryNotFoundException 
     */
    public function addCDNImage(Request $request) {
        //Validation
        $validator = Validator::make($request->all(), [
            'CustomerId' => 'required|numeric',
            'File' => 'required',
            'Type' => 'required|numeric',
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->formatValidationMessages($errors->all());
            return $this->json(false, 'bool');
        }
        $file = $request->file('File');
        $urlFile = Helper::uploadFileToServer($file, $this->getPathByType($request));

        if (!$urlFile || empty($urlFile)) {
            $this->addMessage("Upload Hình ảnh lên hệ thống CDN không thành công", 'ERR001', self::$ERROR);
            return $this->json([$this->formatData('CDNImage','')]);
        }
        
        return $this->json([$this->formatData('CDNImage',API_MEDIA .'/'. $urlFile)]);
    }

    private function getPathByType(Request $request) {
        if (empty($request)) {
            return '';
        }
        $type = $request->get('Type', 5);
        $customerId = $request->get('CustomerId', 0);
        switch ($type) {
            case config('constants.customer.image.type.xray'):
                return 'ImagingAndXrays' . '/' . $customerId;
            case config('constants.customer.image.type.accept'):
                return $customerId . '/' . 'Accepts';
            case config('constants.customer.image.type.test_blood'):
                return $customerId . '/' . 'Test_bloods';
            case config('constants.customer.image.type.commit'):
                return $customerId . '/' . 'Commits';
            case config('constants.customer.image.type.letter'):
                return $customerId . '/' . 'Letters';
            case config('constants.customer.image.type.surgical_sequence'):
                return $customerId . '/' . 'Surgical_sequences';
            default:
                return '';
        }
        return '';
    }

    public function uploadPhoto(Request $request)
    {
        //Validation
        $validator = Validator::make($request->all(), [
            'Photo' => 'required|file',
            'CustomerId' => 'required|numeric'
        ], [
            'Photo.required' => 'Vui lòng upload hình ảnh với định dạng: *.jpg, *.jpeg, *.png',
            'Photo.file' => 'Vui lòng upload hình ảnh với định dạng: *.jpg, *.jpeg, *.png',
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->formatValidationMessages($errors->all(), 'ERR001', self::$ERROR);
            return $this->json(false, 'bool');
        }
        try {
            if ($request->hasFile('Photo')) {
                $file = $request->file('Photo');
                $customerId = $request->input('CustomerId');
                $imageManager = new ImageManager(array('driver' => 'gd'));

                //Compression image <= 7MB
                $originalURLFile = null;
                if ($file->getSize() > self::DEFAULT_PHOTO_SIZE) {
                   $this->addMessage('Vui lòng upload hình ảnh không quá 7MB', 'ERR001', self::$ERROR);
                    return $this->json(false, 'bool');
                }
                // Danh sách extension cho phép (viết thường)
                $allowed_extensions = ['jpg', 'jpeg', 'png'];

                // Lấy phần đuôi của file, không có dấu chấm, viết thường
                if (!in_array(strtolower($file->getClientOriginalExtension()), $allowed_extensions)) {
                    $this->addMessage('Vui lòng upload hình ảnh với định dạng: *.jpg, *.jpeg, *.png', 'ERR001', self::$ERROR);
                    return $this->json(false, 'bool');
                }

                if (function_exists('exif_read_data')) {
                    $originalURLFile = $this->scaleImageWithAspectRatio($file, $customerId, 5000, 5000);
                    $resizeURLFile = $this->scaleImageWithAspectRatio($file, $customerId, 300, 300);
                } else {
                    $originalURLFile = $resizeURLFile = Helper::uploadFileToServer($file, 'Customers/'.$customerId);
                }

                if (!$originalURLFile || empty($originalURLFile) || !$resizeURLFile || empty($resizeURLFile)) {
                    $this->addMessage("Upload Hình ảnh lên hệ thống CDN thất bại", 'ERR001', self::$ERROR);
                    return $this->json(false, 'bool');
                }
                $data = $this->customerRepo->updatePhoto($customerId, $originalURLFile, $resizeURLFile);
                if ($data) {
                    $this->addMessage("Cập nhật hình ảnh cho khách hàng thành công", 'SUCC001', self::$SUCCESS);
                    return $this->json(true, 'bool');
                }
                $this->addMessage("Cập nhật hình ảnh cho khách hàng thất bại", 'ERR001', self::$ERROR);
                return $this->json(false, 'bool');
            }

        } catch (Exception $ex) {
            $this->addMessage('Đã có lỗi khi xử lý hoặc upload hình ảnh', 'ERR001', self::$ERROR);
            return $this->json(false, 'bool');
        }
        $this->addMessage('Đã có lỗi khi xử lý hoặc upload hình ảnh', 'ERR001', self::$ERROR);
        return $this->json(false, 'bool');
        
    }

    private function scaleImageWithAspectRatio(UploadedFile $file, $customerId, $maxWidth, $maxHeight)
    {
        $uploadedFile = null;
        try {
            $imageManager = new ImageManager(array('driver' => 'gd'));
            $image = $imageManager->make($file->getRealPath());

            // Calculate the scaling factor while maintaining aspect ratio
            $image->resize($maxWidth, $maxHeight, function ($constraint) {
                $constraint->aspectRatio();
                $constraint->upsize(); // Prevent upsizing
            });

            if (function_exists('exif_read_data')) {
                $image->orientate(); // Correct orientation based on EXIF data
            }

            $image->encode('jpg', 80); // Adjust quality as needed

            // Create a temporary file to hold the image
            $tmpPath = tempnam(sys_get_temp_dir(), 'imgPhoto_');
            file_put_contents($tmpPath, $image); // Save image data to temp file

            // Create a fake UploadedFile (from the processed image)
            $uploadedFile = new UploadedFile(
                $tmpPath,
                $file->getClientOriginalName(),
                'image/jpeg',
                null,         // size 
                true          // $testMode
            );

        } catch (\Exception $e) {
            Log::error('Resize image error: ', [$e->getMessage()]);
            $uploadedFile = $file;
        }

        return Helper::uploadFileToServer($uploadedFile, 'Customers/'.$customerId);
    }
}
