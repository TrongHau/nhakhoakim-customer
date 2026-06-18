<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Libs\Helper;
use App\Repositories\InfectionControlScoringRepository;
use Illuminate\Support\Facades\Validator;

class InfectionController extends Controller
{
    /**
     * @var InfectionControlScoringRepository
     */
    protected $infectionRepo;


    public function __construct(InfectionControlScoringRepository $infectionRepo)
    {
        parent::__construct();
        $this->infectionRepo = $infectionRepo;
    }

    public function list(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'FromDate' => 'required|date',
            'ToDate' => 'required|date',
            'BranchId' => 'nullable|numeric',
            'Type' => 'nullable|numeric',
            'lmstart' => 'nullable',
            'limit' => 'nullable',
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'ERR001', self::$ERROR);
            return $this->json(false, 'bool');
        }

        $data = $this->infectionRepo->list($request->all());

        $results[] = $this->formatPagination('InfectionControlScoringList', $data);

        return $this->json($results);
    }

    public function detail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'Id' => 'required|numeric',
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'ERR001', self::$ERROR);
            return $this->json(false, 'bool');
        }

        $data = $this->infectionRepo->detail($request->get('Id'));

        $results[] = $this->formatData('InfectionControlScoringDetail', $data);

        return $this->json($results);
    }

    public function create(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'Type' => 'required|numeric',
            'BranchId' => 'required|numeric',
            'TotalScore' => 'required|numeric',
            'TotalTargetAchieved' => 'required|numeric',
            'Detail' => 'required|array',
            'Detail.*.Section' => 'nullable',
            'Detail.*.SubSection' => 'required',
            'Detail.*.CheckContent' => 'nullable',
            'Detail.*.IsPassed' => 'required|numeric',
            'Detail.*.Note' => 'nullable',
            'Detail.*.Score' => 'required|numeric',
            'Detail.*.AttachFiles' => 'nullable|array',
            'Detail.*.AttachFiles.*.File' => 'required|file',
            'Detail.*.AttachFiles.*.FileName' => 'required',
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'ERR001', self::$ERROR);
            return $this->json(false, 'bool');
        }

        //Upload file to CDN
        if (!empty($request->input('Detail'))) {
            $details = [];
            foreach ($request->input('Detail') as $key => $val) {
                if ($request->input('Detail.'. $key .'.AttachFiles')) {
                    foreach ($request->input('Detail.'. $key .'.AttachFiles') as $keyAttach => $valAttach) {
                        if ($request->hasFile('Detail.'. $key .'.AttachFiles.'.$keyAttach.'.File')) {
                            //Set file name item
                            $fileItem = $request->file('Detail.'. $key .'.AttachFiles.'.$keyAttach.'.File');

                            $urlFile = Helper::uploadFileToServer($fileItem, 'InfectionControls', true);
    
                            if (!$urlFile || empty($urlFile)) {
                                $this->addMessage("Upload tài liệu lên hệ thống CDN không thành công", 'ERR001', self::$ERROR);
                                return $this->json(false, 'bool');
                            }
                            $urlLink = API_MEDIA .'/'. $urlFile;
    
                            $val['AttachFiles'][$keyAttach]['URL'] = $urlLink;
                        }
                    }
                }
                $details[] = $val;
            }
            $request->merge([
                'Detail' => $details
            ]);
        }

        $res = $this->infectionRepo->create($request->all());

        if ($res) {
            $this->addMessage('Chấm điểm Tiêu chí kiểm soát nhiễm khuẩn thành công', 'SUCC001', self::$SUCCESS);
            return $this->json(true, 'bool');
        }

        $this->addMessage('Chấm điểm Tiêu chí kiểm soát nhiễm khuẩn thành công', 'ERR001', self::$ERROR);
        return $this->json(false, 'bool');
                
    }
}
