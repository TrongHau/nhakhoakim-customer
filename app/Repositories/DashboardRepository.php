<?php

namespace App\Repositories;

use App\Repositories\Abstracts\EloquentRepository;
use App\Customer;
use App\Receipt;
use App\Expenditure;
use App\Appointment;
use App\Rating;
use App\OrderDetail;
use App\AllocatedRevenueTracking;
use App\TargetRevenueDaily;
use App\OrderChanging;
use App\Branch;
use App\BranchDailyRevenue;
use App\CRMTargetKPI;
use App\Doctor;
use App\OrderDetailFinancialTrans;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Libs\Factory;
use Maatwebsite\Excel\Concerns\ToArray;
use App\DashboardBranchDaily;
use App\DashboardBranchServiceDaily;
use App\DashboardCustomerRatingDaily;
use App\DashboardCustomerSourceSummary;
use App\DashboardStaffEffectiveDaily;
class DashboardRepository extends EloquentRepository
{
    public function getModel()
    {
        return Customer::class;
    }

    public function totalReceipt($data)
    {
        try {
            $branchId = $data['BranchId'] ?? NULL;
            if (!$branchId) {
                return [
                    'TotalReceipt' => 0,
                    'TargetValue' => 0,
                    'KPI' => 0
                ];
            }
            $fromDate = $data['FromDate'] ?? NULL;
            $toDate = $data['ToDate'] ?? NULL;

            // Lấy tổng doanh thu từ bảng DashboardBranchDaily
            $totalRevenue = DashboardBranchDaily::selectRaw('SUM(Revenue) as TotalRevenue')
                ->where('BranchId', $branchId)
                ->whereBetween('SummaryDate', [$fromDate, $toDate])
                ->first();

            $totalAmount = $totalRevenue->TotalRevenue ?? 0;

            // Lấy target revenue
            $fromStartDate = Carbon::parse($fromDate)->startOfMonth()->format('Y-m-d');
            $toEndDate   = Carbon::parse($toDate)->endOfMonth()->format('Y-m-d');
            $CRMTargetKPI = TargetRevenueDaily::selectRaw('SUM(TargetRevenue) as TotalTargetRevenue')
                ->where('BranchId', $branchId)
                ->whereBetween('TargetDate', [$fromStartDate, $toEndDate])
                ->first();

            $targetRevenue = $CRMTargetKPI->TotalTargetRevenue ?? 0;

            $KPI = $targetRevenue > 0
                ? round(($totalAmount / $targetRevenue) * 100, 2)
                : 0;

            return [
                'TotalReceipt' => $totalAmount,
                'TargetValue' => $targetRevenue,
                'KPI' => $KPI
            ];

        } catch (\Exception $e) {
            Log::error('DashboardRepository@totalReceipt: '.$e->getMessage());
            return [
                'TotalReceipt' => 0,
                'TargetValue' => 0,
                'KPI' => 0
            ];
        }
    }

    public function totalReceiptV2($data)
    {
        try {
            $branchId = $data['BranchId'] ?? [];
            $fromDate = $data['FromDate'] ?? NULL;
            $toDate = $data['ToDate'] ?? NULL;

            // Lấy tổng doanh thu từ bảng DashboardBranchDaily
            $totalRevenue = DashboardBranchDaily::selectRaw('SUM(Revenue) as TotalRevenue')
                ->whereIn('BranchId', $branchId)
                ->whereBetween('SummaryDate', [$fromDate, $toDate])
                ->first();

            $totalAmount = $totalRevenue->TotalRevenue ?? 0;

            $fromStartDate = Carbon::parse($fromDate)->startOfMonth()->format('Y-m-d');
            $toEndDate   = Carbon::parse($toDate)->endOfMonth()->format('Y-m-d');
            $CRMTargetKPIQuery = TargetRevenueDaily::selectRaw('SUM(TargetRevenue) as TotalTargetRevenue');
            if (is_array($branchId)) {
                $CRMTargetKPIQuery->whereIn('BranchId', $branchId);
            }
            $CRMTargetKPI = $CRMTargetKPIQuery->whereBetween('TargetDate', [$fromStartDate, $toEndDate])->first();
            $targetRevenue = isset($CRMTargetKPI->TotalTargetRevenue) ? $CRMTargetKPI->TotalTargetRevenue : 0;

            $KPI = $targetRevenue > 0
                ? round(($totalAmount / $targetRevenue) * 100, 2)
                : 0;

            return [
                'TotalReceipt' => $totalAmount,
                'TargetValue' => $targetRevenue,
                'KPI' => $KPI
            ];

        } catch (\Exception $e) {
            Log::error('DashboardRepository@totalReceiptV2: '.$e->getMessage());
            return [
                'TotalReceipt' => 0,
                'TargetValue' => 0,
                'KPI' => 0
            ];
        }
    }

    public function totalAppointment($data)
    {
        try {
            $branchId = $data['BranchId'] ?? NULL;
            if (!$branchId) {
                return [
                    'ConsultationAppointment' => 0,
                    'TreatmentAppointment' => 0
                ];
            }
            $fromDate = $data['FromDate'] ?? NULL;
            $toDate = $data['ToDate'] ?? NULL;

            if(!$fromDate) {
                $fromDate = Carbon::now()->format('Y-m-d');
            }

            // Lấy tổng số lịch hẹn từ bảng DashboardBranchDaily
            $appointmentData = DashboardBranchDaily::selectRaw('
                SUM(ApptConsultCount) as TotalConsultation,
                SUM(ApptTreatmentCount) as TotalTreatment
            ')
                ->where('BranchId', $branchId)
                ->whereBetween('SummaryDate', [$fromDate, $toDate])
                ->first();

            return [
                'ConsultationAppointment' => $appointmentData->TotalConsultation ?? 0,
                'TreatmentAppointment' => $appointmentData->TotalTreatment ?? 0
            ];

        } catch (\Exception $e) {
            Log::error('DashboardRepository@totalAppointment: '.$e->getMessage());
            return [
                'ConsultationAppointment' => 0,
                'TreatmentAppointment' => 0
            ];
        }
    }

    public function totalAppointmentV2($data)
    {
        try {
            $branchId = $data['BranchId'] ?? [];
            $fromDate = $data['FromDate'] ?? NULL;
            $toDate = $data['ToDate'] ?? NULL;

            if(!$fromDate) {
                $fromDate = Carbon::now()->format('Y-m-d');
            }

            // Lấy tổng số lịch hẹn từ bảng DashboardBranchDaily
            $query = DashboardBranchDaily::selectRaw('
                SUM(ApptConsultCount) as TotalConsultation,
                SUM(ApptTreatmentCount) as TotalTreatment
            ')
                ->whereBetween('SummaryDate', [$fromDate, $toDate]);

            if (is_array($branchId) && count($branchId) > 0) {
                $query->whereIn('BranchId', $branchId);
            }

            $appointmentData = $query->first();

            return [
                'ConsultationAppointment' => $appointmentData->TotalConsultation ?? 0,
                'TreatmentAppointment' => $appointmentData->TotalTreatment ?? 0
            ];

        } catch (\Exception $e) {
            Log::error('DashboardRepository@totalAppointmentV2: '.$e->getMessage());
            return [
                'ConsultationAppointment' => 0,
                'TreatmentAppointment' => 0
            ];
        }
    }

    public function listRating($data)
    {
        try {
            $branchId = $data['BranchId'] ?? NULL;
            if (!$branchId) {
                return [];
            }
            $fromDate = $data['FromDate'] ?? NULL;
            $toDate = $data['ToDate'] ?? NULL;

            // List Rating
            $listRating = Rating::select('Rating.*','c.FullName as CustomerName','c.CustomerCode')
                ->join('Customer as c', 'Rating.CustomerId', '=', 'c.CustomerId')
                ->where('Rating.BranchId', $branchId)
                ->where('Rating.CreatedDate', '>=', strtotime($fromDate." 00:00:01"))
                ->where('Rating.CreatedDate', '<=', strtotime($toDate." 23:59:59"))
                ->orderByDesc('Rating.CreatedDate')
                ->limit(6);

            return $listRating->get()->toArray();

        } catch (\Exception $e) {
            Log::error('DashboardRepository@listRating: '.$e->getMessage());
            return [];
        }
    }

    public function totalRating($data)
    {
        try {
            $branchId = $data['BranchId'] ?? 0;
            $fromDate = $data['FromDate'] ?? NULL;
            $toDate = $data['ToDate'] ?? NULL;

            // Lấy dữ liệu rating từ bảng DashboardCustomerRatingDaily
            $query = DashboardCustomerRatingDaily::selectRaw('
                SUM(RatingCount) as Total,
                SUM(CASE WHEN RatingValue = 1 THEN RatingCount ELSE 0 END) as OneStar,
                SUM(CASE WHEN RatingValue = 2 THEN RatingCount ELSE 0 END) as TwoStar,
                SUM(CASE WHEN RatingValue = 3 THEN RatingCount ELSE 0 END) as ThreeStar,
                SUM(CASE WHEN RatingValue = 4 THEN RatingCount ELSE 0 END) as FourStar,
                SUM(CASE WHEN RatingValue = 5 THEN RatingCount ELSE 0 END) as FiveStar
            ')
                ->whereBetween('SummaryDate', [$fromDate, $toDate]);

            if ($branchId > 0) {
                $query->where('BranchId', $branchId);
            }

            $listRating = $query->first();

            return [
                "Total" => $listRating->Total ?? 0,
                "1Star" => $listRating->OneStar ?? 0,
                "2Star" => $listRating->TwoStar ?? 0,
                "3Star" => $listRating->ThreeStar ?? 0,
                "4Star" => $listRating->FourStar ?? 0,
                "5Star" => $listRating->FiveStar ?? 0
            ];

        } catch (\Exception $e) {
            Log::error('DashboardRepository@totalRating: '.$e->getMessage());
            return [
                "Total" => 0,
                "1Star" => 0,
                "2Star" => 0,
                "3Star" => 0,
                "4Star" => 0,
                "5Star" => 0
            ];
        }
    }

    public function totalRatingV2($data)
    {
        try {
            $branchId = $data['BranchId'] ?? [];
            $fromDate = $data['FromDate'] ?? NULL;
            $toDate = $data['ToDate'] ?? NULL;

            // Lấy dữ liệu rating từ bảng DashboardCustomerRatingDaily
            $query = DashboardCustomerRatingDaily::selectRaw('
                SUM(RatingCount) as Total,
                SUM(CASE WHEN RatingValue = 1 THEN RatingCount ELSE 0 END) as OneStar,
                SUM(CASE WHEN RatingValue = 2 THEN RatingCount ELSE 0 END) as TwoStar,
                SUM(CASE WHEN RatingValue = 3 THEN RatingCount ELSE 0 END) as ThreeStar,
                SUM(CASE WHEN RatingValue = 4 THEN RatingCount ELSE 0 END) as FourStar,
                SUM(CASE WHEN RatingValue = 5 THEN RatingCount ELSE 0 END) as FiveStar
            ')
                ->whereBetween('SummaryDate', [$fromDate, $toDate]);

            if (is_array($branchId) && count($branchId) > 0) {
                $query->whereIn('BranchId', $branchId);
            }

            $listRating = $query->first();

            return [
                "Total" => $listRating->Total ?? 0,
                "1Star" => $listRating->OneStar ?? 0,
                "2Star" => $listRating->TwoStar ?? 0,
                "3Star" => $listRating->ThreeStar ?? 0,
                "4Star" => $listRating->FourStar ?? 0,
                "5Star" => $listRating->FiveStar ?? 0
            ];

        } catch (\Exception $e) {
            Log::error('DashboardRepository@totalRatingV2: '.$e->getMessage());
            return [
                "Total" => 0,
                "1Star" => 0,
                "2Star" => 0,
                "3Star" => 0,
                "4Star" => 0,
                "5Star" => 0
            ];
        }
    }

    public function listConsultationService($data)
    {
        try {
            $branchId = $data['BranchId'] ?? NULL;
            if (!$branchId) {
                return [];
            }
            $fromDate = $data['FromDate'] ?? NULL;
            $toDate = $data['ToDate'] ?? NULL;
            $fromTimestamp = strtotime($fromDate . " 00:00:01");
            $toTimestamp   = strtotime($toDate . " 23:59:59");

            // List Consultation Service
            $listConsultationService = OrderDetail::select('s.WarrantyType', 'OrderDetail.Quantity')
                ->join('Service as s', 'OrderDetail.ServiceId', '=', 's.ServiceId')
                ->join('OrderChanging as oc', 'OrderDetail.OrderChangingId', '=', 'oc.OrderChangingId')
                ->where('oc.BranchId', $branchId)
                ->whereBetween('oc.ChangedAt', [$fromTimestamp, $toTimestamp])
                ->where('OrderDetail.Status', 1)->get()->toArray();
            
            $listAgreementService = OrderDetail::select('s.WarrantyType', 'OrderDetail.Quantity')
                ->join('Service as s', 'OrderDetail.ServiceId', '=', 's.ServiceId')
                ->where('OrderDetail.ConsultedBranchId', $branchId)
                ->whereBetween('OrderDetail.ConsultedDate', [$fromDate . " 00:00:01", $toDate . " 23:59:59"])
                ->whereIn('OrderDetail.Status', [2, 50, 100])->get()->toArray();
            
            return $data = ['ConsultationService' => $listConsultationService, 'AgreementService' => $listAgreementService];

        } catch (\Exception $e) {
            Log::error('DashboardRepository@listConsultationService: '.$e->getMessage());
            return [];
        }
    }


    public function listConsultationServiceV2($data)
    {
        try {
            $branchId = $data['BranchId'] ?? [];
            $fromDate = $data['FromDate'] ?? NULL;
            $toDate = $data['ToDate'] ?? NULL;
            $fromTimestamp = strtotime($fromDate . " 00:00:01");

            // Lấy dữ liệu tổng hợp từ bảng DashboardBranchServiceDaily với BranchId
            $query = DashboardBranchServiceDaily::selectRaw('
                BranchId,
                ServiceCategoryId,
                SUM(ConsultCount) as TotalConsult,
                SUM(SuccessCount) as TotalAgreement
            ')
                ->whereBetween('SummaryDate', [$fromDate, $toDate])
                ->groupBy('BranchId', 'ServiceCategoryId');

            if (is_array($branchId) && count($branchId) > 0) {
                $query->whereIn('BranchId', $branchId);
            }

            $serviceData = $query->get();

            // Lấy thông tin Branch để map BranchCode
            $branchIds = $serviceData->pluck('BranchId')->unique()->toArray();
            $branches = Branch::where('State', 1)
                ->pluck('BranchCode', 'BranchId')
                ->toArray();

            // Chuyển đổi sang format mà Controller mong đợi
            $listConsultationService = [];
            $listAgreementService = [];
            
            foreach ($serviceData as $item) {
                // Map ServiceCategoryId: T->NULL, I->I, P->P, C->O (Orthodontic)
                $warrantyType = $item->ServiceCategoryId === 'T' ? NULL : 
                                ($item->ServiceCategoryId === 'C' ? 'O' : $item->ServiceCategoryId);

                $branchCode = $branches[$item->BranchId] ?? null;

                // Tạo records cho consultation với unique ServiceId_OrderChangingId
                for ($i = 0; $i < $item->TotalConsult; $i++) {
                    $listConsultationService[] = [
                        'WarrantyType' => $warrantyType,
                        'ServiceId' => 'S_' . $item->ServiceCategoryId . '_' . $item->BranchId . '_' . $i,
                        'OrderChangingId' => 'OC_' . $item->ServiceCategoryId . '_' . $item->BranchId . '_' . $i,
                        'BranchId' => $item->BranchId,
                        'BranchCode' => $branchCode
                    ];
                }

                // Tạo records cho agreement với unique ServiceId_OrderChangingId
                for ($i = 0; $i < $item->TotalAgreement; $i++) {
                    $listAgreementService[] = [
                        'WarrantyType' => $warrantyType,
                        'ServiceId' => 'S_' . $item->ServiceCategoryId . '_' . $item->BranchId . '_' . $i,
                        'OrderChangingId' => 'OC_' . $item->ServiceCategoryId . '_' . $item->BranchId . '_' . $i,
                        'BranchId' => $item->BranchId,
                        'BranchCode' => $branchCode
                    ];
                }
            }

            // Lấy CRM Target KPI
            $CRMTargetKPI = CRMTargetKPI::select('BranchId', 'GeneralityAmount', 'ProstheticAmount', 'ImplantAmount', 'OrthodonticAmount')
                ->whereIn('BranchId', $branchId)
                ->where('MonthNumber', '=', date('Y-m', $fromTimestamp))
                ->get()
                ->toArray();

            return [
                'ConsultationService' => $listConsultationService,
                'AgreementService' => $listAgreementService,
                'CRMTargetKPI' => $CRMTargetKPI
            ];

        } catch (\Exception $e) {
            Log::error('DashboardRepository@listConsultationServiceV2: '.$e->getMessage());
            return [];
        }
    }

    public function listTreatmentByDoctor($data)
    {
        try {
            $branchId = $data['BranchId'] ?? NULL;
            if (!$branchId) {
                return [];
            }
            $fromDate = $data['FromDate'] ?? NULL;
            $toDate = $data['ToDate'] ?? NULL;

            // List Treatment By Doctor
            $listTreatmentByDoctor = AllocatedRevenueTracking::select('AllocatedRevenueTracking.CreatedBy',DB::raw('COUNT(DISTINCT AllocatedRevenueTracking.CustomerId) AS TotalCustomer'), 's.FullName','s.StaffCode')
                ->join('Doctor as d', 'd.StaffId', '=', 'AllocatedRevenueTracking.CreatedBy')
                ->join('in.Staff as s', 'AllocatedRevenueTracking.CreatedBy', '=', 's.StaffId')
                ->where('AllocatedRevenueTracking.BranchId', $branchId)
                ->where('AllocatedRevenueTracking.CreatedDate', '>=', $fromDate." 00:00:01")
                ->where('AllocatedRevenueTracking.CreatedDate', '<=', $toDate." 23:59:59")
                ->groupBy('AllocatedRevenueTracking.CreatedBy','s.FullName','s.StaffCode')
                ->orderByDesc('TotalCustomer');

            return $listTreatmentByDoctor->get()->toArray();

        } catch (\Exception $e) {
            Log::error('DashboardRepository@listTreatmentByDoctor: '.$e->getMessage());
            return [];
        }
    }

    public function totalCustomer($data)
    {
        try {
            $branchId = $data['BranchId'] ?? NULL;
            if (!$branchId) {
                return [
                    'CustomerConsultation' => 0,
                    'CustomerTreatment' => 0,
                    'CustomerWarranty' => 0
                ];
            }
            $fromDate = $data['FromDate'] ?? NULL;
            $toDate = $data['ToDate'] ?? NULL;

            $fromTimestamp = strtotime($fromDate . " 00:00:01");
            $toTimestamp   = strtotime($toDate . " 23:59:59");

            // Total Consultation Appointment
            $totalCustomerConsultation = Appointment::select('Appointment.CustomerId')
                ->join('AppointmentAppointmentLabel', 'Appointment.AppointmentId', '=', 'AppointmentAppointmentLabel.AppointmentId')
                ->where('AppointmentAppointmentLabel.AppointmentLabelId', 3)
                ->where('Appointment.AtBranchId', $branchId)
                ->where('Appointment.AppointmentStatusId', '>', 11)
                ->whereBetween('Appointment.StartAt', [$fromTimestamp, $toTimestamp])
                ->distinct('Appointment.CustomerId')
                ->count('Appointment.CustomerId');
            

            // Total Treatment Appointment
            $totalCustomerTreatment = Appointment::select('Appointment.CustomerId')
                ->join('AppointmentAppointmentLabel', 'Appointment.AppointmentId', '=', 'AppointmentAppointmentLabel.AppointmentId')
                ->whereIn('AppointmentAppointmentLabel.AppointmentLabelId', [6,9])
                ->where('Appointment.AtBranchId', $branchId)
                ->where('Appointment.AppointmentStatusId', '>', 11)
                ->whereBetween('Appointment.StartAt', [$fromTimestamp, $toTimestamp])
                ->distinct('Appointment.CustomerId')
                ->count('Appointment.CustomerId');

            return [
                'CustomerConsultation' => $totalCustomerConsultation ?? 0,
                'CustomerTreatment' => $totalCustomerTreatment ?? 0,
                'CustomerWarranty' => 0
            ];

        } catch (\Exception $e) {
            Log::error('DashboardRepository@totalAppointment: '.$e->getMessage());
            return [
                'CustomerConsultation' => 0,
                'CustomerTreatment' => 0,
                'CustomerWarranty' => 0
            ];
        }
    }

    public function listAppointmentSource($data)
    {
        try {
            $branchId = $data['BranchId'] ?? NULL;
            if (!$branchId) {
                return [];
            }
            $fromDate = $data['FromDate'] ?? NULL;
            $toDate = $data['ToDate'] ?? NULL;

            // Lấy dữ liệu từ bảng DashboardCustomerSourceSummary và join với CustomerChannel
            $listAppointmentSource = DashboardCustomerSourceSummary::select(
                    'DashboardCustomerSourceSummary.CustomerSourceId as FromCustomerChannel',
                    'CustomerChannel.Name',
                    DB::raw('SUM(DashboardCustomerSourceSummary.CustomerCount) as Total')
                )
                ->join('CustomerChannel', 'CustomerChannel.CustomerChannelId', '=', 'DashboardCustomerSourceSummary.CustomerSourceId')
                ->where('DashboardCustomerSourceSummary.BranchId', $branchId)
                ->whereBetween('DashboardCustomerSourceSummary.SummaryDate', [$fromDate, $toDate])
                ->groupBy('DashboardCustomerSourceSummary.CustomerSourceId', 'CustomerChannel.Name')
                ->orderByDesc('Total')
                ->get();

            return $listAppointmentSource->toArray();

        } catch (\Exception $e) {
            Log::error('DashboardRepository@listAppointmentSource: '.$e->getMessage());
            return [];
        }
    }

    public function totalReceiptByStaff($data)
    {
        try {
            $branchId = $data['BranchId'] ?? null;
            $fromDate = $data['FromDate'] ?? null;
            $toDate = $data['ToDate'] ?? null;

            if (!$branchId || !$fromDate || !$toDate) {
                return [];
            }

            $from = $fromDate . ' 00:00:00';
            $to   = $toDate . ' 23:59:59';

            $receiptByStaff = Receipt::query()
                ->select(
                    'Receipt.UpdatedBy',
                    's.FullName',
                    's.StaffCode',
                    DB::raw('SUM(Receipt.TotalAmount) AS TotalAmount')
                )
                ->join('in.Staff AS s', 's.StaffId', '=', 'Receipt.UpdatedBy')
                // ->join('in.WorkProfile AS wp', 'wp.StaffId', '=', 's.StaffId')->where('wp.IsCurrentProfile', 1)
                // ->join('in.WorkProfilePosition AS wpp', 'wpp.WorkProfilePositionId', '=', 'wp.WorkProfilePositionId')
                ->where('Receipt.BranchId', $branchId)
                // ->whereIn('wpp.Code', ['OM','AOM','GSVH','Reception']) // Chuyên viên tư vấn, QLPK, Quyền QLPK
                ->whereBetween('Receipt.AddedAt', [strtotime($from), strtotime($to)])
                ->where('Receipt.ReceiptStatusId', '>', 1)
                ->groupBy('Receipt.UpdatedBy', 's.FullName', 's.StaffCode')
                ->orderByDesc('TotalAmount')
                ->get()
                ->keyBy('UpdatedBy');

            if ($receiptByStaff->isEmpty()) {
                return [];
            }

            $orderDetailByStaff = OrderDetail::query()
                ->select(
                    'ConsultedBy',
                    DB::raw('COUNT(DISTINCT TreatmentId) AS TotalTreatment')
                )
                ->where('Status', '>', 2)
                ->whereBetween('ConsultedDate', [$from, $to])
                ->whereIn('ConsultedBy', $receiptByStaff->keys())
                ->groupBy('ConsultedBy')
                ->pluck('TotalTreatment', 'ConsultedBy');

            $result = $receiptByStaff->map(function ($item) use ($orderDetailByStaff) {
                return [
                    'StaffId'        => $item->UpdatedBy,
                    'FullName'       => $item->FullName,
                    'StaffCode'      => $item->StaffCode,
                    'TotalAmount'    => (float) $item->TotalAmount,
                    'TotalTreatment' => (int) ($orderDetailByStaff[$item->UpdatedBy] ?? 0),
                ];
            })->values()->toArray();

            return $result;

        } catch (\Throwable $e) {
            Log::error('DashboardRepository@totalReceiptByStaff: ' . $e->getMessage());
            return [];
        }
    }


    public function totalReceiptByStaffV2($data)
    {
        try {

            $branchId = $data['BranchId'] ?? null;
            $fromDate = $data['FromDate'] ?? null;
            $toDate = $data['ToDate'] ?? null;
            $positionId = $data['CurrentWorkProfilePositionId'] ?? null;
            $currentStaffId = $data['CurrentStaffId'] ?? null;

            if (!$branchId || !$fromDate || !$toDate) {
                return [];
            }

            $from = $fromDate . ' 00:00:00';
            $to   = $toDate . ' 23:59:59';

            // Danh sách nhân viên có lịch làm việc tại chi nhánh
            $staffList = $this->listStaffByBranchId($branchId, $fromDate, $toDate, $positionId, $currentStaffId, false);
            if (!$staffList || empty($staffList)) {
                $staffList = $this->listStaffByBranchId($branchId, $fromDate, $toDate, $positionId, $currentStaffId, true);
            }
            if (empty($staffList)) return [];

            // Loại BS khỏi danh sách
            $doctorIds = Doctor::where('State',1)->pluck('StaffId')->toArray();

            $staffList = array_filter($staffList, function ($s) use ($doctorIds) {
                return !in_array($s->StaffId, $doctorIds);
            });

            if (empty($staffList)) return [];

            foreach ($staffList as $key => $s) {
                if ($s->TeamName == 'Phụ tá') {
                    unset($staffList[$key]);
                }
            }

            $staffIds = array_column($staffList, 'StaffId');

            // Lấy dữ liệu từ bảng DashboardStaffEffectiveDaily
            $staffEffectiveData = DashboardStaffEffectiveDaily::selectRaw('
                    StaffId,
                    ServiceCategoryId,
                    SUM(ConsultCount) as TotalConsult,
                    SUM(SuccessCount) as TotalSuccess,
                    SUM(Revenue) as TotalRevenue
                ')
                ->where('BranchId', $branchId)
                ->whereBetween('SummaryDate', [$fromDate, $toDate])
                ->whereIn('StaffId', $staffIds)
                ->groupBy('StaffId', 'ServiceCategoryId')
                ->get();

            // Map dữ liệu theo StaffId và ServiceCategoryId
            $mapConsult = [];
            $mapSuccess = [];
            $moneyMap = [];

            foreach ($staffEffectiveData as $item) {
                $sid = $item->StaffId;
                $category = $item->ServiceCategoryId;

                // Map ServiceCategoryId: T->G, I->I, P->P, C->O
                $type = $category === 'T' ? 'G' : ($category === 'C' ? 'O' : $category);

                if (!isset($mapConsult[$sid])) {
                    $mapConsult[$sid] = ['G' => 0, 'I' => 0, 'P' => 0, 'O' => 0];
                }
                if (!isset($mapSuccess[$sid])) {
                    $mapSuccess[$sid] = ['G' => 0, 'I' => 0, 'P' => 0, 'O' => 0];
                }

                $mapConsult[$sid][$type] = ($mapConsult[$sid][$type] ?? 0) + $item->TotalConsult;
                $mapSuccess[$sid][$type] = ($mapSuccess[$sid][$type] ?? 0) + $item->TotalSuccess;
                $moneyMap[$sid] = ($moneyMap[$sid] ?? 0) + $item->TotalRevenue;
            }

            /** ================== BUILD RESULT ================== */

            $result = [];
            foreach ($staffList as $s) {
                $sid = $s->StaffId;
                $activeDate = isset($s->ActiveDate)
                    ? Carbon::parse($s->ActiveDate)
                    : null;

                $endDate = Carbon::now();

                $month = null;

                if ($activeDate) {

                    $months = $activeDate->diffInMonths($endDate);

                    if ($months > 0) {
                        $month = $months . ' tháng';
                    } else {
                        $days = $activeDate->diffInDays($endDate);
                        $month = $days . ' ngày';
                    }
                }
                $result[] = [
                    'StaffId'   => $sid,
                    'FullName'  => $s->FullName,
                    'StaffCode'=> $s->StaffCode,
                    'Month'     => $month,
                    'TotalAmount' => (float) ($moneyMap[$sid] ?? 0),

                    'G' => [
                        'Consult' => $mapConsult[$sid]['G'] ?? 0,
                        'Success' => $mapSuccess[$sid]['G'] ?? 0,
                    ],
                    'I' => [
                        'Consult' => $mapConsult[$sid]['I'] ?? 0,
                        'Success' => $mapSuccess[$sid]['I'] ?? 0,
                    ],
                    'P' => [
                        'Consult' => $mapConsult[$sid]['P'] ?? 0,
                        'Success' => $mapSuccess[$sid]['P'] ?? 0,
                    ],
                    'C' => [
                        'Consult' => $mapConsult[$sid]['O'] ?? 0,
                        'Success' => $mapSuccess[$sid]['O'] ?? 0,
                    ],
                ];
            }

            usort($result, function ($a, $b) {
                return $b['TotalAmount'] <=> $a['TotalAmount'];
            });

            return $result;

        } catch (\Throwable $e) {
            Log::error('DashboardRepository@totalReceiptByStaffV2: ' . $e->getMessage());
            return [];
        }
    }

    private function mapByStaffAndType($rows)
    {
        $map = [];

        foreach ($rows as $r) {
            $staffId = $r->ConsultingStaffId;
            $key     = $r->GroupKey;
            $total   = (int) $r->total;

            // WarrantyType NULL → ServiceId → General
            if (str_starts_with($key, 'S_')) {
                $type = 'G';
                $total = 1; // Mỗi service tính 1
            } else {
                // WarrantyType có giá trị
                switch ($key) {
                    case 'I':
                        $type = 'I';
                        break;
                    case 'P':
                        $type = 'P';
                        break;
                    case 'O': // đã normalize từ O → C
                        $type = 'O';
                        break;
                    default:
                        $type = 'G';
                }
            }

            if (!isset($map[$staffId][$type])) {
                $map[$staffId][$type] = 0;
            }

            $map[$staffId][$type] += $total;
        }

        return $map;
    }

    public function unsetDoctor($data)
    {
        if (empty($data)) {
            return [];
        }
        foreach ($data as $key => $value) {
            $infDoctor = Doctor::where('StaffId', $value->StaffId)->where('State', 1)->first();
            if ($infDoctor) {
                unset($data[$key]);
            }
        }
        return $data;
    }

    public function isDoctor($data)
    {
        if (empty($data)) {
            return [];
        }
        foreach ($data as $key => $value) {
            $infDoctor = Doctor::where('StaffId', $value->StaffId)->first();
            if (!$infDoctor) {
                unset($data[$key]);
                continue;
            }
            $data[$key]->SpecializationCode = $infDoctor->SpecializationCode ?? null;
        }
        return $data;
    }

    public function totalReceiptByDoctor($data)
    {
        try {
            $branchId = $data['BranchId'] ?? null;
            $fromDate = $data['FromDate'] ?? null;
            $toDate = $data['ToDate'] ?? null;

            if (!$branchId || !$fromDate || !$toDate) {
                return [];
            }

            $from = $fromDate . ' 00:00:00';
            $to   = $toDate . ' 23:59:59';

            $consultSub = OrderChanging::query()
                ->join('OrderDetail AS od', 'od.OrderChangingId', '=', 'OrderChanging.OrderChangingId')
                ->selectRaw('
                    OrderChanging.ChangedBy AS StaffId,
                    COUNT(DISTINCT od.TreatmentId) AS TotalConsult
                ')
                ->where('OrderChanging.BranchId', $branchId)
                ->where('od.Status', '>', 0)
                ->whereBetween('OrderChanging.ChangedAt', [strtotime($from), strtotime($to)])
                ->groupBy('OrderChanging.ChangedBy');

            $result = AllocatedRevenueTracking::query()
                ->join('Doctor AS d', 'd.StaffId', '=', 'AllocatedRevenueTracking.CreatedBy')
                ->join('in.Staff AS s', 's.StaffId', '=', 'AllocatedRevenueTracking.CreatedBy')

                ->leftJoinSub($consultSub, 'consult', function($join) {
                    $join->on('consult.StaffId', '=', 'AllocatedRevenueTracking.CreatedBy');
                })

                ->where('AllocatedRevenueTracking.BranchId', $branchId)
                ->whereBetween('AllocatedRevenueTracking.TreatmentCompletedDate', [$from, $to])

                ->selectRaw('
                    AllocatedRevenueTracking.CreatedBy,
                    s.FullName,
                    s.StaffCode,
                    SUM(
                        CASE AllocatedRevenueTracking.TrackingType
                            WHEN 1 THEN AllocatedRevenueTracking.KIMRevenueAmount
                            WHEN 2 THEN -AllocatedRevenueTracking.KIMRevenueAmount
                            ELSE 0
                        END
                    ) AS TotalAmount,
                    COALESCE(consult.TotalConsult, 0) AS TotalConsult
                ')
                ->groupBy('AllocatedRevenueTracking.CreatedBy', 's.FullName', 's.StaffCode')
                ->orderByDesc('TotalAmount')
                ->get()
                ->toArray();

            return $result;

        } catch (\Throwable $e) {
            Log::error('DashboardRepository@totalReceiptByDoctor: ' . $e->getMessage());
            return [];
        }
    }

    public function totalReceiptByDoctorV2($data)
    {
        try {
            $branchId  = $data['BranchId'] ?? null;
            $fromDate  = $data['FromDate'] ?? null;
            $toDate    = $data['ToDate'] ?? null;
            $positionId = $data['CurrentWorkProfilePositionId'] ?? null;
            $currentStaffId = $data['CurrentStaffId'] ?? null;

            if (!$branchId || !$fromDate || !$toDate) {
                return [];
            }

            /** ================= STAFF ================= */
            $staffs = $this->listStaffByBranchId($branchId, $fromDate, $toDate, $positionId, $currentStaffId, false);
            if (!$staffs || empty($staffs)) {
                $staffs = $this->listStaffByBranchId($branchId, $fromDate, $toDate, $positionId, $currentStaffId, true);
            }
            $staffs = $this->isDoctor($staffs);

            if (empty($staffs)) {
                return [];
            }

            $staffIds = array_column($staffs, 'StaffId');

            // Lấy dữ liệu từ bảng DashboardStaffEffectiveDaily
            $staffEffectiveData = DashboardStaffEffectiveDaily::selectRaw('
                    StaffId,
                    ServiceCategoryId,
                    SUM(ConsultCount) as TotalConsult,
                    SUM(SuccessCount) as TotalSuccess,
                    SUM(Revenue) as TotalRevenue
                ')
                ->where('BranchId', $branchId)
                ->whereBetween('SummaryDate', [$fromDate, $toDate])
                ->whereIn('StaffId', $staffIds)
                ->groupBy('StaffId', 'ServiceCategoryId')
                ->get();

            // Map dữ liệu theo StaffId và ServiceCategoryId
            $consultMap = [];
            $successMap = [];
            $revenueMap = [];

            foreach ($staffEffectiveData as $item) {
                $sid = $item->StaffId;
                $category = $item->ServiceCategoryId;

                // Map ServiceCategoryId: T->G, I->I, P->P, C->O
                $type = $category === 'T' ? 'G' : ($category === 'C' ? 'O' : $category);

                if (!isset($consultMap[$sid])) {
                    $consultMap[$sid] = ['G' => 0, 'I' => 0, 'P' => 0, 'O' => 0];
                }
                if (!isset($successMap[$sid])) {
                    $successMap[$sid] = ['G' => 0, 'I' => 0, 'P' => 0, 'O' => 0];
                }

                $consultMap[$sid][$type] = ($consultMap[$sid][$type] ?? 0) + $item->TotalConsult;
                $successMap[$sid][$type] = ($successMap[$sid][$type] ?? 0) + $item->TotalSuccess;
                $revenueMap[$sid] = ($revenueMap[$sid] ?? 0) + $item->TotalRevenue;
            }

            /** ================= BUILD RESULT ================= */
            $result = [];

            foreach ($staffs as $s) {
                $sid = $s->StaffId;

                $activeDate = isset($s->ActiveDate) ? Carbon::parse($s->ActiveDate) : null;
                $endDate    = Carbon::now();

                $month = null;
                if ($activeDate) {
                    $months = $activeDate->diffInMonths($endDate, false);
                    $month = $months >= 1
                        ? $months . ' tháng'
                        : $activeDate->diffInDays($endDate) . ' ngày';
                }

                $result[] = [
                    'StaffId'   => $sid,
                    'FullName'  => $s->FullName,
                    'StaffCode'=> $s->StaffCode,
                    'SpecializationCode' => $s->SpecializationCode,
                    'Month'     => $month,
                    'TotalRevenue' => $revenueMap[$sid] ?? 0,

                    'GeneralityConsult'   => $consultMap[$sid]['G'] ?? 0,
                    'GeneralitySuccess'   => $successMap[$sid]['G'] ?? 0,

                    'ImplantConsult'      => $consultMap[$sid]['I'] ?? 0,
                    'ImplantSuccess'      => $successMap[$sid]['I'] ?? 0,

                    'ProstheticConsult'   => $consultMap[$sid]['P'] ?? 0,
                    'ProstheticSuccess'   => $successMap[$sid]['P'] ?? 0,

                    'OrthodonticConsult'  => $consultMap[$sid]['O'] ?? 0,
                    'OrthodonticSuccess'  => $successMap[$sid]['O'] ?? 0,
                ];
            }

            usort($result, function ($a, $b) {
                return $b['TotalRevenue'] <=> $a['TotalRevenue'];
            });

            return $result;

        } catch (\Throwable $e) {
            Log::error('DashboardRepository@totalReceiptByDoctorV2: ' . $e->getMessage());
            return [];
        }
    }

    public function listReceiptByBranch($data)
    {
        try {
            $branchId = $data['BranchId'] ?? [];
            $fromDate = $data['FromDate'] ?? NULL;
            $toDate = $data['ToDate'] ?? NULL;

            // Lấy dữ liệu từ bảng DashboardBranchDaily
            // Revenue đã là net revenue (Receipt - Expenditure)
            $query = DashboardBranchDaily::selectRaw('
                DashboardBranchDaily.BranchId,
                SUM(DashboardBranchDaily.Revenue) as TotalAmount,
                SUM(DashboardBranchDaily.NewCustomerCount) as TotalVisitor
            ')
                ->whereBetween('DashboardBranchDaily.SummaryDate', [$fromDate, $toDate])
                ->groupBy('DashboardBranchDaily.BranchId');

            if (is_array($branchId) && count($branchId) > 0) {
                $query->whereIn('DashboardBranchDaily.BranchId', $branchId);
            }

            $branchDailyData = $query->get()->keyBy('BranchId');

            // Lấy Priority và BranchCode từ bảng Branch
            $branchQuery = Branch::select('BranchId', 'BranchCode', 'Priority');

            if (is_array($branchId) && count($branchId) > 0) {
                $branchQuery->whereIn('BranchId', $branchId);
            }

            $branchData = $branchQuery->get()->keyBy('BranchId');

            // Kết hợp dữ liệu
            $listReceipt = [];
            foreach ($branchDailyData as $branchId => $item) {
                $listReceipt[] = [
                    'BranchId' => $branchId,
                    'BranchCode' => isset($branchData[$branchId]) ? $branchData[$branchId]->BranchCode : null,
                    'TotalAmount' => $item->TotalAmount,
                    'TotalVisitor' => $item->TotalVisitor,
                    'Priority' => isset($branchData[$branchId]) ? $branchData[$branchId]->Priority : 0
                ];
            }
            // Lấy target revenue
            $fromStartDate = Carbon::parse($fromDate)->startOfMonth()->format('Y-m-d');
            $toEndDate   = Carbon::parse($toDate)->endOfMonth()->format('Y-m-d');
            $CRMTargetKPIQuery = TargetRevenueDaily::select(DB::raw('SUM(TargetRevenue) as TotalTargetRevenue'), 'BranchId');
            if (is_array($branchId) && count($branchId) > 0) {
                $CRMTargetKPIQuery->whereIn('BranchId', $branchId);
            }
            $CRMTargetKPIQuery->whereBetween('TargetDate', [$fromStartDate, $toEndDate])->groupBy('BranchId');
            $targetRevenue = $CRMTargetKPIQuery->get()->toArray();

            return [
                'ListReceipt' => $listReceipt,
                'ListExpenditure' => [], // Không cần nữa vì Revenue đã tính sẵn
                'TargetValue' => $targetRevenue
            ];

        } catch (\Exception $e) {
            Log::error('DashboardRepository@listReceiptByBranch: '.$e->getMessage());
            return [
                'ListReceipt'       => [],
                'ListExpenditure'   => [],
                'TargetValue'       => []
            ];
        }
    }

    public function mapBranchCodeName($branchId)
    {
        $branch = Branch::where('BranchId', $branchId)
            ->select('BranchCode','Priority')
            ->first();

        return $branch;
    }

    public function getBranchDailyRevenue($conditions)
    {
        $fromDate = $conditions['FromDate'];
        $toDate = $conditions['ToDate'];
        $branchId = $conditions['BranchId'] ?? [];

        if($toDate == date('Y-m-d')) {
            DB::select("CALL pos.usp_GetDailyRevenueByBranch(?)", [
               $toDate
            ]);
        }

        $query = BranchDailyRevenue::select(
            'BranchDailyRevenue.BranchId',
            'Branch.BranchCode',
            'Branch.Priority',
            DB::raw('SUM(BranchDailyRevenue.Visitor) as TotalVisitor'),
            DB::raw('SUM(BranchDailyRevenue.Traffic) as TotalTraffic'),
            DB::raw('SUM(BranchDailyRevenue.CashCollection) as TotalCashCollection'),
            DB::raw('SUM(BranchDailyRevenue.Revenue) as TotalRevenue'),
            DB::raw('SUM(BranchDailyRevenue.DoctorCount) as TotalDoctor'),
            DB::raw('SUM(BranchDailyRevenue.AssistantDoctorCount) as TotalAssistantDoctor'),
            DB::raw('SUM(BranchDailyRevenue.ConsultantCount) as TotalConsultant')
        );
        $query->join('in.Branch', 'BranchDailyRevenue.BranchId', '=', 'Branch.BranchId');
        $query->where('BranchDailyRevenue.Date', '>=', $fromDate);
        $query->where('BranchDailyRevenue.Date', '<=', $toDate);
        if(is_array($branchId)) {
            $query->whereIn('BranchDailyRevenue.BranchId', $branchId);
        }
        $query->where('Branch.State', 1);
        $query->orderBy('Branch.Priority', 'ASC');
        $query->groupBy('BranchDailyRevenue.BranchId');
        $result = $query->get()->toArray();

      return $result;
    }

    public function listStaffByBranchId($branchId, $fromDate, $toDate, $currentWorkProfilePositionId, $currentStaffId, $isBot = true)
    {   
        $header = null;
        if ($isBot) {
            $header = ['Authorization'=>'Bearer ' . JWT_APP_TOKEN];
        }
        $remote = Factory::getRemote();
        $remote->request('module.views[name=ListScheduleManager].data')
            ->from(API_GET_SCHEDULE_MANAGER)
            ->where([
                '_act' => 'listSchedule',
            'Schedule' => [
                'fromdate' => $fromDate,
                'todate' => $toDate,
                'branchid' => $branchId
            ],
            'lmstart' => 0,
            'limit' => 1000,
            'PermissionCode' => 'dashboard',
            'CurrentWorkProfilePositionId' => $currentWorkProfilePositionId,
            'CurrentStaffId' => $currentStaffId
            ])
            ->execute(true, $header);
        
        $response = $remote->loadVar(true);

        return $response;
    }

    public function insertBranchDaily($data)
    {
        try {
            $branchIds = $data['BranchId'] ?? [];
            $fromDate = $data['FromDate'] ?? NULL;
            $toDate = $data['ToDate'] ?? NULL;

            if(!$fromDate){
                $fromDate = Carbon::now()->format('Y-m-d');
            }

            if(!$toDate){
                $toDate = Carbon::now()->format('Y-m-d');
            }

            // Nếu không truyền BranchId, lấy tất cả chi nhánh có State = 1
            if (!is_array($branchIds) || empty($branchIds)) {
                $branchIds = Branch::where('State', 1)->pluck('BranchId')->toArray();

                if (empty($branchIds)) {
                    Log::warning('DashboardRepository@insertBranchDaily: No active branches found');
                    return ['success' => false, 'message' => 'No active branches found'];
                }
            }

            $fromTimestamp = strtotime($fromDate . " 00:00:01");
            $toTimestamp = strtotime($toDate . " 23:59:59");
            $currentTime = Carbon::now();

            DB::beginTransaction();

            // Query tổng tiền thu theo chi nhánh và ngày (một lần cho tất cả)
            $receiptData = Receipt::selectRaw('
                    DATE(FROM_UNIXTIME(AddedAt)) as SummaryDate,
                    BranchId,
                    SUM(TotalAmount) as TotalReceipt
                ')
                ->whereIn('BranchId', $branchIds)
                ->where('AddedAt', '>=', $fromTimestamp)
                ->where('AddedAt', '<=', $toTimestamp)
                ->groupBy(DB::raw('DATE(FROM_UNIXTIME(AddedAt))'), 'BranchId')
                ->get()
                ->keyBy(function($item) {
                    return $item->SummaryDate . '_' . $item->BranchId;
                })
                ->toArray();
            
            // Query tổng tiền chi theo chi nhánh và ngày
            $expenditureData = Expenditure::selectRaw('
                    DATE(FROM_UNIXTIME(CreatedAt)) as SummaryDate,
                    BranchId,
                    SUM(Amount) as TotalExpenditure
                ')
                ->whereIn('BranchId', $branchIds)
                ->where('ExpenditureCategoryId', 44)
                ->where('ExpenditureTypeId', 2)
                ->where('CreatedBy', '<>', 2267)
                ->where('CreatedAt', '>=', $fromTimestamp)
                ->where('CreatedAt', '<=', $toTimestamp)
                ->groupBy(DB::raw('DATE(FROM_UNIXTIME(CreatedAt))'), 'BranchId')
                ->get()
                ->keyBy(function($item) {
                    return $item->SummaryDate . '_' . $item->BranchId;
                })
                ->toArray();

            // Query số lượt khách hàng (Visitor) theo chi nhánh và ngày
            // Đếm số lượt appointment tư vấn (AppointmentLabelId = 3) với AppointmentStatusId > 20
            $newCustomerData = Appointment::selectRaw('
                    DATE(FROM_UNIXTIME(Appointment.StartAt)) as SummaryDate,
                    Appointment.AtBranchId as BranchId,
                    COUNT(*) as NewCustomerCount
                ')
                ->join('AppointmentAppointmentLabel', 'Appointment.AppointmentId', '=', 'AppointmentAppointmentLabel.AppointmentId')
                ->where('AppointmentAppointmentLabel.AppointmentLabelId', 3)
                ->whereIn('Appointment.AtBranchId', $branchIds)
                ->where('Appointment.AppointmentStatusId', '>', 20)
                ->where('Appointment.StartAt', '>=', $fromTimestamp)
                ->where('Appointment.StartAt', '<=', $toTimestamp)
                ->groupBy(DB::raw('DATE(FROM_UNIXTIME(Appointment.StartAt))'), 'Appointment.AtBranchId')
                ->get()
                ->keyBy(function($item) {
                    return $item->SummaryDate . '_' . $item->BranchId;
                })
                ->toArray();

            // Query số lượt tư vấn theo chi nhánh và ngày
            $consultData = Appointment::selectRaw('
                    DATE(FROM_UNIXTIME(Appointment.StartAt)) as SummaryDate,
                    Appointment.AtBranchId as BranchId,
                    COUNT(*) as ConsultCount
                ')
                ->join('AppointmentAppointmentLabel', 'Appointment.AppointmentId', '=', 'AppointmentAppointmentLabel.AppointmentId')
                ->where('AppointmentAppointmentLabel.AppointmentLabelId', 3)
                ->whereIn('Appointment.AtBranchId', $branchIds)
                ->where('Appointment.AppointmentStatusId', '>', 11)
                ->where('Appointment.StartAt', '>=', $fromTimestamp)
                ->where('Appointment.StartAt', '<=', $toTimestamp)
                ->groupBy(DB::raw('DATE(FROM_UNIXTIME(Appointment.StartAt))'), 'Appointment.AtBranchId')
                ->get()
                ->keyBy(function($item) {
                    return $item->SummaryDate . '_' . $item->BranchId;
                })
                ->toArray();

            // Query số lượt điều trị theo chi nhánh và ngày
            $treatmentData = Appointment::selectRaw('
                    DATE(FROM_UNIXTIME(Appointment.StartAt)) as SummaryDate,
                    Appointment.AtBranchId as BranchId,
                    COUNT(*) as TreatmentCount
                ')
                ->join('AppointmentAppointmentLabel', 'Appointment.AppointmentId', '=', 'AppointmentAppointmentLabel.AppointmentId')
                ->whereIn('AppointmentAppointmentLabel.AppointmentLabelId', [6, 9])
                ->whereIn('Appointment.AtBranchId', $branchIds)
                ->where('Appointment.AppointmentStatusId', '>', 11)
                ->where('Appointment.StartAt', '>=', $fromTimestamp)
                ->where('Appointment.StartAt', '<=', $toTimestamp)
                ->groupBy(DB::raw('DATE(FROM_UNIXTIME(Appointment.StartAt))'), 'Appointment.AtBranchId')
                ->get()
                ->keyBy(function($item) {
                    return $item->SummaryDate . '_' . $item->BranchId;
                })
                ->toArray();

            // Tạo danh sách các ngày
            $startDate = Carbon::parse($fromDate);
            $endDate = Carbon::parse($toDate);
            $dateList = [];
            while ($startDate->lte($endDate)) {
                $dateList[] = $startDate->format('Y-m-d');
                $startDate->addDay();
            }

            // Lấy các bản ghi hiện có
            $existingRecords = DB::table('DashboardBranchDaily')
                ->whereIn('SummaryDate', $dateList)
                ->whereIn('BranchId', $branchIds)
                ->get()
                ->keyBy(function($item) {
                    return $item->SummaryDate . '_' . $item->BranchId;
                })
                ->toArray();

            $recordsToInsert = [];
            $updateCount = 0;

            // Xử lý dữ liệu cho tất cả ngày và chi nhánh
            foreach ($dateList as $date) {
                foreach ($branchIds as $branchId) {
                    $key = $date . '_' . $branchId;
                    
                    $receipt = isset($receiptData[$key]) ? $receiptData[$key]['TotalReceipt'] : 0;
                    $expenditure = isset($expenditureData[$key]) ? $expenditureData[$key]['TotalExpenditure'] : 0;
                    $revenue = $receipt - $expenditure;

                    $recordData = [
                        'Revenue' => $revenue,
                        'NewCustomerCount' => isset($newCustomerData[$key]) ? $newCustomerData[$key]['NewCustomerCount'] : 0,
                        'ApptConsultCount' => isset($consultData[$key]) ? $consultData[$key]['ConsultCount'] : 0,
                        'ApptTreatmentCount' => isset($treatmentData[$key]) ? $treatmentData[$key]['TreatmentCount'] : 0,
                        'ApptWarrantyCount' => 0,
                        'UpdatedDate' => $currentTime
                    ];

                    if (isset($existingRecords[$key])) {
                        // Update
                        DB::table('DashboardBranchDaily')
                            ->where('SummaryDate', $date)
                            ->where('BranchId', $branchId)
                            ->update($recordData);
                        $updateCount++;
                    } else {
                        // Insert
                        $recordsToInsert[] = array_merge($recordData, [
                            'SummaryDate' => $date,
                            'BranchId' => $branchId,
                            'CreatedDate' => $currentTime
                        ]);
                    }
                }
            }

            // Batch insert
            if (!empty($recordsToInsert)) {
                $chunks = array_chunk($recordsToInsert, 500);
                foreach ($chunks as $chunk) {
                    DB::table('DashboardBranchDaily')->insert($chunk);
                }
            }

            DB::commit();

            return [
                'success' => true,
                'message' => 'Data inserted successfully',
                'branches_processed' => count($branchIds),
                'days_processed' => count($dateList),
                'inserted' => count($recordsToInsert),
                'updated' => $updateCount
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('DashboardRepository@insertBranchDaily: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ];
        }
    }

    public function insertBranchServiceDaily($data)
    {
        try {
            $branchIds = $data['BranchId'] ?? [];
            $fromDate = $data['FromDate'] ?? NULL;
            $toDate = $data['ToDate'] ?? NULL;

            if(!$fromDate){
                $fromDate = Carbon::now()->format('Y-m-d');
            }

            if(!$toDate){
                $toDate = Carbon::now()->format('Y-m-d');
            }

            // Nếu không truyền BranchId, lấy tất cả chi nhánh có State = 1
            if (!is_array($branchIds) || empty($branchIds)) {
                $branchIds = Branch::where('State', 1)->pluck('BranchId')->toArray();

                if (empty($branchIds)) {
                    Log::warning('DashboardRepository@insertBranchServiceDaily: No active branches found');
                    return ['success' => false, 'message' => 'No active branches found'];
                }
            }

            DB::beginTransaction();

            // Tạo danh sách các ngày cần xử lý
            $startDate = Carbon::parse($fromDate);
            $endDate = Carbon::parse($toDate);
            $totalDays = 0;
            $totalRecordsInserted = 0;

            // Loop qua từng ngày
            while ($startDate->lte($endDate)) {
                $currentDate = $startDate->format('Y-m-d');
                $currentTime = Carbon::now();

                // Query Consultation Service (Tư vấn) cho ngày hiện tại
                DB::statement('SET SESSION TRANSACTION ISOLATION LEVEL READ UNCOMMITTED');
                $listConsultationService = OrderDetail::select(
                        's.WarrantyType', 
                        'OrderDetail.Quantity', 
                        'OrderDetail.ServiceId', 
                        'OrderDetail.ConsultedBranchId as BranchId', 
                        'OrderDetail.OrderChangingId',
                        'OrderDetail.ConsultedDate'
                    )
                    ->join('Service as s', 'OrderDetail.ServiceId', '=', 's.ServiceId')
                    ->whereIn('OrderDetail.ConsultedBranchId', $branchIds)
                    ->where('OrderDetail.ConsultedDate', '>=', $currentDate . " 00:00:01")
                    ->where('OrderDetail.ConsultedDate', '<=', $currentDate . " 23:59:59")
                    ->where('OrderDetail.Status', '>', 1)
                    ->where('OrderDetail.IsOverPaymentAmount', '<>', 1)
                    ->get()
                    ->toArray();
                DB::statement('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');
                
                // Query Agreement Service (Thành công - có phiếu thu) cho ngày hiện tại
                DB::statement('SET SESSION TRANSACTION ISOLATION LEVEL READ UNCOMMITTED');
                $listAgreementService = OrderDetail::select(
                        's.WarrantyType', 
                        'OrderDetail.Quantity',
                        'OrderDetail.ServiceId', 
                        'oc.BranchId', 
                        'oc.OrderChangingId'
                    )
                    ->join('Service as s', 'OrderDetail.ServiceId', '=', 's.ServiceId')
                    ->join('OrderChanging as oc', 'OrderDetail.OrderChangingId', '=', 'oc.OrderChangingId')
                    ->whereIn('oc.BranchId', $branchIds)
                    ->where('OrderDetail.FirstReceiptTime', '>=', $currentDate . " 00:00:01")
                    ->where('OrderDetail.FirstReceiptTime', '<=', $currentDate . " 23:59:59")
                    ->where('OrderDetail.ConsultedDate', '>', '2026-01-22 00:00:00')
                    ->where('OrderDetail.IsOverPaymentAmount', '<>', 1)
                    ->get()
                    ->toArray();
                DB::statement('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');

                // Xử lý dữ liệu theo chi nhánh và loại dịch vụ
                $branchServiceData = [];

                // Xử lý Consultation (Tư vấn)
                foreach ($listConsultationService as $item) {
                    $branchId = $item['BranchId'];
                    $serviceCategory = $this->getServiceCategory($item['WarrantyType'], $item['ServiceId'], $item['OrderChangingId']);

                    if (!isset($branchServiceData[$branchId][$serviceCategory])) {
                        $branchServiceData[$branchId][$serviceCategory] = [
                            'ConsultCount' => 0,
                            'SuccessCount' => 0,
                            'Revenue' => 0,
                            'trackedServicesConsultation' => [],
                            'trackedServicesAgreement' => []
                        ];
                    }

                    // Đếm tư vấn (tránh trùng lặp cho loại T)
                    if ($serviceCategory == 'T') {
                        $serviceKey = $item['ServiceId'] . '_' . $item['OrderChangingId'];
                        if (!in_array($serviceKey, $branchServiceData[$branchId][$serviceCategory]['trackedServicesConsultation'])) {
                            $branchServiceData[$branchId][$serviceCategory]['trackedServicesConsultation'][] = $serviceKey;
                            $branchServiceData[$branchId][$serviceCategory]['ConsultCount'] += 1;
                        }
                    } else {
                        $branchServiceData[$branchId][$serviceCategory]['ConsultCount'] += 1;
                    }
                }

                // Xử lý Agreement (Thành công)
                foreach ($listAgreementService as $item) {
                    $branchId = $item['BranchId'];
                    $serviceCategory = $this->getServiceCategory($item['WarrantyType'], $item['ServiceId'], $item['OrderChangingId']);

                    if (!isset($branchServiceData[$branchId][$serviceCategory])) {
                        $branchServiceData[$branchId][$serviceCategory] = [
                            'ConsultCount' => 0,
                            'SuccessCount' => 0,
                            'Revenue' => 0,
                            'trackedServicesConsultation' => [],
                            'trackedServicesAgreement' => []
                        ];
                    }
                    
                    // Đếm thành công (tránh trùng lặp cho loại T)
                    if ($serviceCategory == 'T') {
                        $serviceKey = $item['ServiceId'] . '_' . $item['OrderChangingId'];
                        
                        if (!in_array($serviceKey, $branchServiceData[$branchId][$serviceCategory]['trackedServicesAgreement'])) {
                            $branchServiceData[$branchId][$serviceCategory]['trackedServicesAgreement'][] = $serviceKey;
                            $branchServiceData[$branchId][$serviceCategory]['SuccessCount'] += 1;
                        }
                    } else {
                        $branchServiceData[$branchId][$serviceCategory]['SuccessCount'] += 1;
                    }
                }

                // Lấy các bản ghi hiện có cho ngày này
                $existingRecords = DB::table('DashboardBranchServiceDaily')
                    ->where('SummaryDate', $currentDate)
                    ->whereIn('BranchId', $branchIds)
                    ->get()
                    ->keyBy(function($item) {
                        return $item->BranchId . '_' . $item->ServiceCategoryId;
                    })
                    ->toArray();

                $recordsToInsert = [];
                $updateCount = 0;
                
                // Chuẩn bị dữ liệu insert/update cho ngày hiện tại
                foreach ($branchServiceData as $branchId => $categories) {
                    foreach ($categories as $categoryId => $stats) {
                        $recordKey = $branchId . '_' . $categoryId;

                        $recordData = [
                            'ConsultCount' => $stats['ConsultCount'],
                            'SuccessCount' => $stats['SuccessCount'],
                            'Revenue' => 0,
                            'UpdatedDate' => $currentTime
                        ];

                        if (isset($existingRecords[$recordKey])) {
                            // Update
                            DB::table('DashboardBranchServiceDaily')
                                ->where('SummaryDate', $currentDate)
                                ->where('BranchId', $branchId)
                                ->where('ServiceCategoryId', $categoryId)
                                ->update($recordData);
                            $updateCount++;
                            
                            // Đánh dấu record này đã được xử lý
                            unset($existingRecords[$recordKey]);
                        } else {
                            // Insert
                            $recordsToInsert[] = array_merge($recordData, [
                                'SummaryDate' => $currentDate,
                                'BranchId' => $branchId,
                                'ServiceCategoryId' => $categoryId,
                                'CreatedDate' => $currentTime
                            ]);
                        }
                    }
                }

                // Update các records còn lại trong existingRecords về 0 (không có data mới)
                foreach ($existingRecords as $recordKey => $existingRecord) {
                    DB::table('DashboardBranchServiceDaily')
                        ->where('SummaryDate', $currentDate)
                        ->where('BranchId', $existingRecord->BranchId)
                        ->where('ServiceCategoryId', $existingRecord->ServiceCategoryId)
                        ->update([
                            'ConsultCount' => 0,
                            'SuccessCount' => 0,
                            'Revenue' => 0,
                            'UpdatedDate' => $currentTime
                        ]);
                    $updateCount++;
                }

                // Batch insert cho ngày hiện tại
                if (!empty($recordsToInsert)) {
                    DB::table('DashboardBranchServiceDaily')->insert($recordsToInsert);
                    $totalRecordsInserted += count($recordsToInsert);
                }

                $totalDays++;
                $startDate->addDay();
            }

            DB::commit();

            return [
                'success' => true,
                'message' => 'Data inserted successfully',
                'branches_processed' => count($branchIds),
                'days_processed' => $totalDays,
                'records_inserted' => $totalRecordsInserted
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('DashboardRepository@insertBranchServiceDaily: '.$e->getMessage());
            return [
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Xác định ServiceCategoryId dựa trên WarrantyType
     * I = Implant, P = Prosthetic, O = Orthodontic, NULL/T = Treatment (Tổng quát)
     */
    private function getServiceCategory($warrantyType, $serviceId, $orderChangingId)
    {
        switch ($warrantyType) {
            case 'I':
                return 'I'; // Implant
            case 'P':
                return 'P'; // Prosthetic (Phục hình)
            case 'O':
                return 'C'; // Orthodontic (Chỉnh nha) -> C
            case NULL:
            default:
                return 'T'; // Treatment (Tổng quát)
        }
    }

    /**
     * Insert customer rating data daily
     * Insert dữ liệu đánh giá khách hàng theo ngày vào bảng DashboardCustomerRatingDaily
     */
    public function insertCustomerRatingDaily($data)
    {
        try {
            $branchIds = $data['BranchId'] ?? [];
            $fromDate = $data['FromDate'] ?? NULL;
            $toDate = $data['ToDate'] ?? NULL;

            if(!$fromDate){
                $fromDate = Carbon::now()->format('Y-m-d');
            }

            if(!$toDate){
                $toDate = Carbon::now()->format('Y-m-d');
            }

            // Nếu không truyền BranchId, lấy tất cả chi nhánh có State = 1
            if (!is_array($branchIds) || empty($branchIds)) {
                $branchIds = Branch::where('State', 1)->pluck('BranchId')->toArray();

                if (empty($branchIds)) {
                    Log::warning('DashboardRepository@insertCustomerRatingDaily: No active branches found');
                    return ['success' => false, 'message' => 'No active branches found'];
                }
            }

            $fromTimestamp = strtotime($fromDate . " 00:00:01");
            $toTimestamp = strtotime($toDate . " 23:59:59");
            $currentTime = Carbon::now();

            DB::beginTransaction();

            // Query rating data theo chi nhánh, ngày và rating value (một lần cho tất cả)
            $ratingData = Rating::selectRaw('
                    DATE(FROM_UNIXTIME(CreatedDate)) as SummaryDate,
                    BranchId,
                    FLOOR(Value) as RatingValue,
                    COUNT(*) as RatingCount
                ')
                ->whereIn('BranchId', $branchIds)
                ->where('CreatedDate', '>=', $fromTimestamp)
                ->where('CreatedDate', '<=', $toTimestamp)
                ->whereRaw('FLOOR(Value) BETWEEN 1 AND 5')
                ->groupBy(
                    DB::raw('DATE(FROM_UNIXTIME(CreatedDate))'),
                    'BranchId',
                    DB::raw('FLOOR(Value)')
                )
                ->get()
                ->toArray();

            // Tạo danh sách các ngày
            $startDate = Carbon::parse($fromDate);
            $endDate = Carbon::parse($toDate);
            $dateList = [];
            while ($startDate->lte($endDate)) {
                $dateList[] = $startDate->format('Y-m-d');
                $startDate->addDay();
            }

            // Lấy các bản ghi hiện có
            $existingRecords = DB::table('DashboardCustomerRatingDaily')
                ->whereIn('SummaryDate', $dateList)
                ->whereIn('BranchId', $branchIds)
                ->get()
                ->keyBy(function($item) {
                    return $item->SummaryDate . '_' . $item->BranchId . '_' . $item->RatingValue;
                })
                ->toArray();

            $recordsToInsert = [];
            $updateCount = 0;

            // Xử lý dữ liệu rating
            foreach ($ratingData as $rating) {
                $summaryDate = $rating['SummaryDate'];
                $branchId = $rating['BranchId'];
                $ratingValue = $rating['RatingValue'];
                $ratingCount = $rating['RatingCount'];

                $recordKey = $summaryDate . '_' . $branchId . '_' . $ratingValue;

                $recordData = [
                    'RatingCount' => $ratingCount,
                    'UpdatedDate' => $currentTime
                ];

                if (isset($existingRecords[$recordKey])) {
                    // Update
                    DB::table('DashboardCustomerRatingDaily')
                        ->where('SummaryDate', $summaryDate)
                        ->where('BranchId', $branchId)
                        ->where('RatingValue', $ratingValue)
                        ->update($recordData);
                    $updateCount++;
                } else {
                    // Insert
                    $recordsToInsert[] = array_merge($recordData, [
                        'SummaryDate' => $summaryDate,
                        'BranchId' => $branchId,
                        'RatingValue' => $ratingValue,
                        'CreatedDate' => $currentTime
                    ]);
                }
            }

            // Batch insert
            if (!empty($recordsToInsert)) {
                $chunks = array_chunk($recordsToInsert, 500);
                foreach ($chunks as $chunk) {
                    DB::table('DashboardCustomerRatingDaily')->insert($chunk);
                }
            }

            DB::commit();

            return [
                'success' => true,
                'message' => 'Data inserted successfully',
                'branches_processed' => count($branchIds),
                'days_processed' => count($dateList),
                'inserted' => count($recordsToInsert),
                'updated' => $updateCount
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('DashboardRepository@insertCustomerRatingDaily: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Insert customer source summary data
     * Insert dữ liệu nguồn khách hàng theo ngày vào bảng DashboardCustomerSourceSummary
     */
    public function insertCustomerSourceSummary($data)
    {
        try {
            $branchIds = $data['BranchId'] ?? [];
            $fromDate = $data['FromDate'] ?? NULL;
            $toDate = $data['ToDate'] ?? NULL;

            if(!$fromDate){
                $fromDate = Carbon::now()->format('Y-m-d');
            }

            if(!$toDate){
                $toDate = Carbon::now()->format('Y-m-d');
            }

            // Nếu không truyền BranchId, lấy tất cả chi nhánh có State = 1
            if (!is_array($branchIds) || empty($branchIds)) {
                $branchIds = Branch::where('State', 1)->pluck('BranchId')->toArray();

                if (empty($branchIds)) {
                    Log::warning('DashboardRepository@insertCustomerSourceSummary: No active branches found');
                    return ['success' => false, 'message' => 'No active branches found'];
                }
            }

            $fromTimestamp = strtotime($fromDate . " 00:00:01");
            $toTimestamp = strtotime($toDate . " 23:59:59");
            $currentTime = Carbon::now();

            DB::beginTransaction();

            // Query một lần cho tất cả ngày và chi nhánh
            // Tìm appointment đầu tiên của mỗi customer
            $sub = Appointment::select(
                    'CustomerId',
                    'AtBranchId',
                    DB::raw('MIN(StartAt) as FirstStartAt')
                )
                ->whereIn('AtBranchId', $branchIds)
                ->where('AppointmentStatusId', '>', 11)
                ->where('StartAt', '>=', $fromTimestamp)
                ->where('StartAt', '<=', $toTimestamp)
                ->groupBy('CustomerId', 'AtBranchId');

            // Đếm số customer theo source, branch và ngày
            $sourceData = Appointment::joinSub($sub, 'F', function($join) {
                    $join->on('F.CustomerId', '=', 'Appointment.CustomerId')
                        ->on('F.AtBranchId', '=', 'Appointment.AtBranchId')
                        ->on('F.FirstStartAt', '=', 'Appointment.StartAt');
                })
                ->select(
                    DB::raw('DATE(FROM_UNIXTIME(Appointment.StartAt)) as SummaryDate'),
                    'Appointment.AtBranchId as BranchId',
                    'Appointment.FromCustomerChannel as CustomerSourceId',
                    DB::raw('COUNT(*) as CustomerCount')
                )
                ->whereNotNull('Appointment.FromCustomerChannel')
                ->groupBy(
                    DB::raw('DATE(FROM_UNIXTIME(Appointment.StartAt))'),
                    'Appointment.AtBranchId',
                    'Appointment.FromCustomerChannel'
                )
                ->get()
                ->toArray();

            // Tạo danh sách các ngày để tính toán
            $startDate = Carbon::parse($fromDate);
            $endDate = Carbon::parse($toDate);
            $dateList = [];
            while ($startDate->lte($endDate)) {
                $dateList[] = $startDate->format('Y-m-d');
                $startDate->addDay();
            }

            // Lấy tất cả bản ghi hiện có
            $existingRecords = DB::table('DashboardCustomerSourceSummary')
                ->whereIn('SummaryDate', $dateList)
                ->whereIn('BranchId', $branchIds)
                ->get()
                ->keyBy(function($item) {
                    return $item->SummaryDate . '_' . $item->BranchId . '_' . $item->CustomerSourceId;
                })
                ->toArray();

            $recordsToInsert = [];
            $updateCount = 0;

            // Xử lý dữ liệu
            foreach ($sourceData as $source) {
                $summaryDate = $source['SummaryDate'];
                $branchId = $source['BranchId'];
                $customerSourceId = $source['CustomerSourceId'];
                $customerCount = $source['CustomerCount'];

                $recordKey = $summaryDate . '_' . $branchId . '_' . $customerSourceId;

                $recordData = [
                    'CustomerCount' => $customerCount,
                    'UpdatedDate' => $currentTime
                ];

                if (isset($existingRecords[$recordKey])) {
                    // Update
                    DB::table('DashboardCustomerSourceSummary')
                        ->where('SummaryDate', $summaryDate)
                        ->where('BranchId', $branchId)
                        ->where('CustomerSourceId', $customerSourceId)
                        ->update($recordData);
                    $updateCount++;
                } else {
                    // Insert
                    $recordsToInsert[] = array_merge($recordData, [
                        'SummaryDate' => $summaryDate,
                        'BranchId' => $branchId,
                        'CustomerSourceId' => $customerSourceId,
                        'CreatedDate' => $currentTime
                    ]);
                }
            }

            // Batch insert
            if (!empty($recordsToInsert)) {
                // Chia nhỏ để tránh query quá lớn
                $chunks = array_chunk($recordsToInsert, 500);
                foreach ($chunks as $chunk) {
                    DB::table('DashboardCustomerSourceSummary')->insert($chunk);
                }
            }

            DB::commit();

            return [
                'success' => true,
                'message' => 'Data inserted successfully',
                'branches_processed' => count($branchIds),
                'days_processed' => count($dateList),
                'inserted' => count($recordsToInsert),
                'updated' => $updateCount
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('DashboardRepository@insertCustomerSourceSummary: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Insert staff effective data daily
     * Insert dữ liệu hiệu quả nhân viên theo ngày vào bảng DashboardStaffEffectiveDaily
     */
    public function insertStaffEffectiveDaily($data)
    {
        try {
            $branchIds = $data['BranchId'] ?? [];
            $fromDate = $data['FromDate'] ?? NULL;
            $toDate = $data['ToDate'] ?? NULL;

            if(!$fromDate){
                $fromDate = Carbon::now()->format('Y-m-d');
            }

            if(!$toDate){
                $toDate = Carbon::now()->format('Y-m-d');
            }

            // Nếu không truyền BranchId, lấy tất cả chi nhánh có State = 1
            if (!is_array($branchIds) || empty($branchIds)) {
                $branchIds = Branch::where('State', 1)->pluck('BranchId')->toArray();
                
                if (empty($branchIds)) {
                    Log::warning('DashboardRepository@insertStaffEffectiveDaily: No active branches found');
                    return ['success' => false, 'message' => 'No active branches found'];
                }
            }

            $currentTime = Carbon::now();

            DB::beginTransaction();

            // Lấy danh sách doctor IDs để loại trừ
            $doctorIds = Doctor::where('State', 1)->pluck('StaffId')->toArray();

            // Query Consult data với group by date, branch, staff, service category
            DB::statement('SET SESSION TRANSACTION ISOLATION LEVEL READ UNCOMMITTED');
            $consultData = OrderDetail::selectRaw("
                    DATE(OrderDetail.ConsultedDate) as SummaryDate,
                    OrderDetail.ConsultedBranchId as BranchId,
                    OrderDetail.ConsultingStaffId as StaffId,
                    CASE
                        WHEN s.WarrantyType = 'I' THEN 'I'
                        WHEN s.WarrantyType = 'P' THEN 'P'
                        WHEN s.WarrantyType = 'O' THEN 'C'
                        ELSE 'T'
                    END AS ServiceCategoryId,
                    CASE
                        WHEN s.WarrantyType IS NULL OR s.WarrantyType = 'G'
                            THEN CONCAT('S_', s.ServiceId, '_', OrderDetail.OrderChangingId)
                        ELSE s.WarrantyType
                    END AS GroupKey,
                    COUNT(*) as ConsultCount
                ")
                ->join('Service as s', 's.ServiceId', '=', 'OrderDetail.ServiceId')
                ->whereIn('OrderDetail.ConsultedBranchId', $branchIds)
                ->whereBetween('OrderDetail.ConsultedDate', [$fromDate . ' 00:00:00', $toDate . ' 23:59:59'])
                ->where('OrderDetail.Status', '>', 1)
                ->whereNotNull('OrderDetail.ConsultingStaffId')
                ->whereNotIn('OrderDetail.ConsultingStaffId', $doctorIds)
                ->where('OrderDetail.IsOverPaymentAmount', '<>', 1)
                ->groupBy(
                    DB::raw('DATE(OrderDetail.ConsultedDate)'),
                    'OrderDetail.ConsultedBranchId',
                    'OrderDetail.ConsultingStaffId',
                    DB::raw("CASE
                        WHEN s.WarrantyType = 'I' THEN 'I'
                        WHEN s.WarrantyType = 'P' THEN 'P'
                        WHEN s.WarrantyType = 'O' THEN 'C'
                        ELSE 'T'
                    END"),
                    DB::raw("CASE
                        WHEN s.WarrantyType IS NULL OR s.WarrantyType = 'G'
                            THEN CONCAT('S_', s.ServiceId, '_', OrderDetail.OrderChangingId)
                        ELSE s.WarrantyType
                    END")
                )
                ->get()
                ->toArray();
            DB::statement('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');

            // Query Success data
            DB::statement('SET SESSION TRANSACTION ISOLATION LEVEL READ UNCOMMITTED');
            $successData = OrderDetail::selectRaw("
                    DATE(OrderDetail.FirstReceiptTime) as SummaryDate,
                    oc.BranchId,
                    OrderDetail.ConsultingStaffId as StaffId,
                    CASE
                        WHEN s.WarrantyType = 'I' THEN 'I'
                        WHEN s.WarrantyType = 'P' THEN 'P'
                        WHEN s.WarrantyType = 'O' THEN 'C'
                        ELSE 'T'
                    END AS ServiceCategoryId,
                    CASE
                        WHEN s.WarrantyType IS NULL OR s.WarrantyType = 'G'
                            THEN CONCAT('S_', s.ServiceId, '_', OrderDetail.OrderChangingId)
                        ELSE s.WarrantyType
                    END AS GroupKey,
                    COUNT(*) as SuccessCount
                ")
                ->join('Service as s', 's.ServiceId', '=', 'OrderDetail.ServiceId')
                ->join('OrderChanging as oc', 'oc.OrderChangingId', '=', 'OrderDetail.OrderChangingId')
                ->whereIn('oc.BranchId', $branchIds)
                ->whereBetween('OrderDetail.FirstReceiptTime', [$fromDate . ' 00:00:00', $toDate . ' 23:59:59'])
                ->where('OrderDetail.ConsultedDate', '>', '2026-01-22 00:00:00')
                ->whereNotNull('OrderDetail.ConsultingStaffId')
                ->whereNotIn('OrderDetail.ConsultingStaffId', $doctorIds)
                ->where('OrderDetail.IsOverPaymentAmount', '<>', 1)
                ->groupBy(
                    DB::raw('DATE(OrderDetail.FirstReceiptTime)'),
                    'oc.BranchId',
                    'OrderDetail.ConsultingStaffId',
                    DB::raw("CASE
                        WHEN s.WarrantyType = 'I' THEN 'I'
                        WHEN s.WarrantyType = 'P' THEN 'P'
                        WHEN s.WarrantyType = 'O' THEN 'C'
                        ELSE 'T'
                    END"),
                    DB::raw("CASE
                        WHEN s.WarrantyType IS NULL OR s.WarrantyType = 'G'
                            THEN CONCAT('S_', s.ServiceId, '_', OrderDetail.OrderChangingId)
                        ELSE s.WarrantyType
                    END")
                )
                ->get()
                ->toArray();
            DB::statement('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');

            // Query Revenue data - group theo cả ServiceCategoryId
            DB::statement('SET SESSION TRANSACTION ISOLATION LEVEL READ UNCOMMITTED');
            $revenueRaw = DB::select("
                SELECT
                    SummaryDate,
                    BranchId,
                    StaffId,
                    ServiceCategoryId,
                    SUM(Revenue) as Revenue
                FROM (
                    SELECT
                        DATE(odft.CreatedDate) as SummaryDate,
                        COALESCE(od.ConsultedBranchId, odft.ConsultedBranchId) as BranchId,
                        odft.ConsultingStaffId as StaffId,
                        CASE
                            WHEN s.WarrantyType = 'I' THEN 'I'
                            WHEN s.WarrantyType = 'P' THEN 'P'
                            WHEN s.WarrantyType = 'O' THEN 'C'
                            ELSE 'T'
                        END AS ServiceCategoryId,
                        COALESCE(odft.ReceiptAmount, 0)
                        + COALESCE(odft.TransferAmount, 0)
                        - COALESCE(odft.ExpenditureAmount, 0) as Revenue
                    FROM OrderDetailFinancialTrans odft
                    JOIN OrderDetail od ON od.OrderDetailId = odft.OrderDetailId
                    JOIN Service s ON s.ServiceId = od.ServiceId
                    WHERE odft.CreatedDate BETWEEN ? AND ?
                      AND odft.ObjectType IN ('Receipt','Expenditure','TransferAmountService')
                      AND od.IsOverPaymentAmount <> 1
                      AND odft.ConsultingStaffId IS NOT NULL
                      AND odft.ConsultingStaffId NOT IN (" . implode(',', array_fill(0, count($doctorIds), '?')) . ")
                      AND COALESCE(od.ConsultedBranchId, odft.ConsultedBranchId) IN (" . implode(',', array_fill(0, count($branchIds), '?')) . ")
                ) AS sub
                GROUP BY SummaryDate, BranchId, StaffId, ServiceCategoryId
            ", array_merge(
                [$fromDate . ' 00:00:00', $toDate . ' 23:59:59'],
                $doctorIds,
                $branchIds
            ));

            $revenueData = [];
            foreach ($revenueRaw as $item) {
                $key = $item->SummaryDate . '_' . $item->BranchId . '_' . $item->StaffId . '_' . $item->ServiceCategoryId;
                $revenueData[$key] = (array) $item;
            }
            DB::statement('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');

            // Xử lý dữ liệu consult - group by unique key để tránh trùng lặp
            $consultGrouped = [];
            foreach ($consultData as $item) {
                $key = $item['SummaryDate'] . '_' . $item['BranchId'] . '_' . $item['StaffId'] . '_' . $item['ServiceCategoryId'];
                if (!isset($consultGrouped[$key])) {
                    $consultGrouped[$key] = [
                        'SummaryDate' => $item['SummaryDate'],
                        'BranchId' => $item['BranchId'],
                        'StaffId' => $item['StaffId'],
                        'ServiceCategoryId' => $item['ServiceCategoryId'],
                        'ConsultCount' => 0,
                        'trackedGroups' => []
                    ];
                }
                
                // Tránh đếm trùng cho loại T (General)
                if ($item['ServiceCategoryId'] == 'T') {
                    if (!in_array($item['GroupKey'], $consultGrouped[$key]['trackedGroups'])) {
                        $consultGrouped[$key]['trackedGroups'][] = $item['GroupKey'];
                        $consultGrouped[$key]['ConsultCount'] += 1; // Chỉ đếm 1 cho mỗi GroupKey
                    }
                } else {
                    $consultGrouped[$key]['ConsultCount'] += $item['ConsultCount'];
                }
            }

            // Xử lý dữ liệu success - tương tự
            $successGrouped = [];
            foreach ($successData as $item) {
                $key = $item['SummaryDate'] . '_' . $item['BranchId'] . '_' . $item['StaffId'] . '_' . $item['ServiceCategoryId'];
                if (!isset($successGrouped[$key])) {
                    $successGrouped[$key] = [
                        'SummaryDate' => $item['SummaryDate'],
                        'BranchId' => $item['BranchId'],
                        'StaffId' => $item['StaffId'],
                        'ServiceCategoryId' => $item['ServiceCategoryId'],
                        'SuccessCount' => 0,
                        'trackedGroups' => []
                    ];
                }
                
                if ($item['ServiceCategoryId'] == 'T') {
                    if (!in_array($item['GroupKey'], $successGrouped[$key]['trackedGroups'])) {
                        $successGrouped[$key]['trackedGroups'][] = $item['GroupKey'];
                        $successGrouped[$key]['SuccessCount'] += 1; // Chỉ đếm 1 cho mỗi GroupKey
                    }
                } else {
                    $successGrouped[$key]['SuccessCount'] += $item['SuccessCount'];
                }
            }

            // Merge consult và success data
            $allKeys = array_unique(array_merge(array_keys($consultGrouped), array_keys($successGrouped)));

            // Thêm revenue keys vào allKeys (revenue có thể có StaffId/ServiceCategoryId không có trong consult/success)
            foreach ($revenueData as $revenueKey => $revenueItem) {
                if (!in_array($revenueKey, $allKeys)) {
                    $allKeys[] = $revenueKey;
                }
            }
            $allKeys = array_unique($allKeys);
            
            // Tạo danh sách các ngày
            $startDate = Carbon::parse($fromDate);
            $endDate = Carbon::parse($toDate);
            $dateList = [];
            while ($startDate->lte($endDate)) {
                $dateList[] = $startDate->format('Y-m-d');
                $startDate->addDay();
            }

            // Lấy existing records
            $existingRecords = DB::table('DashboardStaffEffectiveDaily')
                ->whereIn('SummaryDate', $dateList)
                ->whereIn('BranchId', $branchIds)
                ->get()
                ->keyBy(function($item) {
                    return $item->SummaryDate . '_' . $item->BranchId . '_' . $item->StaffId . '_' . $item->ServiceCategoryId;
                })
                ->toArray();

            $recordsToInsert = [];
            $updateCount = 0;

            foreach ($allKeys as $key) {
                $consult = $consultGrouped[$key] ?? null;
                $success = $successGrouped[$key] ?? null;
                
                $summaryDate       = $consult['SummaryDate']       ?? $success['SummaryDate']       ?? ($revenueData[$key]['SummaryDate'] ?? null);
                $branchId          = $consult['BranchId']          ?? $success['BranchId']          ?? ($revenueData[$key]['BranchId'] ?? null);
                $staffId           = $consult['StaffId']           ?? $success['StaffId']           ?? ($revenueData[$key]['StaffId'] ?? null);
                $serviceCategoryId = $consult['ServiceCategoryId'] ?? $success['ServiceCategoryId'] ?? ($revenueData[$key]['ServiceCategoryId'] ?? null);

                // Bỏ qua nếu thiếu thông tin
                if (!$summaryDate || !$branchId || !$staffId || !$serviceCategoryId) {
                    continue;
                }

                $revenueKey = $summaryDate . '_' . $branchId . '_' . $staffId . '_' . $serviceCategoryId;
                $revenue = isset($revenueData[$revenueKey]) ? $revenueData[$revenueKey]['Revenue'] : 0;
                
                $recordData = [
                    'ConsultCount' => $consult['ConsultCount'] ?? 0,
                    'SuccessCount' => $success['SuccessCount'] ?? 0,
                    'Revenue' => $revenue,
                    'UpdatedDate' => $currentTime
                ];

                if (isset($existingRecords[$key])) {
                    // Update
                    DB::table('DashboardStaffEffectiveDaily')
                        ->where('SummaryDate', $summaryDate)
                        ->where('BranchId', $branchId)
                        ->where('StaffId', $staffId)
                        ->where('ServiceCategoryId', $serviceCategoryId)
                        ->update($recordData);
                    $updateCount++;
                } else {
                    // Insert
                    $recordsToInsert[] = array_merge($recordData, [
                        'SummaryDate' => $summaryDate,
                        'BranchId' => $branchId,
                        'StaffId' => $staffId,
                        'ServiceCategoryId' => $serviceCategoryId,
                        'CreatedDate' => $currentTime
                    ]);
                }
            }

            // Batch insert
            if (!empty($recordsToInsert)) {
                $chunks = array_chunk($recordsToInsert, 500);
                foreach ($chunks as $chunk) {
                    DB::table('DashboardStaffEffectiveDaily')->insert($chunk);
                }
            }

            DB::commit();

            return [
                'success' => true,
                'message' => 'Data inserted successfully',
                'branches_processed' => count($branchIds),
                'days_processed' => count($dateList),
                'inserted' => count($recordsToInsert),
                'updated' => $updateCount
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('DashboardRepository@insertStaffEffectiveDaily: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Insert doctor effective data daily
     * Insert dữ liệu hiệu quả bác sĩ theo ngày vào bảng DashboardStaffEffectiveDaily
     */
    public function insertDoctorEffectiveDaily($data)
    {
        try {
            $branchIds = $data['BranchId'] ?? [];
            $fromDate = $data['FromDate'] ?? NULL;
            $toDate = $data['ToDate'] ?? NULL;

            if(!$fromDate){
                $fromDate = Carbon::now()->format('Y-m-d');
            }

            if(!$toDate){
                $toDate = Carbon::now()->format('Y-m-d');
            }

            // Nếu không truyền BranchId, lấy tất cả chi nhánh có State = 1
            if (!is_array($branchIds) || empty($branchIds)) {
                $branchIds = Branch::where('State', 1)->pluck('BranchId')->toArray();
                
                if (empty($branchIds)) {
                    Log::warning('DashboardRepository@insertDoctorEffectiveDaily: No active branches found');
                    return ['success' => false, 'message' => 'No active branches found'];
                }
            }

            $fromTimestamp = strtotime($fromDate . " 00:00:01");
            $toTimestamp = strtotime($toDate . " 23:59:59");
            $currentTime = Carbon::now();

            DB::beginTransaction();

            // Lấy danh sách doctor IDs
            $doctorIds = Doctor::where('State', 1)->pluck('StaffId')->toArray();
            
            if (empty($doctorIds)) {
                DB::rollBack();
                return ['success' => false, 'message' => 'No active doctors found'];
            }

            // Query Consultation data (dùng OrderChanging.ChangedBy cho doctor)
            DB::statement('SET SESSION TRANSACTION ISOLATION LEVEL READ UNCOMMITTED');
            $consultData = OrderChanging::selectRaw("
                    DATE(FROM_UNIXTIME(OrderChanging.ChangedAt)) as SummaryDate,
                    OrderChanging.BranchId,
                    OrderChanging.ChangedBy as StaffId,
                    CASE
                        WHEN s.WarrantyType = 'I' THEN 'I'
                        WHEN s.WarrantyType = 'P' THEN 'P'
                        WHEN s.WarrantyType = 'O' THEN 'C'
                        ELSE 'T'
                    END AS ServiceCategoryId,
                    CASE
                        WHEN s.WarrantyType IS NULL OR s.WarrantyType = 'G'
                            THEN CONCAT('S_', s.ServiceId, '_', od.OrderChangingId)
                        ELSE s.WarrantyType
                    END AS GroupKey,
                    COUNT(*) as ConsultCount
                ")
                ->join('OrderDetail as od', 'od.OrderChangingId', '=', 'OrderChanging.OrderChangingId')
                ->join('Service as s', 's.ServiceId', '=', 'od.ServiceId')
                ->whereIn('OrderChanging.BranchId', $branchIds)
                ->whereIn('OrderChanging.ChangedBy', $doctorIds)
                ->where('od.Status', '>', 1)
                ->where('od.IsOverPaymentAmount', '<>', 1)
                ->where('OrderChanging.ChangedAt', '>=', $fromTimestamp)
                ->where('OrderChanging.ChangedAt', '<=', $toTimestamp)
                ->groupBy(
                    DB::raw('DATE(FROM_UNIXTIME(OrderChanging.ChangedAt))'),
                    'OrderChanging.BranchId',
                    'OrderChanging.ChangedBy',
                    DB::raw("CASE
                        WHEN s.WarrantyType = 'I' THEN 'I'
                        WHEN s.WarrantyType = 'P' THEN 'P'
                        WHEN s.WarrantyType = 'O' THEN 'C'
                        ELSE 'T'
                    END"),
                    DB::raw("CASE
                        WHEN s.WarrantyType IS NULL OR s.WarrantyType = 'G'
                            THEN CONCAT('S_', s.ServiceId, '_', od.OrderChangingId)
                        ELSE s.WarrantyType
                    END")
                )
                ->get()
                ->toArray();
            DB::statement('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');

            // Query Success data (dùng FirstReceiptTime)
            DB::statement('SET SESSION TRANSACTION ISOLATION LEVEL READ UNCOMMITTED');
            $successData = OrderChanging::selectRaw("
                    DATE(od.FirstReceiptTime) as SummaryDate,
                    OrderChanging.BranchId,
                    OrderChanging.ChangedBy as StaffId,
                    CASE
                        WHEN s.WarrantyType = 'I' THEN 'I'
                        WHEN s.WarrantyType = 'P' THEN 'P'
                        WHEN s.WarrantyType = 'O' THEN 'C'
                        ELSE 'T'
                    END AS ServiceCategoryId,
                    CASE
                        WHEN s.WarrantyType IS NULL OR s.WarrantyType = 'G'
                            THEN CONCAT('S_', s.ServiceId, '_', od.OrderChangingId)
                        ELSE s.WarrantyType
                    END AS GroupKey,
                    COUNT(*) as SuccessCount
                ")
                ->join('OrderDetail as od', 'od.OrderChangingId', '=', 'OrderChanging.OrderChangingId')
                ->join('Service as s', 's.ServiceId', '=', 'od.ServiceId')
                ->whereIn('OrderChanging.BranchId', $branchIds)
                ->whereIn('OrderChanging.ChangedBy', $doctorIds)
                ->where('od.ConsultedDate', '>', '2026-01-22 00:00:00')
                ->where('od.IsOverPaymentAmount', '<>', 1)
                ->whereBetween('od.FirstReceiptTime', [$fromDate . ' 00:00:00', $toDate . ' 23:59:59'])
                ->groupBy(
                    DB::raw('DATE(od.FirstReceiptTime)'),
                    'OrderChanging.BranchId',
                    'OrderChanging.ChangedBy',
                    DB::raw("CASE
                        WHEN s.WarrantyType = 'I' THEN 'I'
                        WHEN s.WarrantyType = 'P' THEN 'P'
                        WHEN s.WarrantyType = 'O' THEN 'C'
                        ELSE 'T'
                    END"),
                    DB::raw("CASE
                        WHEN s.WarrantyType IS NULL OR s.WarrantyType = 'G'
                            THEN CONCAT('S_', s.ServiceId, '_', od.OrderChangingId)
                        ELSE s.WarrantyType
                    END")
                )
                ->get()
                ->toArray();
            DB::statement('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');

            // Query Revenue data (dùng AllocatedRevenueTracking)
            DB::statement('SET SESSION TRANSACTION ISOLATION LEVEL READ UNCOMMITTED');
            $revenueData = AllocatedRevenueTracking::selectRaw("
                    DATE(TreatmentCompletedDate) as SummaryDate,
                    BranchId,
                    CreatedBy as StaffId,
                    SUM(
                        CASE TrackingType
                            WHEN 1 THEN KIMRevenueAmount
                            WHEN 2 THEN -KIMRevenueAmount
                            ELSE 0
                        END
                    ) as Revenue
                ")
                ->whereIn('BranchId', $branchIds)
                ->whereIn('CreatedBy', $doctorIds)
                ->whereBetween('TreatmentCompletedDate', [$fromDate . ' 00:00:00', $toDate . ' 23:59:59'])
                ->groupBy(
                    DB::raw('DATE(TreatmentCompletedDate)'),
                    'BranchId',
                    'CreatedBy'
                )
                ->get()
                ->keyBy(function($item) {
                    return $item->SummaryDate . '_' . $item->BranchId . '_' . $item->StaffId;
                })
                ->toArray();
            DB::statement('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');

            // Xử lý dữ liệu consult - tránh trùng lặp
            $consultGrouped = [];
            foreach ($consultData as $item) {
                $key = $item['SummaryDate'] . '_' . $item['BranchId'] . '_' . $item['StaffId'] . '_' . $item['ServiceCategoryId'];
                if (!isset($consultGrouped[$key])) {
                    $consultGrouped[$key] = [
                        'SummaryDate' => $item['SummaryDate'],
                        'BranchId' => $item['BranchId'],
                        'StaffId' => $item['StaffId'],
                        'ServiceCategoryId' => $item['ServiceCategoryId'],
                        'ConsultCount' => 0,
                        'trackedGroups' => []
                    ];
                }
                
                // Tránh đếm trùng cho loại T
                if ($item['ServiceCategoryId'] == 'T') {
                    if (!in_array($item['GroupKey'], $consultGrouped[$key]['trackedGroups'])) {
                        $consultGrouped[$key]['trackedGroups'][] = $item['GroupKey'];
                        $consultGrouped[$key]['ConsultCount'] += 1;
                    }
                } else {
                    $consultGrouped[$key]['ConsultCount'] += $item['ConsultCount'];
                }
            }

            // Xử lý dữ liệu success
            $successGrouped = [];
            foreach ($successData as $item) {
                $key = $item['SummaryDate'] . '_' . $item['BranchId'] . '_' . $item['StaffId'] . '_' . $item['ServiceCategoryId'];
                if (!isset($successGrouped[$key])) {
                    $successGrouped[$key] = [
                        'SummaryDate' => $item['SummaryDate'],
                        'BranchId' => $item['BranchId'],
                        'StaffId' => $item['StaffId'],
                        'ServiceCategoryId' => $item['ServiceCategoryId'],
                        'SuccessCount' => 0,
                        'trackedGroups' => []
                    ];
                }
                
                if ($item['ServiceCategoryId'] == 'T') {
                    if (!in_array($item['GroupKey'], $successGrouped[$key]['trackedGroups'])) {
                        $successGrouped[$key]['trackedGroups'][] = $item['GroupKey'];
                        $successGrouped[$key]['SuccessCount'] += 1;
                    }
                } else {
                    $successGrouped[$key]['SuccessCount'] += $item['SuccessCount'];
                }
            }

            // Merge data - bao gồm cả revenue keys để không bỏ sót
            $allKeys = array_unique(array_merge(array_keys($consultGrouped), array_keys($successGrouped)));

            // Thêm keys từ revenueData vào allKeys (revenue có thể có StaffId không có trong consult/success)
            foreach ($revenueData as $revenueKey => $revenueItem) {
                // Lấy trực tiếp từ revenueItem thay vì parse key
                $summaryDate = $revenueItem['SummaryDate'];
                $branchId    = $revenueItem['BranchId'];
                $staffId     = $revenueItem['StaffId'];

                // Kiểm tra xem có key nào với StaffId này không
                $hasKey = false;
                foreach ($allKeys as $k) {
                    if (strpos($k, $summaryDate . '_' . $branchId . '_' . $staffId . '_') === 0) {
                        $hasKey = true;
                        break;
                    }
                }
                // Nếu không có key nào, thêm key với ServiceCategoryId = 'T'
                if (!$hasKey) {
                    $allKeys[] = $summaryDate . '_' . $branchId . '_' . $staffId . '_T';
                }
            }
            $allKeys = array_unique($allKeys);
            
            // Tạo danh sách các ngày
            $startDate = Carbon::parse($fromDate);
            $endDate = Carbon::parse($toDate);
            $dateList = [];
            while ($startDate->lte($endDate)) {
                $dateList[] = $startDate->format('Y-m-d');
                $startDate->addDay();
            }

            // Lấy existing records
            $existingRecords = DB::table('DashboardStaffEffectiveDaily')
                ->whereIn('SummaryDate', $dateList)
                ->whereIn('BranchId', $branchIds)
                ->whereIn('StaffId', $doctorIds)
                ->get()
                ->keyBy(function($item) {
                    return $item->SummaryDate . '_' . $item->BranchId . '_' . $item->StaffId . '_' . $item->ServiceCategoryId;
                })
                ->toArray();

            $recordsToInsert = [];
            $updateCount = 0;
            $revenueAssigned = []; // Track revenue đã gán để tránh dup

            foreach ($allKeys as $key) {
                $consult = $consultGrouped[$key] ?? null;
                $success = $successGrouped[$key] ?? null;
                
                $summaryDate       = $consult['SummaryDate']       ?? $success['SummaryDate']       ?? null;
                $branchId          = $consult['BranchId']          ?? $success['BranchId']          ?? null;
                $staffId           = $consult['StaffId']           ?? $success['StaffId']           ?? null;
                $serviceCategoryId = $consult['ServiceCategoryId'] ?? $success['ServiceCategoryId'] ?? null;

                // Nếu consult và success đều null, lấy từ revenueData
                if (!$summaryDate || !$branchId || !$staffId) {
                    foreach ($revenueData as $revenueItem) {
                        $rKey = $revenueItem['SummaryDate'] . '_' . $revenueItem['BranchId'] . '_' . $revenueItem['StaffId'] . '_T';
                        if ($rKey === $key) {
                            $summaryDate       = $revenueItem['SummaryDate'];
                            $branchId          = $revenueItem['BranchId'];
                            $staffId           = $revenueItem['StaffId'];
                            $serviceCategoryId = 'T';
                            break;
                        }
                    }
                }

                // Bỏ qua nếu vẫn thiếu thông tin
                if (!$summaryDate || !$branchId || !$staffId || !$serviceCategoryId) {
                    continue;
                }
                
                // Revenue của doctor không phân theo ServiceCategoryId
                // Chỉ gán revenue 1 lần cho StaffId, các ServiceCategoryId còn lại = 0
                $revenueKey = $summaryDate . '_' . $branchId . '_' . $staffId;
                if (!isset($revenueAssigned[$revenueKey])) {
                    $revenue = isset($revenueData[$revenueKey]) ? $revenueData[$revenueKey]['Revenue'] : 0;
                    $revenueAssigned[$revenueKey] = true;
                } else {
                    $revenue = 0;
                }
                
                $recordData = [
                    'ConsultCount' => $consult['ConsultCount'] ?? 0,
                    'SuccessCount' => $success['SuccessCount'] ?? 0,
                    'Revenue' => $revenue,
                    'UpdatedDate' => $currentTime
                ];

                if (isset($existingRecords[$key])) {
                    // Update
                    DB::table('DashboardStaffEffectiveDaily')
                        ->where('SummaryDate', $summaryDate)
                        ->where('BranchId', $branchId)
                        ->where('StaffId', $staffId)
                        ->where('ServiceCategoryId', $serviceCategoryId)
                        ->update($recordData);
                    $updateCount++;
                } else {
                    // Insert
                    $recordsToInsert[] = array_merge($recordData, [
                        'SummaryDate' => $summaryDate,
                        'BranchId' => $branchId,
                        'StaffId' => $staffId,
                        'ServiceCategoryId' => $serviceCategoryId,
                        'CreatedDate' => $currentTime
                    ]);
                }
            }

            // Batch insert
            if (!empty($recordsToInsert)) {
                $chunks = array_chunk($recordsToInsert, 500);
                foreach ($chunks as $chunk) {
                    DB::table('DashboardStaffEffectiveDaily')->insert($chunk);
                }
            }

            DB::commit();

            return [
                'success' => true,
                'message' => 'Data inserted successfully',
                'branches_processed' => count($branchIds),
                'days_processed' => count($dateList),
                'inserted' => count($recordsToInsert),
                'updated' => $updateCount
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('DashboardRepository@insertDoctorEffectiveDaily: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Refresh tất cả dữ liệu dashboard
     * Gọi tất cả các insert functions theo thứ tự
     */
    public function refreshDashboardData($data)
    {
        try {
            $fromDate = $data['FromDate'] ?? NULL;
            $toDate = $data['ToDate'] ?? NULL;
            $branchIds = $data['BranchId'] ?? [];

            if (!$fromDate || !$toDate) {
                return [
                    'success' => false,
                    'message' => 'FromDate and ToDate are required'
                ];
            }

            $results = [];
            $startTime = microtime(true);

            // 1. Insert Branch Daily
            Log::info('RefreshDashboardData: Starting insertBranchDaily');
            $result1 = $this->insertBranchDaily($data);
            $results['insertBranchDaily'] = $result1;

            // 2. Insert Branch Service Daily
            Log::info('RefreshDashboardData: Starting insertBranchServiceDaily');
            $result2 = $this->insertBranchServiceDaily($data);
            $results['insertBranchServiceDaily'] = $result2;

            // 3. Insert Customer Rating Daily
            Log::info('RefreshDashboardData: Starting insertCustomerRatingDaily');
            $result3 = $this->insertCustomerRatingDaily($data);
            $results['insertCustomerRatingDaily'] = $result3;

            // 4. Insert Customer Source Summary
            Log::info('RefreshDashboardData: Starting insertCustomerSourceSummary');
            $result4 = $this->insertCustomerSourceSummary($data);
            $results['insertCustomerSourceSummary'] = $result4;

            // 5. Insert Staff Effective Daily
            Log::info('RefreshDashboardData: Starting insertStaffEffectiveDaily');
            $result5 = $this->insertStaffEffectiveDaily($data);
            $results['insertStaffEffectiveDaily'] = $result5;

            // 6. Insert Doctor Effective Daily
            Log::info('RefreshDashboardData: Starting insertDoctorEffectiveDaily');
            $result6 = $this->insertDoctorEffectiveDaily($data);
            $results['insertDoctorEffectiveDaily'] = $result6;

            $endTime = microtime(true);
            $executionTime = round($endTime - $startTime, 2);

            return [
                'success' => true,
                'message' => 'All dashboard data refreshed successfully',
                'execution_time' => $executionTime . ' seconds',
                'from_date' => $fromDate,
                'to_date' => $toDate,
                'branches' => !empty($branchIds) ? count($branchIds) : 'all',
                'details' => $results
            ];

        } catch (\Exception $e) {
            Log::error('DashboardRepository@refreshDashboardData: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ];
        }
    }

    public function getPromotionByService(array $data)
    {
        $fromDate  = $data['FromDate'] ?? Carbon::now()->format('Y-m-d');
        $toDate    = $data['ToDate']   ?? Carbon::now()->format('Y-m-d');
        $branchIds = $data['BranchIds'] ?? [];
        $today     = Carbon::now()->format('Y-m-d');

        // Label map cho ServiceCategoryId
        $serviceLabels = [
            'O' => ['Name' => 'Niềng răng',  'Color' => '#3B82F6'],
            'I' => ['Name' => 'Implant',     'Color' => '#10B981'],
            'P' => ['Name' => 'Răng sứ',     'Color' => '#F59E0B'],
            'T' => ['Name' => 'Tổng quát',   'Color' => '#EF4444'],
        ];

        // Khởi tạo serviceMap với giá trị ban đầu cho tất cả ServiceCategoryId
        $serviceMap = [];
        foreach ($serviceLabels as $categoryId => $label) {
            $serviceMap[$categoryId] = [
                'ServiceCategoryId'   => $categoryId,
                'ServiceCategoryName' => $label['Name'],
                'Color'               => $label['Color'],
                'UsageCount'          => 0,
                'TotalService'        => 0,
                'TotalDiscountAmount' => 0.0,
                'UsagePercent'        => 0.0,
            ];
        }

        $oldDates  = [];
        $hasToday  = false;
        $startDate = Carbon::parse($fromDate);
        $endDate   = Carbon::parse($toDate);
        while ($startDate->lte($endDate)) {
            $d = $startDate->format('Y-m-d');
            if ($d === $today) {
                $hasToday = true;
            } else {
                $oldDates[] = $d;
            }
            $startDate->addDay();
        }

        // Ngày cũ: lấy từ bảng pre-aggregated
        if (!empty($oldDates)) {
            $query = DB::table('DashboardPromotionEfficencyByService')
                ->whereIn('SummaryDate', $oldDates)
                ->selectRaw('ServiceCategoryId, SUM(UsageCount) as UsageCount, SUM(TotalService) as TotalService, SUM(TotalDiscountAmount) as TotalDiscountAmount');

            if (!empty($branchIds)) {
                $query->whereIn('BranchId', $branchIds);
            }

            $oldRows = $query->groupBy('ServiceCategoryId')->get();

            foreach ($oldRows as $row) {
                $cat = $row->ServiceCategoryId;
                if (isset($serviceMap[$cat])) {
                    $serviceMap[$cat]['UsageCount']          += (int) $row->UsageCount;
                    $serviceMap[$cat]['TotalService']        += (int) $row->TotalService;
                    $serviceMap[$cat]['TotalDiscountAmount'] += (float) $row->TotalDiscountAmount;
                }
            }
        }

        // Ngày hôm nay: query trực tiếp
        if ($hasToday) {
            DB::statement('SET SESSION TRANSACTION ISOLATION LEVEL READ UNCOMMITTED');

            $todayQuery = DB::table('PromotionOrderDetail as pod')
                ->join('OrderDetail as od', 'od.OrderDetailId', '=', 'pod.OrderDetailId')
                ->leftJoin('Service as s', 's.ServiceId', '=', 'od.ServiceId')
                ->whereBetween('od.FirstTreatmentTime', [$today . ' 00:00:00', $today . ' 23:59:59'])
                ->where('pod.State', 1)
                ->selectRaw("
                    CASE WHEN s.WarrantyType = 'I' THEN 'I' WHEN s.WarrantyType = 'O' THEN 'O' WHEN s.WarrantyType = 'P' THEN 'P' ELSE 'T' END AS ServiceCategoryId,
                    COUNT(DISTINCT CONCAT(pod.CustomerId, '_', pod.PromotionId)) as UsageCount,
                    COUNT(DISTINCT od.ServiceId) as TotalService,
                    SUM(pod.DiscountAmount) as TotalDiscountAmount
                ");

            if (!empty($branchIds)) {
                $todayQuery->whereIn('od.ConsultedBranchId', $branchIds);
            }

            $todayRows = $todayQuery
                ->groupBy(DB::raw("CASE WHEN s.WarrantyType = 'I' THEN 'I' WHEN s.WarrantyType = 'O' THEN 'O' WHEN s.WarrantyType = 'P' THEN 'P' ELSE 'T' END"))
                ->get();

            DB::statement('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');

            foreach ($todayRows as $row) {
                $cat = $row->ServiceCategoryId;
                if (isset($serviceMap[$cat])) {
                    $serviceMap[$cat]['UsageCount']          += (int) $row->UsageCount;
                    $serviceMap[$cat]['TotalService']        += (int) $row->TotalService;
                    $serviceMap[$cat]['TotalDiscountAmount'] += (float) $row->TotalDiscountAmount;
                }
            }
        }

        // Tính UsagePercent = TotalDiscountAmount / tổng * 100
        $grandTotal = array_sum(array_column($serviceMap, 'TotalDiscountAmount'));
        foreach ($serviceMap as &$item) {
            $item['UsagePercent'] = $grandTotal > 0
                ? round(($item['TotalDiscountAmount'] / $grandTotal) * 100, 2)
                : 0.0;
        }
        unset($item);

        // Tổng quan - TotalService là tổng của TotalService từ tất cả Items
        $summary = [
            'TotalUsageCount'     => array_sum(array_column($serviceMap, 'UsageCount')),
            'TotalDiscountAmount' => $grandTotal,
            'TotalService'        => array_sum(array_column($serviceMap, 'TotalService')),
        ];

        // Sort theo TotalDiscountAmount DESC để hiển thị các loại có giá trị cao nhất trước
        usort($serviceMap, function ($a, $b) {
            return $b['TotalDiscountAmount'] <=> $a['TotalDiscountAmount'];
        });

        return [
            'Summary' => $summary,
            'Items'   => array_values($serviceMap),
        ];
    }

    public function getPromotionByType(array $data)
    {
        $fromDate  = $data['FromDate'] ?? Carbon::now()->format('Y-m-d');
        $toDate    = $data['ToDate']   ?? Carbon::now()->format('Y-m-d');
        $branchIds = $data['BranchIds'] ?? [];
        $today     = Carbon::now()->format('Y-m-d');

        // Tách ngày hôm nay và ngày cũ
        $oldDates  = [];
        $hasToday  = false;
        $startDate = Carbon::parse($fromDate);
        $endDate   = Carbon::parse($toDate);
        while ($startDate->lte($endDate)) {
            $d = $startDate->format('Y-m-d');
            if ($d === $today) {
                $hasToday = true;
            } else {
                $oldDates[] = $d;
            }
            $startDate->addDay();
        }

        // Map PromotionTypeId → data
        $typeMap = [];

        // Ngày cũ: lấy từ bảng pre-aggregated
        if (!empty($oldDates)) {
            $query = DB::table('DashboardPromotionEfficencyByType as dpt')
                ->join('PromotionType as pt', 'pt.PromotionTypeId', '=', 'dpt.PromotionTypeId')
                ->whereIn('dpt.SummaryDate', $oldDates)
                ->selectRaw('dpt.PromotionTypeId, pt.Name as PromotionTypeName, pt.Color, SUM(dpt.UsageCount) as UsageCount, SUM(dpt.TotalDiscountAmount) as TotalDiscountAmount');

            if (!empty($branchIds)) {
                $query->whereIn('dpt.BranchId', $branchIds);
            }

            $oldRows = $query->groupBy('dpt.PromotionTypeId', 'pt.Name', 'pt.Color')->get();

            foreach ($oldRows as $row) {
                $typeMap[$row->PromotionTypeId] = [
                    'PromotionTypeId'     => $row->PromotionTypeId,
                    'PromotionTypeName'   => $row->PromotionTypeName,
                    'Color'               => $row->Color,
                    'UsageCount'          => (int) $row->UsageCount,
                    'TotalDiscountAmount' => (float) $row->TotalDiscountAmount,
                    'UsagePercent'        => 0,
                ];
            }
        }

        // Ngày hôm nay: query trực tiếp
        if ($hasToday) {
            DB::statement('SET SESSION TRANSACTION ISOLATION LEVEL READ UNCOMMITTED');

            $todayQuery = DB::table('PromotionOrderDetail as pod')
                ->join('OrderDetail as od', 'od.OrderDetailId', '=', 'pod.OrderDetailId')
                ->join('promotions as p', 'p.ID', '=', 'pod.PromotionId')
                ->join('PromotionType as pt', 'pt.PromotionTypeId', '=', 'p.PromotionType')
                ->whereBetween('od.FirstTreatmentTime', [$today . ' 00:00:00', $today . ' 23:59:59'])
                ->where('pod.State', 1)
                ->selectRaw("pt.PromotionTypeId, pt.Name as PromotionTypeName, pt.Color, COUNT(DISTINCT CONCAT(pod.CustomerId, '_', pod.PromotionId)) as UsageCount, SUM(pod.DiscountAmount) as TotalDiscountAmount");

            if (!empty($branchIds)) {
                $todayQuery->whereIn('od.ConsultedBranchId', $branchIds);
            }

            $todayRows = $todayQuery->groupBy('pt.PromotionTypeId', 'pt.Name', 'pt.Color')->get();

            DB::statement('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');

            foreach ($todayRows as $row) {
                if (isset($typeMap[$row->PromotionTypeId])) {
                    $typeMap[$row->PromotionTypeId]['UsageCount']          += (int) $row->UsageCount;
                    $typeMap[$row->PromotionTypeId]['TotalDiscountAmount'] += (float) $row->TotalDiscountAmount;
                } else {
                    $typeMap[$row->PromotionTypeId] = [
                        'PromotionTypeId'     => $row->PromotionTypeId,
                        'PromotionTypeName'   => $row->PromotionTypeName,
                        'Color'               => $row->Color,
                        'UsageCount'          => (int) $row->UsageCount,
                        'TotalDiscountAmount' => (float) $row->TotalDiscountAmount,
                        'UsagePercent'        => 0,
                    ];
                }
            }
        }

        // Tính UsagePercent = TotalDiscountAmount của loại / tổng * 100
        $grandTotal = array_sum(array_column($typeMap, 'TotalDiscountAmount'));
        foreach ($typeMap as &$item) {
            $item['UsagePercent'] = $grandTotal > 0
                ? round(($item['TotalDiscountAmount'] / $grandTotal) * 100, 2)
                : 0;
        }
        unset($item);

        // Sort theo TotalDiscountAmount desc
        usort($typeMap, function ($a, $b) {
            return $b['TotalDiscountAmount'] <=> $a['TotalDiscountAmount'];
        });

        return array_values($typeMap);
    }

    public function getPromotionSummary(array $data)
    {
        $fromDate  = $data['FromDate'] ?? Carbon::now()->format('Y-m-d');
        $toDate    = $data['ToDate']   ?? Carbon::now()->format('Y-m-d');
        $branchIds = $data['BranchIds'] ?? [];
        $today     = Carbon::now()->format('Y-m-d');

        // Tách ngày hôm nay và ngày cũ
        $oldDates     = [];
        $hasToday     = false;
        $startDate    = Carbon::parse($fromDate);
        $endDate      = Carbon::parse($toDate);

        while ($startDate->lte($endDate)) {
            $d = $startDate->format('Y-m-d');
            if ($d === $today) {
                $hasToday = true;
            } else {
                $oldDates[] = $d;
            }
            $startDate->addDay();
        }

        $totalUsageCount     = 0;
        $totalDiscountAmount = 0;
        $totalOrderCount     = 0;
        $totalOrderAmount    = 0;

        // Lấy ngày cũ từ bảng pre-aggregated
        if (!empty($oldDates)) {
            $query = DB::table('DashboardPromotionEfficencyDaily')
                ->whereIn('SummaryDate', $oldDates)
                ->selectRaw('SUM(TotalUsageCount) as TotalUsageCount, SUM(TotalDiscountAmount) as TotalDiscountAmount, SUM(TotalOrderCount) as TotalOrderCount, SUM(TotalOrderAmount) as TotalOrderAmount');

            if (!empty($branchIds)) {
                $query->whereIn('BranchId', $branchIds);
            }

            $oldResult = $query->first();
            if ($oldResult) {
                $totalUsageCount     += (int) $oldResult->TotalUsageCount;
                $totalDiscountAmount += (float) $oldResult->TotalDiscountAmount;
                $totalOrderCount     += (int) $oldResult->TotalOrderCount;
                $totalOrderAmount    += (float) $oldResult->TotalOrderAmount;
            }
        }

        // Lấy ngày hôm nay từ query trực tiếp
        if ($hasToday) {
            DB::statement('SET SESSION TRANSACTION ISOLATION LEVEL READ UNCOMMITTED');

            // Tổng giảm giá hôm nay
            $todayDiscount = DB::table('OrderDetail')
                ->whereBetween('FirstTreatmentTime', [$today . ' 00:00:00', $today . ' 23:59:59'])
                ->where('Status', '>', 1)
                ->where('DiscountAmount', '>', 0);
            if (!empty($branchIds)) {
                $todayDiscount->whereIn('ConsultedBranchId', $branchIds);
            }
            $todayDiscountResult = $todayDiscount->selectRaw('SUM(DiscountAmount) as TotalDiscountAmount, SUM(ServicePrice) as TotalOrderAmount')->first();

            // Tổng lượt KM và số promotion hôm nay
            $todayUsage = DB::table('PromotionOrderDetail as pod')
                ->join('OrderDetail as od', 'od.OrderDetailId', '=', 'pod.OrderDetailId')
                ->whereBetween('od.FirstTreatmentTime', [$today . ' 00:00:00', $today . ' 23:59:59'])
                ->where('pod.State', 1);
            if (!empty($branchIds)) {
                $todayUsage->whereIn('od.ConsultedBranchId', $branchIds);
            }
            $todayUsageResult = $todayUsage->selectRaw("COUNT(DISTINCT CONCAT(pod.CustomerId, '_', pod.PromotionId)) as TotalUsageCount, COUNT(DISTINCT pod.PromotionId) as TotalOrderCount")->first();

            DB::statement('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');

            $totalDiscountAmount += (float) ($todayDiscountResult->TotalDiscountAmount ?? 0);
            $totalOrderAmount    += (float) ($todayDiscountResult->TotalOrderAmount ?? 0);
            $totalUsageCount     += (int) ($todayUsageResult->TotalUsageCount ?? 0);
            $totalOrderCount     += (int) ($todayUsageResult->TotalOrderCount ?? 0);
        }

        // Số PK phát sinh mã = COUNT(DISTINCT BranchId) có TotalUsageCount > 0
        $branchIdsWithPromo = [];
        
        // Lấy branches từ ngày cũ
        if (!empty($oldDates)) {
            $branchWithPromoQuery = DB::table('DashboardPromotionEfficencyDaily')
                ->whereIn('SummaryDate', $oldDates)
                ->where('TotalUsageCount', '>', 0);
            if (!empty($branchIds)) {
                $branchWithPromoQuery->whereIn('BranchId', $branchIds);
            }
            $oldBranches = $branchWithPromoQuery->distinct()->pluck('BranchId')->toArray();
            $branchIdsWithPromo = array_merge($branchIdsWithPromo, $oldBranches);
        }
        
        // Lấy branches từ hôm nay (real-time)
        if ($hasToday) {
            DB::statement('SET SESSION TRANSACTION ISOLATION LEVEL READ UNCOMMITTED');
            
            $todayBranchQuery = DB::table('PromotionOrderDetail as pod')
                ->join('OrderDetail as od', 'od.OrderDetailId', '=', 'pod.OrderDetailId')
                ->whereBetween('od.FirstTreatmentTime', [$today . ' 00:00:00', $today . ' 23:59:59'])
                ->where('pod.State', 1);
            if (!empty($branchIds)) {
                $todayBranchQuery->whereIn('od.ConsultedBranchId', $branchIds);
            }
            $todayBranches = $todayBranchQuery->distinct()->pluck('od.ConsultedBranchId')->toArray();
            
            DB::statement('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');
            
            $branchIdsWithPromo = array_merge($branchIdsWithPromo, $todayBranches);
        }
        
        // Đếm số branches unique
        $totalBranchWithPromo = count(array_unique($branchIdsWithPromo));

        $dependencyRate = $totalOrderAmount > 0
            ? round(($totalDiscountAmount / $totalOrderAmount) * 100, 2)
            : 0;

        return [
            'TotalUsageCount'     => $totalUsageCount,
            'TotalDiscountAmount' => $totalDiscountAmount,
            'TotalBranchCount'    => $totalBranchWithPromo,
            'DependencyRate'      => $dependencyRate,
            'TotalOrderAmount'    => $totalOrderAmount,
        ];
    }

    public function insertPromotionDashboard($data)
    {
        try {
            $fromDate = $data['FromDate'] ?? NULL;
            $toDate = $data['ToDate'] ?? NULL;
            $branchIds = $data['BranchId'] ?? [];

            if (!$fromDate || !$toDate) {
                return [
                    'success' => false,
                    'message' => 'FromDate and ToDate are required'
                ];
            }

            $results = [];
            $startTime = microtime(true);

            // 1. Insert Promotion Daily
            Log::info('insertPromotionDashboard: Starting insertPromotionDaily');
            $result1 = $this->insertPromotionDaily($data);
            $results['insertPromotionDaily'] = $result1;

            // 2. Insert Promotion By Type
            Log::info('insertPromotionDashboard: Starting insertPromotionByType');
            $result2 = $this->insertPromotionByType($data);
            $results['insertPromotionByType'] = $result2;

            // 3. Insert Promotion By Service
            Log::info('insertPromotionDashboard: Starting insertPromotionByService');
            $result3 = $this->insertPromotionByService($data);
            $results['insertPromotionByService'] = $result3;

            // 4. Insert Promotion By Branch
            Log::info('insertPromotionDashboard: Starting insertPromotionByBranch');
            $result4 = $this->insertPromotionByBranch($data);
            $results['insertPromotionByBranch'] = $result4;

            $endTime = microtime(true);
            $executionTime = round($endTime - $startTime, 2);

            return [
                'success' => true,
                'message' => 'Dashboard Promotion data insert successfully',
                'execution_time' => $executionTime . ' seconds',
                'from_date' => $fromDate,
                'to_date' => $toDate,
                'branches' => !empty($branchIds) ? count($branchIds) : 'all',
                'details' => $results
            ];

        } catch (\Exception $e) {
            Log::error('DashboardRepository@insertPromotionDashboard: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Insert data vào bảng DashboardPromotionEfficencyDaily
     * Rules lấy dữ liệu
     * 1. Từ bảng OrderDetail, PromotionOrderDetail, 
     */
    public function insertPromotionDaily($data)
    {
        try {
            $fromDate  = $data['FromDate'] ?? Carbon::now()->format('Y-m-d');
            $toDate    = $data['ToDate']   ?? Carbon::now()->format('Y-m-d');
            $branchIds = $data['BranchId'] ?? [];

            if (!is_array($branchIds) || empty($branchIds)) {
                $branchIds = Branch::where('State', 1)->pluck('BranchId')->toArray();
                if (empty($branchIds)) {
                    return ['success' => false, 'message' => 'No active branches found'];
                }
            }

            $currentTime = Carbon::now();

            DB::beginTransaction();
            DB::statement('SET SESSION TRANSACTION ISOLATION LEVEL READ UNCOMMITTED');

            // Query 1: Tổng giảm giá và tổng tiền dịch vụ theo ngày + chi nhánh (chỉ đơn có KM)
            $queryOne = DB::table('OrderDetail')
                ->whereBetween('FirstTreatmentTime', [$fromDate . ' 00:00:00', $toDate . ' 23:59:59'])
                ->where('Status', '>', 1)
                ->where('DiscountAmount', '>', 0)
                ->selectRaw('DATE(FirstTreatmentTime) as SummaryDate, ConsultedBranchId as BranchId, SUM(DiscountAmount) as TotalDiscountAmount, SUM(ServicePrice) as TotalServicePrice, COUNT(DISTINCT OrderDetailId) as TotalCaseCount');

            if (!empty($branchIds)) {
                $queryOne->whereIn('ConsultedBranchId', $branchIds);
            }

            $discountData = $queryOne
                ->groupBy(DB::raw('DATE(FirstTreatmentTime)'), 'ConsultedBranchId')
                ->get()
                ->keyBy(function ($item) {
                    return $item->SummaryDate . '_' . $item->BranchId;
                });

            // Query 1b: Tổng tiền dịch vụ (tất cả đơn) để tính DependencyRate
            $queryOneb = DB::table('OrderDetail')
                ->whereBetween('FirstTreatmentTime', [$fromDate . ' 00:00:00', $toDate . ' 23:59:59'])
                ->where('Status', '>', 1)
                ->selectRaw('DATE(FirstTreatmentTime) as SummaryDate, ConsultedBranchId as BranchId, SUM(ServicePrice) as TotalOrderAmount');

            if (!empty($branchIds)) {
                $queryOneb->whereIn('ConsultedBranchId', $branchIds);
            }

            $orderData = $queryOneb
                ->groupBy(DB::raw('DATE(FirstTreatmentTime)'), 'ConsultedBranchId')
                ->get()
                ->keyBy(function ($item) {
                    return $item->SummaryDate . '_' . $item->BranchId;
                });

            // Query 2: Tổng lượt dùng mã KM và số promotion phát sinh theo ngày + chi nhánh
            // TotalUsageCount: đếm DISTINCT (CustomerId, PromotionId)
            // TotalOrderCount: số promotion phát sinh = COUNT(DISTINCT PromotionId)
            $queryTwo = DB::table('PromotionOrderDetail as pod')
                ->join('OrderDetail as od', 'od.OrderDetailId', '=', 'pod.OrderDetailId')
                ->whereBetween('od.FirstTreatmentTime', [$fromDate . ' 00:00:00', $toDate . ' 23:59:59'])
                ->where('pod.State', 1)
                ->selectRaw("DATE(od.FirstTreatmentTime) as SummaryDate, od.ConsultedBranchId as BranchId, COUNT(DISTINCT CONCAT(pod.CustomerId, '_', pod.PromotionId)) as TotalUsageCount, COUNT(DISTINCT pod.PromotionId) as TotalOrderCount");

            if (!empty($branchIds)) {
                $queryTwo->whereIn('od.ConsultedBranchId', $branchIds);
            }

            $usageData = $queryTwo
                ->groupBy(DB::raw('DATE(od.FirstTreatmentTime)'), 'od.ConsultedBranchId')
                ->get()
                ->keyBy(function ($item) {
                    return $item->SummaryDate . '_' . $item->BranchId;
                });

            DB::statement('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');

            // Tạo danh sách ngày
            $startDate = Carbon::parse($fromDate);
            $endDate   = Carbon::parse($toDate);
            $dateList  = [];
            while ($startDate->lte($endDate)) {
                $dateList[] = $startDate->format('Y-m-d');
                $startDate->addDay();
            }

            // Lấy existing records
            $existingQuery = DB::table('DashboardPromotionEfficencyDaily')
                ->whereIn('SummaryDate', $dateList);
            if (!empty($branchIds)) {
                $existingQuery->whereIn('BranchId', $branchIds);
            }
            $existingRecords = $existingQuery->get()
                ->keyBy(function ($item) {
                    return $item->SummaryDate . '_' . $item->BranchId;
                })
                ->toArray();

            $recordsToInsert = [];
            $updateCount     = 0;

            foreach ($dateList as $date) {
                foreach ($branchIds as $branchId) {
                    $key = $date . '_' . $branchId;

                    $discountRow = $discountData[$key] ?? null;
                    $usageRow    = $usageData[$key]    ?? null;
                    $orderRow    = $orderData[$key]    ?? null;

                    $totalOrderCount     = $usageRow    ? (int) $usageRow->TotalOrderCount          : 0;
                    $totalOrderAmount    = $orderRow    ? (float) $orderRow->TotalOrderAmount        : 0;
                    $totalDiscountAmount = $discountRow ? (float) $discountRow->TotalDiscountAmount  : 0;
                    $totalUsageCount     = $usageRow    ? (int) $usageRow->TotalUsageCount           : 0;
                    // DependencyRate = tổng giảm / tổng tiền dịch vụ * 100
                    $dependencyRate      = $totalOrderAmount > 0
                        ? round(($totalDiscountAmount / $totalOrderAmount) * 100, 2)
                        : 0;

                    $recordData = [
                        'TotalUsageCount'     => $totalUsageCount,
                        'TotalDiscountAmount' => $totalDiscountAmount,
                        'TotalOrderCount'     => $totalOrderCount,
                        'TotalOrderAmount'    => $totalOrderAmount,
                        'DependencyRate'      => $dependencyRate,
                        'UpdatedDate'         => $currentTime,
                    ];

                    if (isset($existingRecords[$key])) {
                        DB::table('DashboardPromotionEfficencyDaily')
                            ->where('SummaryDate', $date)
                            ->where('BranchId', $branchId)
                            ->update($recordData);
                        $updateCount++;
                    } else {
                        $recordsToInsert[] = array_merge($recordData, [
                            'SummaryDate' => $date,
                            'BranchId'    => $branchId,
                            'CreatedDate' => $currentTime,
                        ]);
                    }
                }
            }

            if (!empty($recordsToInsert)) {
                foreach (array_chunk($recordsToInsert, 500) as $chunk) {
                    DB::table('DashboardPromotionEfficencyDaily')->insert($chunk);
                }
            }

            DB::commit();

            return [
                'success'  => true,
                'inserted' => count($recordsToInsert),
                'updated'  => $updateCount,
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('DashboardRepository@insertPromotionDaily: ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function insertPromotionByType($data)
    {
        try {
            $fromDate  = $data['FromDate'] ?? Carbon::now()->format('Y-m-d');
            $toDate    = $data['ToDate']   ?? Carbon::now()->format('Y-m-d');
            $branchIds = $data['BranchId'] ?? [];

            if (!is_array($branchIds) || empty($branchIds)) {
                $branchIds = Branch::where('State', 1)->whereIn('CompanyId', [1,2,3])->whereNotIn('BranchId', [48,49])->pluck('BranchId')->toArray();
                if (empty($branchIds)) {
                    return ['success' => false, 'message' => 'No active branches found'];
                }
            }

            $currentTime = Carbon::now();

            DB::beginTransaction();
            DB::statement('SET SESSION TRANSACTION ISOLATION LEVEL READ UNCOMMITTED');

            // Query: group theo ngày, chi nhánh, PromotionTypeId
            $query = DB::table('PromotionOrderDetail as pod')
                ->join('OrderDetail as od', 'od.OrderDetailId', '=', 'pod.OrderDetailId')
                ->join('promotions as p', 'p.ID', '=', 'pod.PromotionId')
                ->join('PromotionType as pt', 'pt.PromotionTypeId', '=', 'p.PromotionType')
                ->whereBetween('od.FirstTreatmentTime', [$fromDate . ' 00:00:00', $toDate . ' 23:59:59'])
                ->where('pod.State', 1)
                ->where('od.Status', '>', 1)
                ->selectRaw("
                    DATE(od.FirstTreatmentTime) as SummaryDate,
                    od.ConsultedBranchId as BranchId,
                    pt.PromotionTypeId,
                    COUNT(DISTINCT pod.CustomerId) as UsageCount,
                    SUM(od.DiscountAmount) as TotalDiscountAmount
                ");

            if (!empty($branchIds)) {
                $query->whereIn('od.ConsultedBranchId', $branchIds);
            }

            $rawData = $query
                ->groupBy(DB::raw('DATE(od.FirstTreatmentTime)'), 'od.ConsultedBranchId', 'pt.PromotionTypeId')
                ->get();
                
            DB::statement('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');

            if ($rawData->isEmpty()) {
                DB::commit();
                return ['success' => true, 'inserted' => 0, 'updated' => 0];
            }

            // Tính tổng TotalDiscountAmount theo ngày + chi nhánh để tính UsagePercent
            $totalByDateBranch = [];
            foreach ($rawData as $row) {
                $key = $row->SummaryDate . '_' . $row->BranchId;
                $totalByDateBranch[$key] = ($totalByDateBranch[$key] ?? 0) + $row->TotalDiscountAmount;
            }

            // Lấy existing records
            $dateList = $rawData->pluck('SummaryDate')->unique()->toArray();
            $existingQuery = DB::table('DashboardPromotionEfficencyByType')
                ->whereIn('SummaryDate', $dateList);
            if (!empty($branchIds)) {
                $existingQuery->whereIn('BranchId', $branchIds);
            }
            $existingRecords = $existingQuery->get()
                ->keyBy(function ($item) {
                    return $item->SummaryDate . '_' . $item->BranchId . '_' . $item->PromotionTypeId;
                })
                ->toArray();

            $recordsToInsert = [];
            $updateCount     = 0;

            foreach ($rawData as $row) {
                $key      = $row->SummaryDate . '_' . $row->BranchId . '_' . $row->PromotionTypeId;
                $totalKey = $row->SummaryDate . '_' . $row->BranchId;
                $total    = $totalByDateBranch[$totalKey] ?? 0;
                $percent  = $total > 0 ? round(($row->TotalDiscountAmount / $total) * 100, 2) : 0;

                $recordData = [
                    'UsageCount'          => (int) $row->UsageCount,
                    'UsagePercent'        => $percent,
                    'TotalDiscountAmount' => (float) $row->TotalDiscountAmount,
                    'UpdatedDate'         => $currentTime,
                ];

                if (isset($existingRecords[$key])) {
                    DB::table('DashboardPromotionEfficencyByType')
                        ->where('SummaryDate', $row->SummaryDate)
                        ->where('BranchId', $row->BranchId)
                        ->where('PromotionTypeId', $row->PromotionTypeId)
                        ->update($recordData);
                    $updateCount++;
                } else {
                    $recordsToInsert[] = array_merge($recordData, [
                        'SummaryDate'     => $row->SummaryDate,
                        'BranchId'        => $row->BranchId,
                        'PromotionTypeId' => $row->PromotionTypeId,
                        'CreatedDate'     => $currentTime,
                    ]);
                }
            }

            if (!empty($recordsToInsert)) {
                foreach (array_chunk($recordsToInsert, 500) as $chunk) {
                    DB::table('DashboardPromotionEfficencyByType')->insert($chunk);
                }
            }

            DB::commit();

            return [
                'success'  => true,
                'inserted' => count($recordsToInsert),
                'updated'  => $updateCount,
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('DashboardRepository@insertPromotionByType: ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function insertPromotionByService($data)
    {
        try {
            $fromDate  = $data['FromDate'] ?? Carbon::now()->format('Y-m-d');
            $toDate    = $data['ToDate']   ?? Carbon::now()->format('Y-m-d');
            $branchIds = $data['BranchId'] ?? [];

            if (!is_array($branchIds) || empty($branchIds)) {
                $branchIds = Branch::where('State', 1)->whereIn('CompanyId', [1,2,3])->whereNotIn('BranchId', [48,49])->pluck('BranchId')->toArray();
                if (empty($branchIds)) {
                    return ['success' => false, 'message' => 'No active branches found'];
                }
            }

            $currentTime = Carbon::now();

            DB::beginTransaction();
            DB::statement('SET SESSION TRANSACTION ISOLATION LEVEL READ UNCOMMITTED');

            // I=Implant, O=Niềng răng, P=Phục hình, NULL/G=Tổng quát (T)
            $query = DB::table('PromotionOrderDetail as pod')
                ->join('OrderDetail as od', 'od.OrderDetailId', '=', 'pod.OrderDetailId')
                ->join('Service as s', 's.ServiceId', '=', 'od.ServiceId')
                ->whereBetween('od.FirstTreatmentTime', [$fromDate . ' 00:00:00', $toDate . ' 23:59:59'])
                ->where('pod.State', 1)
                ->where('od.Status', '>', 1)
                ->selectRaw("
                    DATE(od.FirstTreatmentTime) as SummaryDate,
                    od.ConsultedBranchId as BranchId,
                    CASE
                        WHEN s.WarrantyType = 'I' THEN 'I'
                        WHEN s.WarrantyType = 'O' THEN 'O'
                        WHEN s.WarrantyType = 'P' THEN 'P'
                        ELSE 'T'
                    END AS ServiceCategoryId,
                    COUNT(DISTINCT CONCAT(pod.CustomerId, '_', pod.PromotionId)) as UsageCount,
                    COUNT(DISTINCT od.ServiceId) as TotalService,
                    SUM(pod.DiscountAmount) as TotalDiscountAmount
                ");

            if (!empty($branchIds)) {
                $query->whereIn('od.ConsultedBranchId', $branchIds);
            }

            $rawData = $query
                ->groupBy(
                    DB::raw('DATE(od.FirstTreatmentTime)'),
                    'od.ConsultedBranchId',
                    DB::raw("CASE WHEN s.WarrantyType = 'I' THEN 'I' WHEN s.WarrantyType = 'O' THEN 'O' WHEN s.WarrantyType = 'P' THEN 'P' ELSE 'T' END")
                )
                ->get();

            DB::statement('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');

            if ($rawData->isEmpty()) {
                DB::commit();
                return ['success' => true, 'inserted' => 0, 'updated' => 0];
            }

            // Tính tổng TotalDiscountAmount theo ngày + chi nhánh để tính UsagePercent
            $totalByDateBranch = [];
            foreach ($rawData as $row) {
                $key = $row->SummaryDate . '_' . $row->BranchId;
                $totalByDateBranch[$key] = ($totalByDateBranch[$key] ?? 0) + $row->TotalDiscountAmount;
            }

            // Lấy existing records
            $dateList = $rawData->pluck('SummaryDate')->unique()->toArray();
            $existingQuery = DB::table('DashboardPromotionEfficencyByService')
                ->whereIn('SummaryDate', $dateList);
            if (!empty($branchIds)) {
                $existingQuery->whereIn('BranchId', $branchIds);
            }
            $existingRecords = $existingQuery->get()
                ->keyBy(function ($item) {
                    return $item->SummaryDate . '_' . $item->BranchId . '_' . $item->ServiceCategoryId;
                })
                ->toArray();

            $recordsToInsert = [];
            $updateCount     = 0;

            foreach ($rawData as $row) {
                $key      = $row->SummaryDate . '_' . $row->BranchId . '_' . $row->ServiceCategoryId;
                $totalKey = $row->SummaryDate . '_' . $row->BranchId;
                $total    = $totalByDateBranch[$totalKey] ?? 0;
                $percent  = $total > 0 ? round(($row->TotalDiscountAmount / $total) * 100, 2) : 0;

                $recordData = [
                    'TotalService'        => (int) $row->TotalService,
                    'UsageCount'          => (int) $row->UsageCount,
                    'UsagePercent'        => $percent,
                    'TotalDiscountAmount' => (float) $row->TotalDiscountAmount,
                    'UpdatedDate'         => $currentTime,
                ];

                if (isset($existingRecords[$key])) {
                    DB::table('DashboardPromotionEfficencyByService')
                        ->where('SummaryDate', $row->SummaryDate)
                        ->where('BranchId', $row->BranchId)
                        ->where('ServiceCategoryId', $row->ServiceCategoryId)
                        ->update($recordData);
                    $updateCount++;
                } else {
                    $recordsToInsert[] = array_merge($recordData, [
                        'SummaryDate'       => $row->SummaryDate,
                        'BranchId'          => $row->BranchId,
                        'ServiceCategoryId' => $row->ServiceCategoryId,
                        'CreatedDate'       => $currentTime,
                    ]);
                }
            }

            if (!empty($recordsToInsert)) {
                foreach (array_chunk($recordsToInsert, 500) as $chunk) {
                    DB::table('DashboardPromotionEfficencyByService')->insert($chunk);
                }
            }

            DB::commit();

            return [
                'success'  => true,
                'inserted' => count($recordsToInsert),
                'updated'  => $updateCount,
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('DashboardRepository@insertPromotionByService: ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function getPromotionByBranch(array $data)
    {
        $fromDate  = $data['FromDate'] ?? Carbon::now()->format('Y-m-d');
        $toDate    = $data['ToDate']   ?? Carbon::now()->format('Y-m-d');
        $branchIds = $data['BranchIds'] ?? [];
        $today     = Carbon::now()->format('Y-m-d');

        // Tách ngày hôm nay và ngày cũ
        $oldDates  = [];
        $hasToday  = false;
        $startDate = Carbon::parse($fromDate);
        $endDate   = Carbon::parse($toDate);
        while ($startDate->lte($endDate)) {
            $d = $startDate->format('Y-m-d');
            if ($d === $today) {
                $hasToday = true;
            } else {
                $oldDates[] = $d;
            }
            $startDate->addDay();
        }

        $branchMap = [];

        // Ngày cũ: lấy từ bảng DashboardPromotionEfficencyByBranch
        if (!empty($oldDates)) {
            $query = DB::table('DashboardPromotionEfficencyByBranch as dpb')
                ->join('in.Branch as b', 'b.BranchId', '=', 'dpb.BranchId')
                ->whereIn('b.CompanyId', [1,2,3])
                ->whereNotIn('b.BranchId', [48,49])
                ->whereIn('dpb.SummaryDate', $oldDates)
                ->selectRaw('
                    dpb.BranchId,
                    b.BranchCode,
                    b.Priority,
                    SUM(dpb.TotalOrderCount) as TotalOrderCount,
                    SUM(dpb.TotalUsageCount) as TotalUsageCount,
                    SUM(dpb.TotalDiscountAmount) as TotalDiscountAmount,
                    SUM(dpb.TotalOrderAmount) as TotalOrderAmount
                ');

            if (!empty($branchIds)) {
                $query->whereIn('dpb.BranchId', $branchIds);
            }

            $oldRows = $query->groupBy('dpb.BranchId', 'b.BranchCode', 'b.Priority')->get();

            foreach ($oldRows as $row) {
                $branchMap[$row->BranchId] = [
                    'BranchId'            => $row->BranchId,
                    'BranchCode'          => $row->BranchCode,
                    'Priority'            => $row->Priority ?? 999,
                    'TotalOrderCount'     => (int) $row->TotalOrderCount,
                    'TotalUsageCount'     => (int) $row->TotalUsageCount,
                    'TotalDiscountAmount' => (float) $row->TotalDiscountAmount,
                    'TotalOrderAmount'    => (float) $row->TotalOrderAmount,
                    'DependencyRate'      => 0,
                    'EvaluationLevel'     => '',
                ];
            }
        }

        // Ngày hôm nay: query trực tiếp
        if ($hasToday) {
            DB::statement('SET SESSION TRANSACTION ISOLATION LEVEL READ UNCOMMITTED');

            // Query 1: Tổng ca và tổng tiền dịch vụ (tất cả đơn)
            $todayOrderQuery = DB::table('OrderDetail')
                ->whereBetween('FirstTreatmentTime', [$today . ' 00:00:00', $today . ' 23:59:59'])
                ->where('Status', '>', 1)
                ->selectRaw('ConsultedBranchId as BranchId, COUNT(DISTINCT TreatmentId) as TotalOrderCount, SUM(ServicePrice) as TotalOrderAmount');

            if (!empty($branchIds)) {
                $todayOrderQuery->whereIn('ConsultedBranchId', $branchIds);
            }

            $todayOrderRows = $todayOrderQuery->groupBy('ConsultedBranchId')->get();

            // Query 2: Tổng lượt KM và tổng giảm từ PromotionOrderDetail
            $todayPromoQuery = DB::table('PromotionOrderDetail as pod')
                ->join('OrderDetail as od', 'od.OrderDetailId', '=', 'pod.OrderDetailId')
                ->whereBetween('od.FirstTreatmentTime', [$today . ' 00:00:00', $today . ' 23:59:59'])
                ->where('pod.State', 1)
                ->selectRaw("od.ConsultedBranchId as BranchId, COUNT(DISTINCT CONCAT(pod.CustomerId, '_', pod.PromotionId)) as TotalUsageCount, SUM(pod.DiscountAmount) as TotalDiscountAmount");

            if (!empty($branchIds)) {
                $todayPromoQuery->whereIn('od.ConsultedBranchId', $branchIds);
            }

            $todayPromoRows = $todayPromoQuery->groupBy('od.ConsultedBranchId')->get();

            DB::statement('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');

            // Merge dữ liệu hôm nay vào branchMap
            foreach ($todayOrderRows as $row) {
                $bid = $row->BranchId;
                if (isset($branchMap[$bid])) {
                    $branchMap[$bid]['TotalOrderCount']  += (int) $row->TotalOrderCount;
                    $branchMap[$bid]['TotalOrderAmount'] += (float) $row->TotalOrderAmount;
                } else {
                    $branch = Branch::where('BranchId', $bid)->first();
                    $branchMap[$bid] = [
                        'BranchId'            => $bid,
                        'BranchCode'          => $branch ? $branch->BranchCode : null,
                        'Priority'            => $branch ? $branch->Priority : 999,
                        'TotalOrderCount'     => (int) $row->TotalOrderCount,
                        'TotalUsageCount'     => 0,
                        'TotalDiscountAmount' => 0,
                        'TotalOrderAmount'    => (float) $row->TotalOrderAmount,
                        'DependencyRate'      => 0,
                        'EvaluationLevel'     => '',
                    ];
                }
            }

            foreach ($todayPromoRows as $row) {
                $bid = $row->BranchId;
                if (isset($branchMap[$bid])) {
                    $branchMap[$bid]['TotalUsageCount']     += (int) $row->TotalUsageCount;
                    $branchMap[$bid]['TotalDiscountAmount'] += (float) $row->TotalDiscountAmount;
                } else {
                    $branch = Branch::where('BranchId', $bid)->first();
                    $branchMap[$bid] = [
                        'BranchId'            => $bid,
                        'BranchCode'          => $branch ? $branch->BranchCode : null,
                        'Priority'            => $branch ? $branch->Priority : 999,
                        'TotalOrderCount'     => 0,
                        'TotalUsageCount'     => (int) $row->TotalUsageCount,
                        'TotalDiscountAmount' => (float) $row->TotalDiscountAmount,
                        'TotalOrderAmount'    => 0,
                        'DependencyRate'      => 0,
                        'EvaluationLevel'     => '',
                    ];
                }
            }
        }

        // Tính DependencyRate và EvaluationLevel cho từng chi nhánh
        foreach ($branchMap as &$item) {
            $item['DependencyRate'] = $item['TotalOrderAmount'] > 0
                ? round(($item['TotalDiscountAmount'] / $item['TotalOrderAmount']) * 100, 2)
                : 0;

            // EvaluationLevel: Bình thường <15%, Cao 15-20%, Bất thường >20%
            if ($item['DependencyRate'] > 20) {
                $item['EvaluationLevel'] = 'Bất thường';
            } elseif ($item['DependencyRate'] >= 15) {
                $item['EvaluationLevel'] = 'Cao';
            } else {
                $item['EvaluationLevel'] = 'Bình thường';
            }
        }
        unset($item);

        // Sort theo Priority ASC
        usort($branchMap, function ($a, $b) {
            return $a['Priority'] <=> $b['Priority'];
        });

        return array_values($branchMap);
    }

    public function getTopCustomersByPromotion(array $data)
    {
        $fromDate  = $data['FromDate'] ?? Carbon::now()->format('Y-m-d');
        $toDate    = $data['ToDate']   ?? Carbon::now()->format('Y-m-d');
        $branchIds = $data['BranchIds'] ?? [];

        DB::statement('SET SESSION TRANSACTION ISOLATION LEVEL READ UNCOMMITTED');

        // Query lấy danh sách khách hàng
        $query = DB::table('OrderDetail as od')
            ->join('PromotionOrderDetail as pod', 'pod.OrderDetailId', '=', 'od.OrderDetailId')
            ->join('Customer as c', 'c.CustomerId', '=', 'pod.CustomerId')
            ->whereBetween('od.FirstTreatmentTime', [$fromDate . ' 00:00:00', $toDate . ' 23:59:59'])
            ->where('od.Status', '>', 1)
            ->where('pod.State', '=', 1)
            ->selectRaw('
                c.CustomerId,
                c.FullName as CustomerName,
                c.CustomerCode,
                SUM(od.ServicePrice) as TotalServicePrice,
                SUM(od.DiscountAmount) as TotalDiscountAmount,
                COUNT(DISTINCT CONCAT(pod.PromotionId, "_", DATE(od.FirstTreatmentTime))) as PromotionUsageCount
            ')
            ->groupBy('c.CustomerId', 'c.FullName', 'c.CustomerCode');

        if (!empty($branchIds)) {
            $query->whereIn('od.ConsultedBranchId', $branchIds);
        }

        $result = $query
            ->orderByDesc('TotalServicePrice')
            // ->limit(10)
            ->get();

        DB::statement('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');

        // Format kết quả
        $formattedResult = [];
        $index = 1;
        foreach ($result as $row) {
            $dependencyRate = $row->TotalServicePrice > 0
                ? round(($row->TotalDiscountAmount / $row->TotalServicePrice) * 100, 2)
                : 0;

            $formattedResult[] = [
                'CustomerId'            => $row->CustomerId,
                'CustomerName'          => $row->CustomerName,
                'CustomerCode'          => $row->CustomerCode,
                'PromotionUsageCount'   => (int) $row->PromotionUsageCount,
                'TotalDiscountAmount'   => (float) $row->TotalDiscountAmount,
                'TotalServicePrice'     => (float) $row->TotalServicePrice,
                'DependencyRate'        => $dependencyRate,
            ];
        }

        return $formattedResult;
    }

    public function insertPromotionByBranch($data)
    {
        try {
            $fromDate  = $data['FromDate'] ?? Carbon::now()->format('Y-m-d');
            $toDate    = $data['ToDate']   ?? Carbon::now()->format('Y-m-d');
            $branchIds = $data['BranchId'] ?? [];

            if (!is_array($branchIds) || empty($branchIds)) {
                $branchIds = Branch::where('State', 1)->whereIn('CompanyId', [1,2,3])->whereNotIn('BranchId', [48,49])->pluck('BranchId')->toArray();
                if (empty($branchIds)) {
                    return ['success' => false, 'message' => 'No active branches found'];
                }
            }

            $currentTime = Carbon::now();

            DB::beginTransaction();
            DB::statement('SET SESSION TRANSACTION ISOLATION LEVEL READ UNCOMMITTED');

            // Query 1: Tổng ca và tổng tiền dịch vụ (tất cả đơn)
            $queryOne = DB::table('OrderDetail')
                ->whereBetween('FirstTreatmentTime', [$fromDate . ' 00:00:00', $toDate . ' 23:59:59'])
                ->where('Status', '>', 1)
                ->selectRaw('DATE(FirstTreatmentTime) as SummaryDate, ConsultedBranchId as BranchId, COUNT(DISTINCT TreatmentId) as TotalOrderCount, SUM(ServicePrice) as TotalOrderAmount');

            if (!empty($branchIds)) {
                $queryOne->whereIn('ConsultedBranchId', $branchIds);
            }

            $orderData = $queryOne
                ->groupBy(DB::raw('DATE(FirstTreatmentTime)'), 'ConsultedBranchId')
                ->get()
                ->keyBy(function ($item) {
                    return $item->SummaryDate . '_' . $item->BranchId;
                });

            // Query 2: Tổng lượt KM và tổng giảm từ PromotionOrderDetail
            // TotalUsageCount: DISTINCT (CustomerId, PromotionId)
            // TotalDiscountAmount: SUM(pod.DiscountAmount)
            $queryTwo = DB::table('PromotionOrderDetail as pod')
                ->join('OrderDetail as od', 'od.OrderDetailId', '=', 'pod.OrderDetailId')
                ->whereBetween('od.FirstTreatmentTime', [$fromDate . ' 00:00:00', $toDate . ' 23:59:59'])
                ->where('pod.State', 1)
                ->selectRaw("DATE(od.FirstTreatmentTime) as SummaryDate, od.ConsultedBranchId as BranchId, COUNT(DISTINCT CONCAT(pod.CustomerId, '_', pod.PromotionId)) as TotalUsageCount, SUM(pod.DiscountAmount) as TotalDiscountAmount");

            if (!empty($branchIds)) {
                $queryTwo->whereIn('od.ConsultedBranchId', $branchIds);
            }

            $promoData = $queryTwo
                ->groupBy(DB::raw('DATE(od.FirstTreatmentTime)'), 'od.ConsultedBranchId')
                ->get()
                ->keyBy(function ($item) {
                    return $item->SummaryDate . '_' . $item->BranchId;
                });

            DB::statement('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');

            // Tạo danh sách ngày
            $startDate = Carbon::parse($fromDate);
            $endDate   = Carbon::parse($toDate);
            $dateList  = [];
            while ($startDate->lte($endDate)) {
                $dateList[] = $startDate->format('Y-m-d');
                $startDate->addDay();
            }

            // Lấy existing records
            $existingQuery = DB::table('DashboardPromotionEfficencyByBranch')
                ->whereIn('SummaryDate', $dateList);
            if (!empty($branchIds)) {
                $existingQuery->whereIn('BranchId', $branchIds);
            }
            $existingRecords = $existingQuery->get()
                ->keyBy(function ($item) {
                    return $item->SummaryDate . '_' . $item->BranchId;
                })
                ->toArray();

            $recordsToInsert = [];
            $updateCount     = 0;

            foreach ($dateList as $date) {
                foreach ($branchIds as $branchId) {
                    $key = $date . '_' . $branchId;

                    $orderRow = $orderData[$key] ?? null;
                    $promoRow = $promoData[$key] ?? null;

                    $totalOrderCount     = $orderRow ? (int) $orderRow->TotalOrderCount     : 0;
                    $totalOrderAmount    = $orderRow ? (float) $orderRow->TotalOrderAmount   : 0;
                    $totalUsageCount     = $promoRow ? (int) $promoRow->TotalUsageCount      : 0;
                    $totalDiscountAmount = $promoRow ? (float) $promoRow->TotalDiscountAmount : 0;

                    // DependencyRate = tổng giảm / tổng tiền dịch vụ * 100
                    $dependencyRate = $totalOrderAmount > 0
                        ? round(($totalDiscountAmount / $totalOrderAmount) * 100, 2)
                        : 0;

                    // EvaluationLevel: 1=Bình thường <15%, 2=Cao 15-20%, 3=Bất thường >20%
                    if ($dependencyRate > 20) {
                        $evaluationLevel = 3;
                    } elseif ($dependencyRate >= 15) {
                        $evaluationLevel = 2;
                    } else {
                        $evaluationLevel = 1;
                    }

                    $recordData = [
                        'TotalOrderCount'     => $totalOrderCount,
                        'TotalUsageCount'     => $totalUsageCount,
                        'TotalDiscountAmount' => $totalDiscountAmount,
                        'TotalOrderAmount'    => $totalOrderAmount,
                        'DependencyRate'      => $dependencyRate,
                        'EvaluationLevel'     => $evaluationLevel,
                        'UpdatedDate'         => $currentTime,
                    ];

                    if (isset($existingRecords[$key])) {
                        DB::table('DashboardPromotionEfficencyByBranch')
                            ->where('SummaryDate', $date)
                            ->where('BranchId', $branchId)
                            ->update($recordData);
                        $updateCount++;
                    } else {
                        $recordsToInsert[] = array_merge($recordData, [
                            'SummaryDate' => $date,
                            'BranchId'    => $branchId,
                            'CreatedDate' => $currentTime,
                        ]);
                    }
                }
            }

            if (!empty($recordsToInsert)) {
                foreach (array_chunk($recordsToInsert, 500) as $chunk) {
                    DB::table('DashboardPromotionEfficencyByBranch')->insert($chunk);
                }
            }

            DB::commit();

            return [
                'success'  => true,
                'inserted' => count($recordsToInsert),
                'updated'  => $updateCount,
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('DashboardRepository@insertPromotionByBranch: ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Lượt khách checkin bình quân theo thứ
     * Trả về data thực từ database
     */
    public function getCheckinByWeekday($data)
    {
        try {
            $branchId = $data['BranchId'] ?? null;
            $fromDate = $data['FromDate'] ?? null;
            $toDate   = $data['ToDate']   ?? null;

            if (!$branchId || !$fromDate || !$toDate) {
                return [
                    'Summary'   => ['AveragePerDay' => 0, 'HighestPerDay' => 0, 'HighestDay' => ''],
                    'ChartData' => [],
                ];
            }

            $rows = [];
            try {
                DB::statement('SET SESSION TRANSACTION ISOLATION LEVEL READ UNCOMMITTED');

                $rows = DB::select("
                    SELECT
                        Day,
                        Weekday,
                        WeekdayName,
                        ROUND(AVG(DailyCount)) AS Count
                    FROM (
                        SELECT
                            DATE(FROM_UNIXTIME(a.StartAt))      AS d,
                            DAYOFWEEK(FROM_UNIXTIME(a.StartAt)) AS Day,
                            CASE DAYOFWEEK(FROM_UNIXTIME(a.StartAt))
                                WHEN 1 THEN 'CN'
                                WHEN 2 THEN 'T2'
                                WHEN 3 THEN 'T3'
                                WHEN 4 THEN 'T4'
                                WHEN 5 THEN 'T5'
                                WHEN 6 THEN 'T6'
                                WHEN 7 THEN 'T7'
                            END AS Weekday,
                            CASE DAYOFWEEK(FROM_UNIXTIME(a.StartAt))
                                WHEN 1 THEN 'Chủ nhật'
                                WHEN 2 THEN 'Thứ 2'
                                WHEN 3 THEN 'Thứ 3'
                                WHEN 4 THEN 'Thứ 4'
                                WHEN 5 THEN 'Thứ 5'
                                WHEN 6 THEN 'Thứ 6'
                                WHEN 7 THEN 'Thứ 7'
                            END AS WeekdayName,
                            COUNT(*) AS DailyCount
                        FROM Appointment a
                        LEFT JOIN `in`.Holiday h
                            ON h.`Date`      = DATE(FROM_UNIXTIME(a.StartAt))
                            AND h.IsHoliday   = 1
                        WHERE  a.StartAt    >= ?
                        AND  a.StartAt    <  ?
                        AND  a.AtBranchId =  ?
                        AND  a.CheckInTime IS NOT NULL
                        AND  h.`Date` IS NULL          -- ngày lễ bị loại (không match = không phải ngày lễ)
                        GROUP BY d, Day, Weekday, WeekdayName
                    ) daily
                    GROUP BY Day, Weekday, WeekdayName
                    ORDER BY Day
                ", [strtotime($fromDate), strtotime($toDate . ' +1 day'), $branchId]);

            } finally {
                DB::statement('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');
            }

            $weekdayTemplate = [
                1 => ['Weekday' => 'CN', 'WeekdayName' => 'Chủ nhật', 'Count' => 0],
                2 => ['Weekday' => 'T2', 'WeekdayName' => 'Thứ 2',    'Count' => 0],
                3 => ['Weekday' => 'T3', 'WeekdayName' => 'Thứ 3',    'Count' => 0],
                4 => ['Weekday' => 'T4', 'WeekdayName' => 'Thứ 4',    'Count' => 0],
                5 => ['Weekday' => 'T5', 'WeekdayName' => 'Thứ 5',    'Count' => 0],
                6 => ['Weekday' => 'T6', 'WeekdayName' => 'Thứ 6',    'Count' => 0],
                7 => ['Weekday' => 'T7', 'WeekdayName' => 'Thứ 7',    'Count' => 0],
            ];

            foreach ($rows as $row) {
                $weekdayTemplate[$row->Day]['Count'] = (int) $row->Count;
            }

            $totals  = array_column($weekdayTemplate, 'Count');
            $maxVal  = max($totals);
            $peakDay = '';
            foreach ($weekdayTemplate as $day) {
                if ($day['Count'] == $maxVal) {
                    $peakDay = $day['Weekday'];
                    break;
                }
            }

            $summary = [
                'AveragePerDay' => (int) round(array_sum($totals) / 7),
                'HighestPerDay' => $maxVal,
                'HighestDay'    => $peakDay,
            ];

            // ── CN (key=1) xuống cuối ─────────────────────────────────────
            $chartData = [];
            foreach ([2, 3, 4, 5, 6, 7, 1] as $day) {
                $chartData[] = $weekdayTemplate[$day];
            }

            return [
                'Summary'   => $summary,
                'ChartData' => $chartData,
            ];

        } catch (\Exception $e) {
            Log::error('DashboardRepository@getCheckinByWeekday: ' . $e->getMessage());
            return [
                'Summary'   => ['AveragePerDay' => 0, 'HighestPerDay' => 0, 'HighestDay' => ''],
                'ChartData' => [],
            ];
        }
    }

    /**
     * Ghế điều trị sử dụng bình quân theo thứ
     * Trả về data thực từ database
     */
    public function getChairUsageByWeekday($data)
    {
        try {
            $branchId = $data['BranchId'] ?? null;
            $fromDate = $data['FromDate'] ?? null;
            $toDate   = $data['ToDate']   ?? null;

            if (!$branchId || !$fromDate || !$toDate) {
                return [
                    'Summary'   => ['AveragePerDay' => 0, 'HighestPerDay' => 0, 'TotalChairs' => 0, 'FullDays' => 0],
                    'ChartData' => [],
                    'Threshold' => 0,
                ];
            }

            $totalChairs = DB::table('DentalChair')
                ->join('Room as r', 'r.RoomId', '=', 'DentalChair.RoomId')
                ->where('DentalChair.BranchId', $branchId)
                ->where('DentalChair.State', 1)
                ->whereNotIn('r.RoomTypeId', [2,4])
                ->count();

            // ── Isolation level dùng finally để đảm bảo luôn được reset ──────
            $rows = [];
            try {
                DB::statement('SET SESSION TRANSACTION ISOLATION LEVEL READ UNCOMMITTED');

                $rows = DB::select("
                    SELECT
                        Day,
                        ROUND(AVG(DailyChairs)) AS AvgChairs,
                        MAX(DailyChairs)        AS MaxChairs
                    FROM (
                        SELECT
                            DATE(dcb.EstimatedStartDate)               AS d,
                            DAYOFWEEK(dcb.EstimatedStartDate)          AS Day,
                            COUNT(DISTINCT dcb.DentalChairId)          AS DailyChairs
                        FROM DentalChairBooking as dcb
                        JOIN DentalChair as dc on dc.DentalChairId = dcb.DentalChairId
                        JOIN Room as r on r.RoomId = dc.RoomId
                        WHERE dcb.EstimatedStartDate >= ?
                        AND dcb.EstimatedStartDate < DATE_ADD(?, INTERVAL 1 DAY)
                        AND dcb.IsDeleted = 0
                        AND dc.BranchId = ?
                        AND r.RoomTypeId NOT IN (2,4)
                        GROUP BY d, Day
                    ) t
                    GROUP BY Day
                    ORDER BY Day
                ", [$fromDate, $toDate, $branchId]);

            } finally {
                DB::statement('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');
            }

            $weekdayTemplate = [
                1 => ['Weekday' => 'CN', 'WeekdayName' => 'Chủ nhật', 'Count' => 0, 'Status' => 'normal'],
                2 => ['Weekday' => 'T2', 'WeekdayName' => 'Thứ 2',    'Count' => 0, 'Status' => 'normal'],
                3 => ['Weekday' => 'T3', 'WeekdayName' => 'Thứ 3',    'Count' => 0, 'Status' => 'normal'],
                4 => ['Weekday' => 'T4', 'WeekdayName' => 'Thứ 4',    'Count' => 0, 'Status' => 'normal'],
                5 => ['Weekday' => 'T5', 'WeekdayName' => 'Thứ 5',    'Count' => 0, 'Status' => 'normal'],
                6 => ['Weekday' => 'T6', 'WeekdayName' => 'Thứ 6',    'Count' => 0, 'Status' => 'normal'],
                7 => ['Weekday' => 'T7', 'WeekdayName' => 'Thứ 7',    'Count' => 0, 'Status' => 'normal'],
            ];

            $fullDays = 0;
            foreach ($rows as $row) {
                $avgChairs = (int) $row->AvgChairs;
                $weekdayTemplate[$row->Day]['Count'] = $avgChairs;

                if ($avgChairs >= $totalChairs) {
                    $weekdayTemplate[$row->Day]['Status'] = 'full';
                    $fullDays++;
                } elseif ($avgChairs >= $totalChairs * 0.9) {
                    $weekdayTemplate[$row->Day]['Status'] = 'warning';
                } else {
                    $weekdayTemplate[$row->Day]['Status'] = 'normal';
                }
            }

            $totals   = array_column($weekdayTemplate, 'Count');
            $maxVal   = max($totals);
            $summary  = [
                'AveragePerDay' => (int) round(array_sum($totals) / 7),
                'HighestPerDay' => $maxVal,
                'TotalChairs'   => $totalChairs,
                'FullDays'      => $fullDays,
            ];

            // ── Chủ Nhật (key=1) xuống cuối ─────────────────────────────────
            $chartData = [];
            foreach ([2, 3, 4, 5, 6, 7, 1] as $day) {
                $chartData[] = $weekdayTemplate[$day];
            }

            return [
                'Summary'   => $summary,
                'ChartData' => $chartData,
                'Threshold' => $totalChairs,
            ];

        } catch (\Exception $e) {
            Log::error('DashboardRepository@getChairUsageByWeekday: ' . $e->getMessage());
            return [
                'Summary'   => ['AveragePerDay' => 0, 'HighestPerDay' => 0, 'TotalChairs' => 0, 'FullDays' => 0],
                'ChartData' => [],
                'Threshold' => 0,
            ];
        }
    }

    /**
     * Lượt khách checkin theo giờ trong ngày
     * Trả về data giả dựa trên hình ảnh
     */
    public function getCheckinByHour($data)
    {
        try {
            $branchId = $data['BranchId'] ?? null;
            $fromDate = $data['FromDate'] ?? null;
            $toDate   = $data['ToDate']   ?? null;
            $weekday  = $data['Weekday']  ?? null;

            if (!$branchId || !$fromDate || !$toDate || !$weekday) {
                return [
                    'Summary'   => ['Total' => 0, 'PeakHour' => '', 'PeakCount' => 0],
                    'ChartData' => [],
                ];
            }

            // Map Weekday → DAYOFWEEK MySQL (1=CN, 2=T2 … 7=T7)
            $dowMap = ['CN' => 1, 'T2' => 2, 'T3' => 3, 'T4' => 4, 'T5' => 5, 'T6' => 6, 'T7' => 7];

            if (!isset($dowMap[$weekday])) {
                return [
                    'Summary'   => ['Total' => 0, 'PeakHour' => '', 'PeakCount' => 0],
                    'ChartData' => [],
                ];
            }

            $dow = $dowMap[$weekday];

            $rows = [];
            try {
                DB::statement('SET SESSION TRANSACTION ISOLATION LEVEL READ UNCOMMITTED');

                $rows = DB::select("
                    SELECT
                        h.Hour,
                        COALESCE(ROUND(d.HourSum / t.TotalDays), 0) AS Count,
                        t.TotalCheckins,
                        t.TotalDays
                    FROM (
                        SELECT  8 AS Hour UNION ALL SELECT  9
                        UNION ALL SELECT 10 UNION ALL SELECT 11 UNION ALL SELECT 12
                        UNION ALL SELECT 13 UNION ALL SELECT 14 UNION ALL SELECT 15
                        UNION ALL SELECT 16 UNION ALL SELECT 17 UNION ALL SELECT 18
                        UNION ALL SELECT 19
                    ) h
                    LEFT JOIN (
                        SELECT Hour, SUM(DailyCount) AS HourSum
                        FROM (
                            SELECT
                                DATE(FROM_UNIXTIME(a.StartAt)) AS d,
                                -- trước 9h → 8h, sau 19h → 19h
                                CASE
                                    WHEN HOUR(a.CheckInTime) < 9  THEN 8
                                    WHEN HOUR(a.CheckInTime) > 19 THEN 19
                                    ELSE HOUR(a.CheckInTime)
                                END                            AS Hour,
                                COUNT(*)                       AS DailyCount
                            FROM Appointment a
                            LEFT JOIN `in`.Holiday hol
                                ON hol.`Date`    = DATE(FROM_UNIXTIME(a.StartAt))
                                AND hol.IsHoliday = 1
                            WHERE  a.StartAt    >= ?
                            AND  a.StartAt    <  ?
                            AND  a.AtBranchId  = ?
                            AND  a.CheckInTime IS NOT NULL
                            AND  HOUR(a.CheckInTime) BETWEEN 7 AND 20
                            AND  DAYOFWEEK(FROM_UNIXTIME(a.StartAt)) = ?
                            AND  hol.`Date` IS NULL
                            GROUP BY d, Hour
                        ) daily
                        GROUP BY Hour
                    ) d ON d.Hour = h.Hour
                    CROSS JOIN (
                        SELECT
                            COUNT(DISTINCT DATE(FROM_UNIXTIME(a.StartAt))) AS TotalDays,
                            COUNT(*)                                        AS TotalCheckins
                        FROM Appointment a
                        LEFT JOIN `in`.Holiday hol
                            ON hol.`Date`    = DATE(FROM_UNIXTIME(a.StartAt))
                            AND hol.IsHoliday = 1
                        WHERE  a.StartAt    >= ?
                        AND  a.StartAt    <  ?
                        AND  a.AtBranchId  = ?
                        AND  a.CheckInTime IS NOT NULL
                        AND  HOUR(a.CheckInTime) BETWEEN 7 AND 20
                        AND  DAYOFWEEK(FROM_UNIXTIME(a.StartAt)) = ?
                        AND  hol.`Date` IS NULL
                    ) t
                    ORDER BY h.Hour
                ", [
                    strtotime($fromDate), strtotime($toDate . ' +1 day'), $branchId, $dow,
                    strtotime($fromDate), strtotime($toDate . ' +1 day'), $branchId, $dow,
                ]);

            } finally {
                DB::statement('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');
            }

            // fix 2: ROUND 1 lần từ tổng thực, không cộng dồn từng giờ đã ROUND
            $summaryTotal = 0;
            if (!empty($rows)) {
                $totalDays    = (int) $rows[0]->TotalDays;
                $totalCheckins = (int) $rows[0]->TotalCheckins;
                $summaryTotal = $totalDays > 0 ? (int) round($totalCheckins / $totalDays) : 0;
            }

            $chartData = [];
            $peakCount = 0;
            $peakHour  = '';

            foreach ($rows as $row) {
                $count       = (int) $row->Count;
                $chartData[] = ['Hour' => $row->Hour . 'h', 'Count' => $count];
                if ($count > $peakCount) {
                    $peakCount = $count;
                    $peakHour  = $row->Hour . 'h';
                }
            }

            return [
                'Summary' => [
                    'Total'     => $summaryTotal,   // ← khớp với getCheckinByWeekday
                    'PeakHour'  => $peakHour,
                    'PeakCount' => $peakCount,
                ],
                'ChartData' => $chartData,
            ];

        } catch (\Exception $e) {
            Log::error('DashboardRepository@getCheckinByHour: ' . $e->getMessage());
            return [
                'Summary'   => ['Total' => 0, 'PeakHour' => '', 'PeakCount' => 0],
                'ChartData' => [],
            ];
        }
    }

    public function getHourlyUsage($data)
    {
        try {
            $branchId = $data['BranchId'] ?? null;
            $fromDate = $data['FromDate'] ?? null;
            $toDate   = $data['ToDate']   ?? null;
            $weekday  = $data['Weekday']  ?? null;

            if (!$branchId || !$fromDate || !$toDate || !$weekday) {
                return [
                    'Summary'   => ['AveragePerHour' => 0, 'PeakHour' => '', 'PeakCount' => 0, 'TotalChairs' => 0],
                    'ChartData' => [],
                ];
            }

            $dowMap = ['CN' => 1, 'T2' => 2, 'T3' => 3, 'T4' => 4, 'T5' => 5, 'T6' => 6, 'T7' => 7];

            if (!isset($dowMap[$weekday])) {
                return [
                    'Summary'   => ['AveragePerHour' => 0, 'PeakHour' => '', 'PeakCount' => 0, 'TotalChairs' => 0],
                    'ChartData' => [],
                ];
            }

            $dow = $dowMap[$weekday];

            // Tổng ghế chi nhánh → đường ngưỡng (dashed line)
            $totalChairs = DB::table('DentalChair')
                ->join('Room as r', 'r.RoomId', '=', 'DentalChair.RoomId')
                ->where('DentalChair.BranchId', $branchId)
                ->where('DentalChair.State', 1)
                ->whereNotIn('r.RoomTypeId', [2,4])
                ->count();

            $rows = [];
            try {
                DB::statement('SET SESSION TRANSACTION ISOLATION LEVEL READ UNCOMMITTED');

                $rows = DB::select("
                    SELECT
                        h.Hour,
                        COALESCE(ROUND(d.ChairSum / t.TotalDays), 0) AS Count,
                        t.TotalDays
                    FROM (
                        SELECT  8 AS Hour UNION ALL SELECT  9
                        UNION ALL SELECT 10 UNION ALL SELECT 11 UNION ALL SELECT 12
                        UNION ALL SELECT 13 UNION ALL SELECT 14 UNION ALL SELECT 15
                        UNION ALL SELECT 16 UNION ALL SELECT 17 UNION ALL SELECT 18
                        UNION ALL SELECT 19
                    ) h
                    LEFT JOIN (
                        SELECT Hour, SUM(DailyChairs) AS ChairSum
                        FROM (
                            SELECT
                                DATE(dcb.EstimatedStartDate)          AS d,
                                CASE
                                    WHEN HOUR(dcb.EstimatedStartDate) < 9  THEN 8
                                    WHEN HOUR(dcb.EstimatedStartDate) > 19 THEN 19
                                    ELSE HOUR(dcb.EstimatedStartDate)
                                END                                   AS Hour,
                                COUNT(DISTINCT dcb.DentalChairId)     AS DailyChairs
                            FROM DentalChairBooking dcb
                            JOIN DentalChair dc
                                ON dc.DentalChairId = dcb.DentalChairId
                            JOIN Room r
                                ON r.RoomId = dc.RoomId
                            LEFT JOIN `in`.Holiday hol
                                ON hol.`Date`    = DATE(dcb.EstimatedStartDate)
                                AND hol.IsHoliday = 1
                            WHERE  dcb.EstimatedStartDate >= ?
                            AND  dcb.EstimatedStartDate <  DATE_ADD(?, INTERVAL 1 DAY)
                            AND  dcb.IsDeleted   = 0
                            -- AND  HOUR(dcb.EstimatedStartDate) BETWEEN 7 AND 20
                            AND  DAYOFWEEK(dcb.EstimatedStartDate) = ?
                            AND  dc.BranchId     = ?
                            AND  dc.State        = 1
                            AND  r.RoomTypeId NOT IN (2, 4)
                            AND  hol.`Date` IS NULL
                            GROUP BY d, Hour
                        ) daily
                        GROUP BY Hour
                    ) d ON d.Hour = h.Hour
                    CROSS JOIN (
                        SELECT COUNT(DISTINCT DATE(dcb.EstimatedStartDate)) AS TotalDays
                        FROM DentalChairBooking dcb
                        JOIN DentalChair dc
                            ON dc.DentalChairId = dcb.DentalChairId
                        JOIN Room r
                            ON r.RoomId = dc.RoomId
                        LEFT JOIN `in`.Holiday hol
                            ON hol.`Date`    = DATE(dcb.EstimatedStartDate)
                            AND hol.IsHoliday = 1
                        WHERE  dcb.EstimatedStartDate >= ?
                        AND  dcb.EstimatedStartDate <  DATE_ADD(?, INTERVAL 1 DAY)
                        AND  dcb.IsDeleted   = 0
                        -- AND  HOUR(dcb.EstimatedStartDate) BETWEEN 7 AND 20
                        AND  DAYOFWEEK(dcb.EstimatedStartDate) = ?
                        AND  dc.BranchId     = ?
                        AND  dc.State        = 1
                        AND  r.RoomTypeId NOT IN (2, 4)
                        AND  hol.`Date` IS NULL
                    ) t
                    ORDER BY h.Hour
                ", [
                    $fromDate, $toDate, $dow, $branchId,   // LEFT JOIN
                    $fromDate, $toDate, $dow, $branchId,   // CROSS JOIN
                ]);

            } finally {
                DB::statement('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');
            }

            $chartData = [];
            $total     = 0;
            $peakCount = 0;
            $peakHour  = '';

            foreach ($rows as $row) {
                $count       = (int) $row->Count;
                $chartData[] = [
                    'Hour'        => $row->Hour . 'h',
                    'Count'       => $count,
                    'TotalChairs' => $totalChairs,          // "6 ghế / 12" → dùng ở tooltip
                ];
                $total += $count;
                if ($count > $peakCount) {
                    $peakCount = $count;
                    $peakHour  = $row->Hour . 'h';
                }
            }

            // TB: 6.1 = tổng ghế tất cả khung giờ / 14 khung giờ
            $avgPerHour = count($chartData) > 0
                ? round($total / count($chartData), 1)
                : 0;

            return [
                'Summary' => [
                    'AveragePerHour' => $avgPerHour,    // TB: 6.1
                    'PeakHour'       => $peakHour,      // Cao điểm: 14h
                    'PeakCount'      => $peakCount,
                    'TotalChairs'    => $totalChairs,   // ngưỡng đường kẻ ngang: 12
                ],
                'ChartData' => $chartData,
            ];

        } catch (\Exception $e) {
            Log::error('DashboardRepository@getHourlyUsage: ' . $e->getMessage());
            return [
                'Summary'   => ['AveragePerHour' => 0, 'PeakHour' => '', 'PeakCount' => 0, 'TotalChairs' => 0],
                'ChartData' => [],
            ];
        }
    }

    public function getCheckinWeekdayByHour($data)
    {
        try {
            $startDate = $data['FromDate'];
            $endDate   = $data['ToDate'];
            $branchId  = (int) $data['BranchId'];

            $rows = [];
            try {
                DB::statement('SET SESSION TRANSACTION ISOLATION LEVEL READ UNCOMMITTED');

                $rows = DB::select("
                    SELECT
                        dow.Day,
                        dow.DayLabel,
                        h.Hour,
                        COALESCE(data.TotalCheckin, 0) AS TotalCheckin
                    FROM (
                        SELECT  8 AS Hour UNION ALL SELECT  9
                        UNION ALL SELECT 10 UNION ALL SELECT 11 UNION ALL SELECT 12
                        UNION ALL SELECT 13 UNION ALL SELECT 14 UNION ALL SELECT 15
                        UNION ALL SELECT 16 UNION ALL SELECT 17 UNION ALL SELECT 18
                        UNION ALL SELECT 19
                    ) h
                    CROSS JOIN (
                        SELECT 1 AS Day, 'CN' AS DayLabel
                        UNION ALL SELECT 2, 'T2'
                        UNION ALL SELECT 3, 'T3'
                        UNION ALL SELECT 4, 'T4'
                        UNION ALL SELECT 5, 'T5'
                        UNION ALL SELECT 6, 'T6'
                        UNION ALL SELECT 7, 'T7'
                    ) dow
                    LEFT JOIN (
                        SELECT
                            Day,
                            Hour,
                            ROUND(AVG(cnt)) AS TotalCheckin
                        FROM (
                            SELECT
                                DATE(FROM_UNIXTIME(a.StartAt))      AS d,
                                DAYOFWEEK(FROM_UNIXTIME(a.StartAt)) AS Day,
                                CASE
                                    WHEN HOUR(a.CheckInTime) < 9  THEN 8
                                    WHEN HOUR(a.CheckInTime) > 19 THEN 19
                                    ELSE HOUR(a.CheckInTime)
                                END                                 AS Hour,
                                COUNT(*)                            AS cnt
                            FROM Appointment a
                            LEFT JOIN `in`.Holiday hol
                                ON hol.`Date`    = DATE(FROM_UNIXTIME(a.StartAt))
                                AND hol.IsHoliday = 1
                            WHERE  a.StartAt    >= ?
                            AND  a.StartAt    <  ?
                            AND  a.AtBranchId  = ?
                            AND  a.CheckInTime IS NOT NULL
                            AND  hol.`Date`   IS NULL
                            GROUP BY d, Day, Hour
                        ) daily
                        GROUP BY Day, Hour
                    ) data ON data.Day = dow.Day AND data.Hour = h.Hour
                    ORDER BY CASE WHEN dow.Day = 1 THEN 8 ELSE dow.Day END, h.Hour
                ", [strtotime($startDate), strtotime($endDate . ' +1 day'), $branchId]);

            } finally {
                DB::statement('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');
            }

            $matrix = [];
            foreach ($rows as $row) {
                $matrix[$row->DayLabel][$row->Hour] = (int) $row->TotalCheckin;
            }

            return $matrix;

        } catch (\Exception $e) {
            Log::error('DashboardRepository@getCheckinWeekdayByHour: ' . $e->getMessage());
            return [];
        }
    }

    public function getAverageStayTime($data)
    {
        try {
            $branchId = $data['BranchId'] ?? null;
            $fromDate = $data['FromDate'] ?? null;
            $toDate   = $data['ToDate']   ?? null;

            if (!$branchId || !$fromDate || !$toDate) {
                return [];
            }

            $rows = [];
            try {
                DB::statement('SET SESSION TRANSACTION ISOLATION LEVEL READ UNCOMMITTED');

                $rows = DB::select("
                    SELECT
                        DAYOFWEEK(FROM_UNIXTIME(StartAt))  AS Day,
                        CASE DAYOFWEEK(FROM_UNIXTIME(StartAt))
                            WHEN 1 THEN 'Chủ nhật'
                            WHEN 2 THEN 'Thứ 2'
                            WHEN 3 THEN 'Thứ 3'
                            WHEN 4 THEN 'Thứ 4'
                            WHEN 5 THEN 'Thứ 5'
                            WHEN 6 THEN 'Thứ 6'
                            WHEN 7 THEN 'Thứ 7'
                        END                                AS DayLabel,
                        COUNT(*)                           AS TotalAppointments,
                        ROUND(
                            SUM(
                                TIMESTAMPDIFF(
                                    SECOND,
                                    CONCAT(DATE(FROM_UNIXTIME(StartAt)), ' ', CheckInTime),
                                    LatestUpdated
                                )
                            ) / COUNT(*) / 60
                        , 1)                               AS AvgStayMinutes
                    FROM Appointment
                    WHERE StartAt >= ?
                    AND   StartAt < ?
                    AND  AtBranchId = ?
                    AND  CheckInTime  IS NOT NULL
                    AND  LatestUpdated IS NOT NULL
                    AND TIMESTAMPDIFF(
                        SECOND,
                        CONCAT(DATE(FROM_UNIXTIME(StartAt)), ' ', CheckInTime),

                        CASE
                            WHEN DATE(LatestUpdated) = DATE(FROM_UNIXTIME(StartAt))
                            THEN LatestUpdated

                            ELSE CONCAT(
                                DATE(FROM_UNIXTIME(StartAt)),
                                ' 23:59:59'
                            )
                        END
                    ) > 0
                    GROUP BY Day, DayLabel
                    ORDER BY CASE WHEN Day = 1 THEN 8 ELSE Day END
                ", [strtotime($fromDate), strtotime($toDate . ' +1 day'), $branchId]);

            } finally {
                DB::statement('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');
            }

            // Template đủ 7 thứ, mặc định = 0
            $template = [
                1 => ['Weekday' => 'CN', 'DayLabel' => 'Chủ nhật', 'TotalAppointments' => 0, 'AverageStayMinutes' => 0],
                2 => ['Weekday' => 'T2', 'DayLabel' => 'Thứ 2',    'TotalAppointments' => 0, 'AverageStayMinutes' => 0],
                3 => ['Weekday' => 'T3', 'DayLabel' => 'Thứ 3',    'TotalAppointments' => 0, 'AverageStayMinutes' => 0],
                4 => ['Weekday' => 'T4', 'DayLabel' => 'Thứ 4',    'TotalAppointments' => 0, 'AverageStayMinutes' => 0],
                5 => ['Weekday' => 'T5', 'DayLabel' => 'Thứ 5',    'TotalAppointments' => 0, 'AverageStayMinutes' => 0],
                6 => ['Weekday' => 'T6', 'DayLabel' => 'Thứ 6',    'TotalAppointments' => 0, 'AverageStayMinutes' => 0],
                7 => ['Weekday' => 'T7', 'DayLabel' => 'Thứ 7',    'TotalAppointments' => 0, 'AverageStayMinutes' => 0],
            ];

            foreach ($rows as $row) {
                $template[$row->Day]['TotalAppointments']  = (int)   $row->TotalAppointments;
                $template[$row->Day]['AverageStayMinutes'] = (float) $row->AvgStayMinutes;
            }

            // CN xuống cuối
            $result = [];
            foreach ([2, 3, 4, 5, 6, 7, 1] as $day) {
                $result[] = $template[$day];
            }

            return $result;

        } catch (\Exception $e) {
            Log::error('DashboardRepository@getAverageStayTime: ' . $e->getMessage());
            return [];
        }
    }

    public function countAppointment($data)
    {
        try {
            $branchId = $data['BranchId'] ?? null;
            $fromDate = $data['FromDate'] ?? null;
            $toDate   = $data['ToDate']   ?? null;
            $doctorId = $data['DoctorId'] ?? null;

            if (!$branchId || !$fromDate || !$toDate) {
                return [];
            }

            try {
                DB::statement('SET SESSION TRANSACTION ISOLATION LEVEL READ UNCOMMITTED');

                $sql = "
                    SELECT
                        DATE(FROM_UNIXTIME(StartAt)) AS Date,
                        DAYNAME(FROM_UNIXTIME(StartAt)) AS DayOfWeek,
                        GREATEST(8, LEAST(19, HOUR(FROM_UNIXTIME(StartAt)))) AS Hour,
                        COUNT(*) AS TotalAppointment,
                        COUNT(CASE WHEN AppointedTo = ? THEN 1 END) AS DoctorAppointment
                    FROM `pos`.Appointment
                    WHERE
                        StartAt >= ?
                        AND StartAt < ?
                        AND AtBranchId = ?
                        AND AppointmentStatusId > 1
                ";

                $bindings = [
                    $doctorId,  // cho COUNT CASE
                    strtotime($fromDate),
                    strtotime($toDate . ' +1 day'),
                    $branchId,
                ];

                $sql .= " GROUP BY Date, DayOfWeek, Hour ORDER BY Date, Hour";

                $rows = DB::select($sql, $bindings);

            } finally {
                DB::statement('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');
            }

            return $rows;

        } catch (\Exception $e) {
            Log::error('DashboardRepository@countAppointment: ' . $e->getMessage());
            return [];
        }
    }
    
    public function totalCustomerOrthodontic($data)
    {
        $staffId = $data['StaffId'] ?? null;
        $currentWorkProfilePositionId = $data['CurrentWorkProfilePositionId'] ?? null;

        $defaultResult = [
            [
                'TotalPatients'           => 0,
                'DirectCases'             => 0,
                'GuidedCases'             => 0,
                'DirectPercent'           => 0,
                'GuidedPercent'           => 0,
                'TotalOrthodonticAdvisor' => 0
            ]
        ];

        if($staffId == 2 || $staffId == 3049){ // Gắn cứng cho Sếp Bin có data
            $staffId = 3249;
            $currentWorkProfilePositionId = 580;
        }

        if (!$staffId || !$currentWorkProfilePositionId) {
            return $defaultResult;
        }

        $workProfilePosition = DB::table('in.WorkProfilePosition')
            ->where('WorkProfilePositionId', $currentWorkProfilePositionId)
            ->where('State', 1)
            ->select('Code')
            ->first();

        if (!$workProfilePosition || $workProfilePosition->Code !== 'Doctor') {
            return $defaultResult;
        }

        DB::statement('SET SESSION TRANSACTION ISOLATION LEVEL READ UNCOMMITTED');

        try {
            $guidedDoctorIds = DB::table('pos.Doctor')
                ->where('OrthodonticAdvisorStaffId', $staffId)
                ->where('State', 1)
                ->pluck('StaffId')
                ->toArray();

            $totalOrthodonticAdvisor = count($guidedDoctorIds);

            // FIX: Dùng nhất quán leftJoin + orWhereNotNull, bỏ double WHERE (orWhereExists + orWhereNotNull)
            $counts = DB::table('pos.AllocatedRevenueTracking as art')
                ->join('pos.OrderDetail as od', 'od.OrderDetailId', '=', 'art.OrderDetailId')
                ->join('pos.Service as s', 's.ServiceId', '=', 'od.ServiceId')
                ->join('pos.TreatmentMedicalProcedure as tmp', 'tmp.TreatmentMedicalProcedureId', '=', 'art.TreatmentMedicalProcedureId')
                ->join('pos.TreatmentProcedureProgressByStep as tpps', function ($join) {
                    $join->on('tpps.ServiceId', '=', 'art.ServiceId')
                        ->on('tpps.ProcedureProgressId', '=', 'art.ProcedureProgressId');
                })
                ->leftJoin('pos.Doctor as d_advisor', function ($join) use ($staffId) {
                    $join->on('d_advisor.StaffId', '=', 'art.TreatmentDoctorId')
                        ->where('d_advisor.OrthodonticAdvisorStaffId', '=', $staffId)
                        ->where('d_advisor.State', '=', 1);
                })
                ->where('s.WarrantyType', 'O')
                ->where('tmp.TreatmentMedicalProcedureStatusId', 2)
                ->where('tpps.IsActive', 1)
                ->where(function ($q) use ($staffId) {
                    $q->where('art.TreatmentDoctorId', $staffId)
                    ->orWhereNotNull('d_advisor.StaffId');
                })
                ->selectRaw("
                    COUNT(DISTINCT art.OrderDetailId) as total_unique,
                    COUNT(DISTINCT CASE WHEN art.TreatmentDoctorId = ? THEN art.OrderDetailId END) as direct_total
                ", [$staffId])
                ->first();

            $directCases  = (int)($counts->direct_total ?? 0);
            $totalPatients = (int)($counts->total_unique ?? 0);
            $guidedCases  = $totalPatients - $directCases;  // không double-count
            $directPercent  = $totalPatients > 0 ? round(($directCases / $totalPatients) * 100) : 0;
            $guidedPercent  = $totalPatients > 0 ? round(($guidedCases  / $totalPatients) * 100) : 0;

            return [[
                'TotalPatients'           => $totalPatients,
                'DirectCases'             => $directCases,
                'GuidedCases'             => $guidedCases,
                'DirectPercent'           => $directPercent,
                'GuidedPercent'           => $guidedPercent,
                'TotalOrthodonticAdvisor' => $totalOrthodonticAdvisor
            ]];

        } catch (\Exception $e) {
            Log::error("Error in totalCustomerOrthodontic: " . $e->getMessage());
            return $defaultResult;
        } finally {
            DB::statement('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        }
    }

    public function totalCustomerOrthodonticByStep($data)
    {
        $staffId = $data['StaffId'] ?? null;
        $currentWorkProfilePositionId = $data['CurrentWorkProfilePositionId'] ?? null;

        $defaultResult = [
            1 => ['Stage' => 1, 'Total' => 0],
            2 => ['Stage' => 2, 'Total' => 0],
            3 => ['Stage' => 3, 'Total' => 0],
            4 => ['Stage' => 4, 'Total' => 0],
            5 => ['Stage' => 5, 'Total' => 0],
        ];

        if($staffId == 2 || $staffId == 3049){ // Gắn cứng cho Sếp Bin có data
            $staffId = 3249;
            $currentWorkProfilePositionId = 580;
        }

        if (!$staffId || !$currentWorkProfilePositionId) {
            return array_values($defaultResult);
        }

        $workProfilePosition = DB::table('in.WorkProfilePosition')
            ->where('WorkProfilePositionId', $currentWorkProfilePositionId)
            ->where('State', 1)
            ->select('Code')
            ->first();

        if (!$workProfilePosition || $workProfilePosition->Code !== 'Doctor') {
            return array_values($defaultResult);
        }

        DB::statement('SET SESSION TRANSACTION ISOLATION LEVEL READ UNCOMMITTED');

        try {
            $stageData = DB::table('pos.AllocatedRevenueTracking as art')
                ->join('pos.OrderDetail as od', 'od.OrderDetailId', '=', 'art.OrderDetailId')
                ->join('pos.Service as s', 's.ServiceId', '=', 'od.ServiceId')
                ->join('pos.TreatmentMedicalProcedure as tmp', 'tmp.TreatmentMedicalProcedureId', '=', 'art.TreatmentMedicalProcedureId')
                ->join('pos.TreatmentProcedureProgressByStep as tpps', function ($join) {
                    $join->on('tpps.ServiceId', '=', 'art.ServiceId')
                        ->on('tpps.ProcedureProgressId', '=', 'art.ProcedureProgressId');
                })
                ->leftJoin('pos.Doctor as d_advisor', function ($join) use ($staffId) {
                    $join->on('d_advisor.StaffId', '=', 'art.TreatmentDoctorId')
                        ->where('d_advisor.OrthodonticAdvisorStaffId', '=', $staffId)
                        ->where('d_advisor.State', '=', 1);
                })
                ->where('s.WarrantyType', 'O')
                ->where('tmp.TreatmentMedicalProcedureStatusId', 2)
                ->where('tpps.IsActive', 1)
                ->where(function ($q) use ($staffId) {
                    $q->where('art.TreatmentDoctorId', $staffId)
                    ->orWhereNotNull('d_advisor.StaffId');
                })
                ->groupBy('art.CustomerId', 'art.OrderDetailId')
                ->selectRaw('MAX(tpps.Stage) as MaxStage')
                ->get();

            $counts = $stageData->pluck('MaxStage')->countBy();

            foreach ($defaultResult as $stage => &$row) {
                $row['Total'] = $counts->get($stage, 0);
            }

        } catch (\Exception $e) {
            Log::error("Error in totalCustomerOrthodonticByStep: " . $e->getMessage());
        } finally {
            DB::statement('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        }

        return array_values($defaultResult);
    }

    public function getTotalCustomerOrthodonticByStepDetailData($data)
    {
        $staffId                      = $data['StaffId'] ?? null;
        $currentWorkProfilePositionId = $data['CurrentWorkProfilePositionId'] ?? null;
        $keyword                      = $data['Keyword'] ?? null;
        $stageParam                   = isset($data['Stage']) ? (int)$data['Stage'] : 0;

        $stagesStructure = [];
        $allowedStages   = ($stageParam > 0) ? [$stageParam] : [1, 2, 3, 4, 5];

        foreach ($allowedStages as $stg) {
            $stagesStructure[$stg] = [
                'Stage'              => $stg,
                'DirectCases'        => 0,
                'GuidedCases'        => 0,
                'Total'              => 0,
                'DirectCasesDetails' => [],
                'GuidedCasesDetails' => []
            ];
        }

        if($staffId == 2 || $staffId == 3049){ // Gắn cứng cho Sếp Bin có data
            $staffId = 3249;
            $currentWorkProfilePositionId = 580;
        }

        if (!$staffId || !$currentWorkProfilePositionId) {
            return array_values($stagesStructure);
        }

        $workProfilePosition = DB::table('in.WorkProfilePosition')
            ->where('WorkProfilePositionId', $currentWorkProfilePositionId)
            ->where('State', 1)
            ->select('Code')
            ->first();

        if (!$workProfilePosition || $workProfilePosition->Code !== 'Doctor') {
            return array_values($stagesStructure);
        }

        DB::statement('SET SESSION TRANSACTION ISOLATION LEVEL READ UNCOMMITTED');

        try {
            // subMaxStage mirror đúng logic hàm 2:
            // cùng filter WarrantyType + StatusId + IsActive + staffId filter
            // dùng whereRaw với literal integer để tránh binding issue
            $staffIdInt = (int)$staffId;
            $subMaxStage = DB::table('pos.AllocatedRevenueTracking as art')
                ->join('pos.OrderDetail as od', 'od.OrderDetailId', '=', 'art.OrderDetailId')
                ->join('pos.Service as s', 's.ServiceId', '=', 'od.ServiceId')
                ->join('pos.TreatmentMedicalProcedure as tmp',
                    'tmp.TreatmentMedicalProcedureId', '=', 'art.TreatmentMedicalProcedureId')
                ->join('pos.TreatmentProcedureProgressByStep as tpps', function ($join) {
                    $join->on('tpps.ServiceId', '=', 'art.ServiceId')
                        ->on('tpps.ProcedureProgressId', '=', 'art.ProcedureProgressId');
                })
                ->leftJoin('pos.Doctor as d_sub', function ($join) use ($staffIdInt) {
                    $join->on('d_sub.StaffId', '=', 'art.TreatmentDoctorId')
                        ->whereRaw("d_sub.OrthodonticAdvisorStaffId = {$staffIdInt}")
                        ->whereRaw('d_sub.State = 1');
                })
                ->whereRaw("s.WarrantyType = 'O'")
                ->whereRaw('tmp.TreatmentMedicalProcedureStatusId = 2')
                ->whereRaw('tpps.IsActive = 1')
                ->whereRaw("(art.TreatmentDoctorId = {$staffIdInt} OR d_sub.StaffId IS NOT NULL)")
                ->groupBy(['art.CustomerId', 'art.OrderDetailId'])
                ->selectRaw('art.CustomerId, art.OrderDetailId, MAX(tpps.Stage) as MaxStage');

            $subServiceDuration = DB::table('pos.TreatmentProcedureProgressByStep')
                ->whereRaw('IsActive = 1')
                ->groupBy('ServiceId')
                ->selectRaw('ServiceId, MAX(ProcedureProgressId) as MaxServiceStage');

            $rawQuery = DB::table('pos.AllocatedRevenueTracking as art')
                ->join('pos.OrderDetail as od', 'od.OrderDetailId', '=', 'art.OrderDetailId')
                ->join('pos.Service as s', 's.ServiceId', '=', 'od.ServiceId')
                ->join('pos.TreatmentMedicalProcedure as tmp',
                    'tmp.TreatmentMedicalProcedureId', '=', 'art.TreatmentMedicalProcedureId')
                ->join(DB::raw("({$subMaxStage->toSql()}) as max_stage"), function ($join) {
                    $join->on('max_stage.CustomerId', '=', 'art.CustomerId')
                        ->on('max_stage.OrderDetailId', '=', 'art.OrderDetailId');
                })
                ->leftJoin(DB::raw("({$subServiceDuration->toSql()}) as svc_duration"), function ($join) {
                    $join->on('svc_duration.ServiceId', '=', 'art.ServiceId');
                })
                ->join('pos.Customer as c', 'c.CustomerId', '=', 'art.CustomerId')
                ->join('in.Branch as b', 'b.BranchId', '=', 'art.BranchId')
                ->join('in.Staff as s_doc', 's_doc.StaffId', '=', 'art.TreatmentDoctorId')
                ->leftJoin('pos.Doctor as d_advisor', function ($join) use ($staffId) {
                    $join->on('d_advisor.StaffId', '=', 'art.TreatmentDoctorId')
                        ->where('d_advisor.OrthodonticAdvisorStaffId', '=', $staffId)
                        ->where('d_advisor.State', 1);
                })
                ->where('s.WarrantyType', 'O')
                ->where('tmp.TreatmentMedicalProcedureStatusId', 2);

            if ($stageParam > 0) {
                $rawQuery->where('max_stage.MaxStage', $stageParam);
            } else {
                $rawQuery->whereIn('max_stage.MaxStage', [1, 2, 3, 4, 5]);
            }

            $rawQuery->where(function ($q) use ($staffId) {
                $q->where('art.TreatmentDoctorId', $staffId)
                ->orWhereNotNull('d_advisor.StaffId');
            });

            if (!empty($keyword)) {
                $rawQuery->where(function ($q) use ($keyword) {
                    $q->where('c.CustomerCode', 'LIKE', "%{$keyword}%")
                    ->orWhere('c.FullName', 'LIKE', "%{$keyword}%");
                });
            }

            $rawRecords = $rawQuery->select([
                'max_stage.MaxStage as CurrentStage',
                'art.BranchId',
                'b.BranchCode',
                'art.CustomerId',
                'art.OrderDetailId',
                'c.CustomerCode',
                'c.FullName as CustomerName',
                's.Name as ServiceName',
                'art.TreatmentDoctorId as DoctorStaffId',
                's_doc.FullName as DoctorName',
                's_doc.StaffCode as DoctorCode',
                'od.FirstTreatmentTime',
                'tmp.TreatmentMedicalProcedureStatusId as Status',
                'svc_duration.MaxServiceStage as ExpectedDurationMonths'
            ])
            ->groupBy([
                'max_stage.MaxStage', 'art.BranchId', 'b.BranchCode', 'art.CustomerId', 'art.OrderDetailId',
                'c.CustomerCode', 'c.FullName', 's.Name', 'art.TreatmentDoctorId', 's_doc.FullName',
                's_doc.StaffCode', 'od.FirstTreatmentTime', 'tmp.TreatmentMedicalProcedureStatusId',
                'svc_duration.MaxServiceStage'
            ])
            ->get();

            // FIX: Bỏ unique() + groupBy sau. Thay bằng groupBy stage trước,
            // rồi trong mỗi stage group theo CustomerId-OrderDetailId để xác định
            // đúng direct/guided dù có nhiều row cho cùng 1 bệnh nhân.
            $groupedByStage = $rawRecords->groupBy('CurrentStage');

            foreach ($groupedByStage as $stage => $records) {
                if (!isset($stagesStructure[$stage])) continue;

                $directList   = [];
                $guidedGrouped = [];

                // Group theo từng cặp bệnh nhân - đơn hàng
                $patientOrderGroups = $records->groupBy(function ($item) {
                    return $item->CustomerId . '-' . $item->OrderDetailId;
                });

                foreach ($patientOrderGroups as $orderRows) {
                    // Ưu tiên row của bác sĩ trực tiếp (staffId) nếu tồn tại
                    $directRow       = $orderRows->firstWhere('DoctorStaffId', $staffId);
                    $representative  = $directRow ?? $orderRows->first();
                    $isDirectCase    = !is_null($directRow);

                    $durationMonths = $representative->ExpectedDurationMonths
                        ? (int)$representative->ExpectedDurationMonths
                        : 18;

                    $firstTreatmentTime     = null;
                    $expectedCompletionDate = null;
                    $monthsTreated          = 0;

                    if (!empty($representative->FirstTreatmentTime)) {
                        $rawDate = $representative->FirstTreatmentTime;
                        if ($rawDate !== '0000-00-00 00:00:00') {
                            $firstTreatmentTime = date('Y-m-d', strtotime($rawDate));
                            $expectedCompletionDate = date(
                                'Y-m-d',
                                strtotime("+{$durationMonths} months", strtotime($firstTreatmentTime))
                            );
                            try {
                                $startDate   = new \DateTime($firstTreatmentTime);
                                $currentDate = new \DateTime();
                                $interval    = $startDate->diff($currentDate);
                                $monthsTreated = ($interval->y * 12) + $interval->m;
                                $monthsTreated = $monthsTreated <= 0 ? 1 : $monthsTreated;
                            } catch (\Exception $dateEx) {
                                $monthsTreated = 1;
                            }
                        }
                    }

                    $progressPercent = $durationMonths > 0
                        ? min(100, round(($representative->CurrentStage / $durationMonths) * 100))
                        : 0;

                    $patientDetail = [
                        'BranchId'               => $representative->BranchId,
                        'BranchCode'             => $representative->BranchCode,
                        'CustomerId'             => $representative->CustomerId,
                        'CustomerCode'           => $representative->CustomerCode,
                        'CustomerName'           => $representative->CustomerName,
                        'ServiceName'            => $representative->ServiceName,
                        'FirstTreatmentTime'     => $firstTreatmentTime,
                        'ExpectedCompletionDate' => $expectedCompletionDate,
                        'ExpectedDurationMonths' => $durationMonths,
                        'MonthsTreated'          => $monthsTreated,
                        'Progress'               => $progressPercent,
                        'Status'                 => $representative->Status,
                    ];

                    if ($isDirectCase) {
                        $directList[] = $patientDetail;
                    } else {
                        $doctorStaffId = $representative->DoctorStaffId;
                        if (!isset($guidedGrouped[$doctorStaffId])) {
                            $guidedGrouped[$doctorStaffId] = [
                                'StaffId'         => $doctorStaffId,
                                'DoctorName'      => $representative->DoctorName,
                                'DoctorCode'      => $representative->DoctorCode,
                                'PatientsDetails' => []
                            ];
                        }
                        $guidedGrouped[$doctorStaffId]['PatientsDetails'][] = $patientDetail;
                    }
                }
                $stagesStructure[$stage]['DirectCasesDetails'] = $directList;
                $stagesStructure[$stage]['GuidedCasesDetails'] = array_values($guidedGrouped);
                $stagesStructure[$stage]['DirectCases']        = count($directList);
                $stagesStructure[$stage]['GuidedCases']        = count($guidedGrouped) > 0
                    ? array_sum(array_map(function ($g) { return count($g['PatientsDetails']); }, $guidedGrouped))
                    : 0;
                $stagesStructure[$stage]['Total'] =
                    $stagesStructure[$stage]['DirectCases'] + $stagesStructure[$stage]['GuidedCases'];
            }

        } catch (\Exception $e) {
            Log::error("Error in totalCustomerOrthodonticByStepDetail: " . $e->getMessage());
        } finally {
            DB::statement('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        }

        return array_values($stagesStructure);
    }

}
