<?php

namespace App\Repositories;

use App\Repositories\Abstracts\EloquentRepository;
use App\CustomerBank;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use App\Bank;

class CustomerBankRepository extends EloquentRepository
{
    protected function getModel()
    {
        return CustomerBank::class;
    }

    public function addCustomerBank($customerId,$bankId,$bankAccNumber,$bankAccName) {

        $staffId = 0;
        $userId = Auth::user()['UserId'];
        $staff = DB::table('in.Staff')->where('UserId', $userId )->first();
        if ($staff && !empty($staff)) {
            $staffId = $staff->StaffId ?? 0;
        }
        $data = [
            'CustomerId'    => $customerId,
            'BankId'        => $bankId,
            'BankAccNumber'    => $bankAccNumber,
            'BankAccName'      => $bankAccName,
            'CreatedBy'     => $staffId,
            'CreatedDate'   => Carbon::now(),
            'Status'        => 1
        ];
        try {

            DB::beginTransaction();
            $this->create($data);
            DB::commit();
            
        } catch (\Exception $e) {
            DB::rollback();
            Log::error("addCustomerBank error", [$e->getMessage()]);
            return false;
        }
        return true;
    }

    public function listCustomerBank($customerId) {

        $query = DB::table('pos.CustomerBank as cb');
        $query->select('cb.*','b.NameVi as BankName');
        $query->join('in.Bank as b','cb.BankId','=','b.BankId');
        $query->where('cb.CustomerId','=',$customerId)->where('Status',1);

        return $query->get();
    }

    public function listBank() {

        $results = Bank::where('State', 1)->orderBy('Priority')->get();
        
        return $results ?? [];
    }

    public function deleteCustomerBank($customerBankId) {

        $staffId = 0;
        $userId = Auth::user()['UserId'];
        $staff = DB::table('in.Staff')->where('UserId', $userId )->first();
        if ($staff && !empty($staff)) {
            $staffId = $staff->StaffId ?? 0;
        }
        try {

            DB::beginTransaction();
            $this->_model::where('CustomerBankId', $customerBankId)->update([
                'Status'        => 0,
                'UpdatedBy'     => $staffId,
                'UpdatedDate'   =>  Carbon::now()
            ]);
            DB::commit();
            
        } catch (\Exception $e) {
            DB::rollback();
            Log::error("deleteCustomerBank error", [$e->getMessage()]);
            return false;
        }
        return true;
    }

    public function updateCustomerBank($customerBankId,$bankId,$bankAccNumber,$bankAccName) {
        
        $staffId = 0;
        $userId = Auth::user()['UserId'];
        $staff = DB::table('in.Staff')->where('UserId', $userId )->first();
        if ($staff && !empty($staff)) {
            $staffId = $staff->StaffId ?? 0;
        }
        try {

            DB::beginTransaction();
            $this->_model::where('CustomerBankId', $customerBankId)->update([
                'BankId'        => $bankId,
                'BankAccNumber'    => $bankAccNumber,
                'BankAccName'      => $bankAccName,
                'UpdatedBy'     => $staffId,
                'UpdatedDate'   =>  Carbon::now()
            ]);
            DB::commit();
            
        } catch (\Exception $e) {
            DB::rollback();
            Log::error("updateCustomerBank error", [$e->getMessage()]);
            return false;
        }
        return true;
    }
}