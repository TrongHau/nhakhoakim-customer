<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Libs\Helper;
use App\Repositories\CustomerDocumentRepository;
use Illuminate\Support\Facades\Validator;

class CustomerDocumentController extends Controller
{
    /**
     * @var CustomerDocumentRepository
     */
    protected $customerDocumentRepo;

    public function __construct(CustomerDocumentRepository $customerDocumentRepo)
    {
        parent::__construct();
        $this->customerDocumentRepo = $customerDocumentRepo;
    }

    public function getDocumentByCustomer(Request $request)
    {
        //TODO: add validator for Customer Id
		$validator = Validator::make($request->all(),[
			'CustomerId'=>'required|numeric',
            'Status' => 'nullable',
		]);
		if($validator->errors()->count()>0){
			$this->formatValidationMessages($validator->errors()->getMessages());
			return $this->json(false,'bool');
		}

        $data = $this->customerDocumentRepo->getDocumentByCustomer($request->get('CustomerId', 0), $request->all());

        $results[] = $this->formatData("ListDocumentOfCustomer", $data);

        return $this->json($results);
    }

    public function createDocument(Request $request)
    {
        $validator = Validator::make($request->all(),[
			'CustomerId'=> 'required|numeric',
            'CustomerName' => 'required',
            'Birthday' => 'nullable|date',
            'Gender' => 'nullable|numeric',
            'BirthPlace' => 'nullable',
            'PermanentAddress' => 'nullable',
            'DocumentType' => 'required|numeric',
            'DocumentNumber' => 'required',
            'IssuedDate' => 'nullable|date',
            'ExpiryDate' => 'nullable|date',
            'Priority' => 'nullable|numeric',
            'Status' => 'nullable|numeric',
            'IssuingAuthorityId' => 'nullable|numeric',
            'IssuingType' => 'nullable',
            'Note' => 'nullable',
            'Class' => 'nullable|numeric',
            'DocumentFiles' => 'nullable|array',
		]);
		if($validator->errors()->count()>0){
			$this->formatValidationMessages($validator->errors()->getMessages());
			return $this->json(false,'bool');
		}

        $res = $this->customerDocumentRepo->createDocument($request->all());
        if ($res) {
            $this->addMessage("Thêm mới thành công", "SUCC001", self::$SUCCESS);
            return $this->json(true, 'bool');
        }
        $this->addMessage("Thêm mới thất bại", "ERR001", self::$ERROR);
        return $this->json(false, 'bool');
    }

    public function editDocument(Request $request)
    {
        $validator = Validator::make($request->all(),[
            'CustomerDocumentId' => 'required|numeric',
			'CustomerId'=>'required|numeric',
            'CustomerName' => 'required',
            'Birthday' => 'nullable|date',
            'Gender' => 'nullable|numeric',
            'BirthPlace' => 'nullable',
            'PermanentAddress' => 'nullable',
            'DocumentType' => 'required|numeric',
            'DocumentNumber' => 'required',
            'IssuedDate' => 'nullable|date',
            'ExpiryDate' => 'nullable|date',
            'Priority' => 'nullable|numeric',
            'Status' => 'nullable|numeric',
            'IssuingAuthorityId' => 'nullable|numeric',
            'IssuingType' => 'nullable',
            'Note' => 'nullable',
            'Class' => 'nullable|numeric',
		]);
		if($validator->errors()->count()>0){
			$this->formatValidationMessages($validator->errors()->getMessages());
			return $this->json(false,'bool');
		}

        $res = $this->customerDocumentRepo->editDocument($request->get('CustomerDocumentId', 0) , $request->all());
        if ($res) {
            $this->addMessage("Chỉnh sửa thành công", "SUCC001", self::$SUCCESS);
            return $this->json(true, 'bool');
        }
        $this->addMessage("Chỉnh sửa thất bại", "ERR001", self::$ERROR);
        return $this->json(false, 'bool');
    }

    public function removeDocument(Request $request)
    {
        $validator = Validator::make($request->all(),[
            'CustomerDocumentId' => 'required|numeric',
        ]);
        if($validator->errors()->count()>0){
			$this->formatValidationMessages($validator->errors()->getMessages());
			return $this->json(false,'bool');
		}

        $res = $this->customerDocumentRepo->removeDocument($request->get('CustomerDocumentId', 0));
        if ($res) {
            $this->addMessage("Xoá thành công", "SUCC001", self::$SUCCESS);
            return $this->json(true, 'bool');
        }
        $this->addMessage("Xoá thất bại", "ERR001", self::$ERROR);
        return $this->json(false, 'bool');
    }

    public function uploadDocument(Request $request)
    {
        $validator = Validator::make($request->all(),[
            'CustomerDocumentId' => 'nullable|numeric',
            'CustomerId' => 'required|numeric',
            'DocumentFiles' => 'required|array',
            'PositionSide' => 'nullable|numeric',
        ]);
        if($validator->errors()->count()>0){
			$this->formatValidationMessages($validator->errors()->getMessages());
			return $this->json(false,'bool');
		}

        $files = $request->file('DocumentFiles');

        $linkCDNs = [];
        foreach ($files as $file) {
            $urlFile = Helper::uploadFileToServer($file, 'Document/'.$request->get('CustomerId', 0));

            if (!$urlFile || empty($urlFile)) {
                $this->addMessage("Upload Hình ảnh, tài liệu lên hệ thống CDN không thành công", 'ERR001', self::$ERROR);
                return $this->json(false, 'bool');
            }
            $linkCDNs[] = API_MEDIA .'/'. $urlFile;
        }
        $request->merge(['LinkCDN' => $linkCDNs]);

        $res = $this->customerDocumentRepo->uploadDocument($request->all());

        if ($res) {
            $this->addMessage("Upload thành công", "SUCC001", self::$SUCCESS);
            return $this->json(true, 'bool');
        }

        $this->addMessage("Upload thất bại", "ERR001", self::$ERROR);
        return $this->json(false, 'bool');
    }

}
