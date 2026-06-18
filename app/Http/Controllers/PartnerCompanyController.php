<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Repositories\InsuranceCompanyRepository;
use App\Repositories\InsuranceContractRepository;
use App\Repositories\PartnerCompanyRepository;
use Illuminate\Support\Facades\Validator;

class PartnerCompanyController extends Controller
{
    /**
     * @var PartnerCompanyRepository
     */
    protected $partnerCompanyRepo;

    /**
     * @var InsuranceCompanyRepository
     */
    protected $insuranceRepo;

    public function __construct(PartnerCompanyRepository $partnerCompanyRepo, InsuranceCompanyRepository $insuranceRepo) {
        parent::__construct();
        $this->partnerCompanyRepo = $partnerCompanyRepo;
        $this->insuranceRepo = $insuranceRepo;
    }

    public function getAllInsuranceCompany(Request $request)
    {
        $data = $this->partnerCompanyRepo->getAllInsuranceCompany();
        $results[] = $this->formatData('InsuranceCompany', $data);
        return $this->json($results);
    }

    public function getInsuranceCompanyByBranch(Request $request)
    {
        $this->validate($request, [
            'BranchId' => 'nullable|integer',
            'PartnerCompanyId' => 'nullable|integer',
            'lmstart' => 'nullable',
            'limit' => 'nullable',
        ]);
        $data = $this->partnerCompanyRepo->getInsuranceCompanyByBranch($request->all());
        $results[] = $this->formatPagination('BranchInsuranceCompany', $data);
        return $this->json($results);
    }

    public function getContactInsurance(Request $request)
    {
        $data = $this->insuranceRepo->getContactInsurance($request->all());

        $rs[] = $this->formatPagination("ContactInsurance", $data);
        return $this->json($rs, 'views');
    }

    public function getOutOfHoursInsurers(Request $request)
    {
        $rs[] = $this->formatData("ListOvertimeGuaranteeContract", "https://s3.hn-2.cloud.cmctelecom.vn/files/DANH_SACH_HOP_DONG_BLVP_NGOAI_GIO.pdf");
        return $this->json($rs, 'views');
    }

    public function editInsuranceByBranch(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'BranchId' => 'required|integer',
            'PartnerCompanyId' => 'array'
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'ERR001', self::$ERROR);
            return $this->json(false, 'bool');
        }
        $data = $this->partnerCompanyRepo->syncBranchPartnerCompany($request->all());
        if ($data) {
            $this->addMessage("Cập nhật danh sách bảo hiểm thành công!", 'IBB002', self::$SUCCESS);
            return $this->json(true, 'bool');
        }
        $this->addMessage("Cập nhật danh sách bảo hiểm thất bại!", 'EIBB002', self::$ERROR);
        return $this->json(false, 'bool');
    }
}
