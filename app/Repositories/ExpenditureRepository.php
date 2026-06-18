<?php

namespace App\Repositories;

use App\Expenditure;
use App\Deposit;
use App\PartnerCompany;
use App\OrderDetail;
use App\OrderDetailFinancial;
use App\Treatment;
use App\Appointment;
use App\DepositTransaction;
use App\Branch;
use App\CustomerPhoneNumber;
use App\ExpenditureService;
use App\OrderDetailFinancialTrans;
use App\Repositories\Abstracts\EloquentRepository;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;
use App\Libs\Factory;
use GuzzleHttp\Client;

class ExpenditureRepository extends EloquentRepository
{
   /**
    * @return string
    */
   protected function getModel(): string
   {
      //Required model
      return Expenditure::class;
   }

   public function listServiceCreateExpenditure($customerId)
    {
        try {
            return OrderDetailFinancial::from('OrderDetailFinancial as odf')
            ->join('OrderDetail as od', 'odf.OrderDetailId', '=', 'od.OrderDetailId')
            ->where('odf.CustomerId', $customerId)
            ->whereColumn('odf.PaymentAmount', '<', 'odf.TotalAmount')
            ->where('od.Status', '<>', 0)
            ->select([
                'od.OrderDetailId',
                'od.ServiceId',
                'od.ServiceName',
                'od.AnatomyBodyPartItemName',
                'od.OrderChangingId',
                'odf.OrderDetailAmount',
                'odf.PaymentAmount',
                'odf.TotalAmount',
                'odf.TreatmentId'
            ])
            ->selectRaw('(odf.TotalAmount - odf.PaymentAmount) as RemainAmount')
            ->get()
            ->toArray();
        } catch (\Exception $e) {
            Log::error('ExpenditureRepository - listServiceCreateExpenditure: ' . $e->getMessage());
            return [];
        }
    }

    public function createExpenditure($data)
    {
        try {

            $branchId = $data['BranchId'];
            $expenditureCode = $data['ExpenditureCode'];
            $expenditureTypeId = $data['ExpenditureTypeId'];
            $expenditureCategoryId = $data['ExpenditureCategoryId'];
            $receiverName = $data['ReceiverName'] ?? null;
            $note = $data['Note']?? null;
            $paymentMethodId = $data['PaymentMethodId'];
            $bankId = $data['BankId'] ?? null;
            $refId = $data['RefId'] ?? null;
            $exhibit = $data['Exhibit'] ?? [];
            $orderDetails = $data['OrderDetails'] ?? [];
            $treatmentId = $data['TreatmentId'] ?? null;
            $expenditureId = $data['ExpenditureId'] ?? null;
            $staffId = Auth::user()['StaffId'];

            DB::beginTransaction();
            $amount = 0;
            $oldAmount = 0;

            if(!empty($orderDetails)) {
                foreach ($orderDetails as $od) {
                    $amount += (int)$od['Amount'];
                }
            }
            $expenditureCode = $this->randomExpenditureCode();
            $dataExpenditure = [
                'ExpenditureCode'       => $expenditureCode,
                'Amount'                => $amount,
                'ReceiverName'          => $receiverName,
                'Note'                  => $note,
                'CreatedAt'             => Carbon::now()->timestamp,
                'CreatedBy'             => $staffId,
                'BranchId'              => $branchId,
                'BankId'                => $bankId,
                'EditedAt'              => Carbon::now()->timestamp,
                'EditedBy'              => $staffId,
                'ExpenditureStatusId'   => 5,
                'ExpenditureCategoryId' => $expenditureCategoryId,
                'ExpenditureTypeId'     => $expenditureTypeId,
                'PaymentMethodId'       => $paymentMethodId,
                'RefId'                 => $refId,
                'TreatmentId'           => $treatmentId
            ];

            $expenditureOld = Expenditure::select('Amount')
                ->where('ExpenditureId', $expenditureId)->first();

            if ($expenditureOld) {
                $oldAmount = (int)$expenditureOld->Amount;
            }

            if (!empty($expenditureId) && (int)$expenditureId > 0) {
                // UPDATE
                $expenditure = Expenditure::update($dataExpenditure);
            } else {
                // INSERT
                $expenditure = Expenditure::create($dataExpenditure);
            }

            $update = $this->saveExpenditureExhibitExpenditure($expenditure->ExpenditureId, $exhibit);
            if (!$update) {
                DB::rollBack();
                return false;
            }

            // 5. Điều kiện xử lý Deposit
            if (
                (int)($expenditureCategoryId ?? 0) === 44 &&
                (int)($expenditureTypeId ?? 0) === 2 &&
                (int)($amount ?? 0) > 0 &&
                (int)($refId ?? 0) > 0
            ) {

                $newAmount = (int)$amount;
                $customerId = (int)$refId;

                $deposit = Deposit::where('CustomerId', $customerId)->first();

                if ($deposit) {

                    $totalAmount     = (int)$deposit->TotalAmount;
                    $currentBalance  = (int)$deposit->CurrentBalance;

                    if ($newAmount < $oldAmount) {
                        if ((($oldAmount - $newAmount) + $currentBalance) <= $totalAmount) {
                            $currentBalance += ($oldAmount - $newAmount);
                        }
                    } else {
                        if (
                            $newAmount <= ($oldAmount + $currentBalance) &&
                            ($currentBalance - ($newAmount - $oldAmount)) >= 0
                        ) {
                            $currentBalance -= ($newAmount - $oldAmount);
                        }
                    }

                    $paidAmount = $totalAmount - $currentBalance;

                    $deposit->update([
                        'CurrentBalance' => $currentBalance,
                        'PaidAmount'     => $paidAmount,
                    ]);

                    if ($oldAmount > 0) {

                        $depositTransaction = DepositTransaction::where([
                            'Type'        => 5,
                            'ObjectType'  => 'Expenditure',
                            'ObjectId'    => $expenditure->ExpenditureId,
                            'BalanceType' => 'Current',
                        ])->first();

                        if ($depositTransaction) {
                            $depositTransaction->update([
                                'Amount' => $newAmount
                            ]);
                        }

                    } else {

                        DepositTransaction::create([
                            'DepositId'   => $deposit->DepositId,
                            'CustomerId'  => $customerId,
                            'BranchId'    => $branchId,
                            'Type'        => 5,
                            'Amount'      => $newAmount,
                            'ObjectType'  => 'Expenditure',
                            'ObjectId'    => $expenditure->ExpenditureId,
                            'BalanceType' => 'Current',
                            'CreatedBy'   => $expenditure->CreatedBy,
                            'CreatedDate' => Carbon::now()
                        ]);
                    }
                }
            }
            $dataExpenditure['ExpenditureId'] = $expenditure->ExpenditureId;
            $dataExpenditure['CreatedDate'] = $expenditure->CreatedDate;
            $this->saveExpenditureService($treatmentId, $orderDetails, $expenditure->ExpenditureId, $refId);
            $this->onAfterSaveExpenditure($dataExpenditure);

            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('ExpenditureRepository - createExpenditure: ' . $e->getMessage());
            return false;
        }
    }

    public function checkServiceCreateExpenditure($data)
    {
        $orderDetails = $data['OrderDetails'] ?? [];

        $orderDetailIds = [];

        if (!empty($orderDetails)) {
            foreach ($orderDetails as $d) {
                if (!empty($d['OrderDetailId'])) {
                    $orderDetailIds[] = $d['OrderDetailId'];
                }
            }
        }

        $orderDetailIds = array_unique($orderDetailIds);

        if (empty($orderDetailIds)) {
            return true;
        }

        $result = OrderDetailFinancial::whereIn('OrderDetailId', $orderDetailIds)->whereColumn('PaymentAmount', '>=', 'TotalAmount')->first();

        if ($result) {
            Log::error(
                'Dịch vụ đã chi hết tiền, không thể tạo phiếu chi tiếp tục.',
                ['OrderDetailId' => $result->OrderDetailId]
            );
            return false;
        }

        return true;
    }

    public function checkAmountServiceCreateExpenditure($data)
    {
        $orderDetails = $data['OrderDetails'] ?? [];

        if (empty($orderDetails)) {
            return true;
        }

        // Lấy danh sách OrderDetailId
        $orderDetailIds = collect($orderDetails)
            ->pluck('OrderDetailId')
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        if (empty($orderDetailIds)) {
            return true;
        }

        // Query 1 lần
        $financials = OrderDetailFinancial::whereIn('OrderDetailId', $orderDetailIds)
            ->get()
            ->keyBy('OrderDetailId');

        foreach ($orderDetails as $d) {
            if (empty($d['OrderDetailId'])) {
                continue;
            }

            $financial = $financials[$d['OrderDetailId']] ?? null;

            if (!$financial) {
                Log::error(
                    'Không tìm thấy OrderDetailFinancial',
                    ['OrderDetailId' => $d['OrderDetailId']]
                );
                return false;
            }

            $remainAmount = $financial->TotalAmount - $financial->PaymentAmount;

            if ($remainAmount < $d['Amount']) {
                Log::error(
                    'Tồn tại dịch vụ chi tiền lớn hơn số tiền còn lại.',
                    [
                        'OrderDetailId' => $d['OrderDetailId'],
                        'RemainAmount' => $remainAmount,
                        'RequestAmount' => $d['Amount'],
                    ]
                );
                return false;
            }
        }

        return true;
    }

    public function checkIpAddress($ipAddress)
    {
        try {

            $value = Redis::get('common:IPCreatedExpenditure_' . $ipAddress);
            if ($value) {
                return json_decode($value);
            } else {
                $query = DB::table('in.NetworkConfig as nc')->select('bwl.BranchId');
                $query->join('in.BranchWorkLocation as bwl', 'bwl.WorkLocationId', '=', 'nc.WorkLocationId');
                $query->where('nc.WanIp', '=', $ipAddress);
                $query->whereIn('bwl.CompanyId', [1, 2, 3, 13]);
                $query->where('nc.State', '=', 1);
                $result = $query->first();

                if ($result) {
                Redis::set('common:IPCreatedExpenditure_' . $ipAddress, json_encode($result->BranchId));
                return $result->BranchId;
                }
                return NULL;
            }
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return NULL;
        }
    }

    protected function saveExpenditureExhibitExpenditure($expenditureId, $exhibits)
    {
        try {
            if ($expenditureId <= 0) {
                return false;
            }

            if (empty($exhibits)) {
                return true;
            }

            $exhibits = array_values(array_filter(array_map('intval', $exhibits)));

            DB::table('ExpenditureExhibitExpenditure')
                ->where('ExpenditureId', $expenditureId)
                ->when(!empty($exhibits), function ($q) use ($exhibits) {
                    $q->whereNotIn('ExpenditureExhibitId', $exhibits);
                })
                ->when(empty($exhibits), function ($q) {
                    $q->whereRaw('1=1');
                })
                ->delete();

            $existings = DB::table('ExpenditureExhibitExpenditure')
                ->where('ExpenditureId', $expenditureId)
                ->whereIn('ExpenditureExhibitId', $exhibits)
                ->pluck('ExpenditureExhibitId')
                ->toArray();

            $insertData = [];

            foreach ($exhibits as $exhibitId) {
                if (!in_array($exhibitId, $existings, true)) {
                    $insertData[] = [
                        'ExpenditureExhibitId' => $exhibitId,
                        'ExpenditureId'        => $expenditureId,
                    ];
                }
            }

            if (!empty($insertData)) {
                DB::table('ExpenditureExhibitExpenditure')->insert($insertData);
            }

            return true;
        } catch (\Exception $e) {
            Log::error('ExpenditureRepository - saveExpenditureExhibitExpenditure: ' . $e->getMessage());
            throw $e;
        }
    }

    public function saveExpenditureService($treatmentId, $orderDetails, $expenditureId, $customerId)
    {
        try {
            $staffId = Auth::user()['StaffId'];
            if(count($orderDetails) > 0){
                foreach($orderDetails as $d){
                    $expenditureServiceId = ExpenditureService::insertGetId([
                        'ExpenditureId'     => $expenditureId,
                        'TreatmentId'       => $treatmentId,
                        'OrderDetailId'     => $d['OrderDetailId'],
                        'ServiceId'         => $d['ServiceId'],
                        'Amount'            => $d['Amount'],
                        'CreatedBy'         => $staffId,
                        'UpdatedBy'         => $staffId
                    ]);
                    $infoOrderDetail = OrderDetail::where('OrderDetailId', $d['OrderDetailId'])->where('TreatmentId', $treatmentId)->first();
                    OrderDetailFinancialTrans::insert([
                        'TreatmentId'       => $treatmentId,
                        'CustomerId'        => $customerId,
                        'OrderDetailId'     => $d['OrderDetailId'],
                        'OrderChangingId'   => $d['OrderChangingId'],
                        'ServiceId'         => $d['ServiceId'],
                        'ObjectType'        => 'Expenditure',
                        'ObjectId'          => $expenditureId,
                        'ObjectDetailType'  => 'ExpenditureService',
                        'ObjectDetailId'    => $expenditureServiceId,
                        'ExpenditureAmount' => $d['Amount'],
                        'Note'              => 'ADD_EXPENDITURE',
                        'CreatedStaffId'    => $staffId,
                        'CreatedDate'       => Carbon::now()->toDateTimeString(),
                        'ConsultingStaffId'  => isset($infoOrderDetail->ConsultingStaffId) ? $infoOrderDetail->ConsultingStaffId : NULL
                    ]);
                }
            }
        } catch (\Exception $e) {
            Log::error('ExpenditureRepository - saveExpenditureService: ' . $e->getMessage());
            throw $e;
        }
    }

    public function onAfterSaveExpenditure($param)
    {
        try {
            $data = [
                'ExpenditureId' => $param['ExpenditureId'],
                'ExpenditureCode' => $param['ExpenditureCode'],
                'Amount' => $param['Amount'],
                'ReceiverName' => $param['ReceiverName'],
                'Note' => $param['Note'],
                'ExpenditureStatusId' => 5,
                'ExpenditureCategoryId' => $param['ExpenditureCategoryId'],
                'ExpenditureTypeId' => $param['ExpenditureTypeId'],
                'PaymentMethodId' => $param['PaymentMethodId'],
                'ClientCustomerId' => $param['RefId'],
                'BankId' => $param['BankId'],
                'BranchId' => $param['BranchId'],
                'CreatedDate' => $param['CreatedDate']
            ];

            $header = ['Authorization'=>'Bearer ' . JWT_APP_TOKEN];
            $remote = Factory::getRemote();
            $remote->request('module')
                ->from(API_SYNC_EXPENDITURE)
                ->where([
                    'data' => $data,
                    'service_type' => 'kim'
                ])
                ->execute(true, $header);
            
            $response = $remote->loadVar(false);

            return true;

        } catch (\Exception $e) {
            Log::error('ExpenditureRepository - onAfterSaveExpenditure: ' . $e->getMessage());
            throw $e;
        }
        
    }

    public function randomExpenditureCode()
    {
        $prefix  = 'E' . date('y') . date('m');
        $newCode = $prefix . '001';

        $maxCode = Expenditure::where('ExpenditureCode', 'like', $prefix . '%')
            ->selectRaw('MAX(CAST(SUBSTRING(ExpenditureCode, 6) AS UNSIGNED)) as max_code')
            ->value('max_code');

        if ($maxCode) {
            $newNum = (int)$maxCode + 1;
            $newCode = $prefix . str_pad($newNum, 3, '0', STR_PAD_LEFT);
        }

        return $newCode;
    }

}