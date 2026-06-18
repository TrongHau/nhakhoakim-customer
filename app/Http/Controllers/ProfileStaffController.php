<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Repositories\ProfileStaffRepository;
use App\Repositories\ReceiptRepository;
use Illuminate\Support\Facades\Auth;
use App\Libs\Helper;

class ProfileStaffController extends Controller
{
    protected $profileStaffRepo;
    protected $receiptRepo;

    public function __construct(ProfileStaffRepository $profileStaffRepo, ReceiptRepository $receiptRepo)
    {
        parent::__construct();
        $this->profileStaffRepo = $profileStaffRepo;
        $this->receiptRepo      = $receiptRepo;
    }

    public function getStaffProfile(Request $request)
    {
        $staffId = Auth::user()['StaffId'] ?? null;
        if (!$staffId) {
            $this->addMessage('Không xác định được tài khoản đăng nhập', 'ERR001', 3);
            return $this->json(false, 'bool');
        }

        $branchId = $this->receiptRepo->checkIpAddress(Helper::getClientIp());
        if (!$branchId) {
            $this->addMessage('Không xác định được chi nhánh từ địa chỉ IP', 'ERR002', 3);
            return $this->json(false, 'bool');
        }

        $fromDate = $request->input('FromDate');
        $toDate   = $request->input('ToDate');

        $result = $this->profileStaffRepo->getStaffProfile($staffId, $branchId, $fromDate, $toDate);

        if (isset($result['error'])) {
            $this->addMessage($result['error'], 'ERR003', 3);
            return $this->json(false, 'bool');
        }

        $results[] = $this->formatData('StaffProfile', $result, 'Grid');
        return $this->json($results, 'views');
    }

    public function getServiceAnalysis(Request $request)
    {
        $staffId = Auth::user()['StaffId'] ?? null;
        if (!$staffId) {
            $this->addMessage('Không xác định được tài khoản đăng nhập', 'ERR001', 3);
            return $this->json(false, 'bool');
        }
        $branchId = $this->receiptRepo->checkIpAddress(Helper::getClientIp());

        if (!$branchId) {
            $this->addMessage('Không xác định được chi nhánh từ địa chỉ IP', 'ERR002', 3);
            return $this->json(false, 'bool');
        }

        $fromDate = $request->input('FromDate');
        $toDate   = $request->input('ToDate');

        $result = $this->profileStaffRepo->getServiceAnalysis($staffId, $branchId, $fromDate, $toDate);

        if (isset($result['error'])) {
            $this->addMessage($result['error'], 'ERR003', 3);
            return $this->json(false, 'bool');
        }

        $results[] = $this->formatData('ServiceAnalysis', $result, 'Grid');
        return $this->json($results, 'views');
    }

    public function getRating(Request $request)
    {
        $staffId = Auth::user()['StaffId'] ?? null;
        if (!$staffId) {
            $this->addMessage('Không xác định được tài khoản đăng nhập', 'ERR001', 3);
            return $this->json(false, 'bool');
        }

        $branchId = $this->receiptRepo->checkIpAddress(Helper::getClientIp());
        if (!$branchId) {
            $this->addMessage('Không xác định được chi nhánh từ địa chỉ IP', 'ERR002', 3);
            return $this->json(false, 'bool');
        }

        $ranking = $this->profileStaffRepo->getRatingFromCache((int) $branchId, (int) $staffId);

        $results[] = $this->formatData('StaffRating', $ranking, 'Grid');
        return $this->json($results, 'views');
    }

    public function getRevenueChart(Request $request)
    {
        $staffId = Auth::user()['StaffId'] ?? null;
        if (!$staffId) {
            $this->addMessage('Không xác định được tài khoản đăng nhập', 'ERR001', 3);
            return $this->json(false, 'bool');
        }

        $branchId = $this->receiptRepo->checkIpAddress(Helper::getClientIp());
        if (!$branchId) {
            $this->addMessage('Không xác định được chi nhánh từ địa chỉ IP', 'ERR002', 3);
            return $this->json(false, 'bool');
        }

        $result = $this->profileStaffRepo->getRevenueChart((int) $staffId, (int) $branchId);

        $results[] = $this->formatData('RevenueChart', $result, 'Grid');
        return $this->json($results, 'views');
    }
}
