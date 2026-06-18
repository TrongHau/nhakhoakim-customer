<?php

namespace App\Repositories;

use App\Repositories\Abstracts\EloquentRepository;
use App\Customer;
use App\CustomerInvoiceConfiguration;
use App\CustomerEmail;
use App\ParamConfig;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class CustomerGatewayRepository extends EloquentRepository
{
    protected function getModel()
    {
        return Customer::class;
    }

    public function checkUrlHIS($customerCode)
    {
        $paramConfig = ParamConfig::where('ObjectCode', 'URLExpirationTime')->first();
        if (!$paramConfig) {
            Log::error('URLExpirationTime config not found');
            return false;
        }
        $now = Carbon::now();
        $nowDate = $now->format('Y-m-d');

        $URLExpirationDate = $nowDate . ' ' . $paramConfig->ObjectValue;

        // Nếu quá URLExpirationTime thì return false
        if ($now > Carbon::parse($URLExpirationDate)) {
            return false;
        }

        $startDay = Carbon::today()->timestamp;
        $currentTimestamp = $now->timestamp;

        $customer = Customer::where('Customer.CustomerCode', $customerCode)
            ->join('Deposit as d', 'd.CustomerId', '=', 'Customer.CustomerId')
            ->join('Receipt as r', 'r.DepositId', '=', 'd.DepositId')
            ->whereBetween('r.AddedAt', [$startDay, $currentTimestamp])
            ->first();

        return $customer ? true : false;
    }

    public function saveCustomerHIS($customerInfo)
    {
        $customerCode = isset($customerInfo['CustomerCode'])
            ? trim($customerInfo['CustomerCode'])
            : '';

        if (empty($customerCode)) {
            return false;
        }

        $customer = Customer::where('CustomerCode', $customerCode)->first();

        if (!$customer) {
            return false;
        }

        DB::beginTransaction();

        try {

            $fullName   = isset($customerInfo['FullName']) ? trim($customerInfo['FullName']) : '';
            $address    = isset($customerInfo['Address']) ? trim($customerInfo['Address']) : '';
            $provinceId = isset($customerInfo['ProvinceId']) ? $customerInfo['ProvinceId'] : null;
            $wardId     = isset($customerInfo['VnWardId']) ? $customerInfo['VnWardId'] : null;

            $isChanged = false;

            if ($customer->FullName != $fullName && !empty($fullName)) {
                $customer->FullName = $fullName;
                $isChanged = true;
            }

            if ($customer->Address != $address && !empty($address) && empty($customerInfo['CompanyName'])) {
                $customer->Address = $address;
                $isChanged = true;
            }

            if ($customer->ProvinceId != $provinceId && $provinceId != 0) {
                $customer->ProvinceId = $provinceId;
                $customer->WardId = NULL; // reset ward if province changed
                $isChanged = true;
            }

            if ($customer->WardId != $wardId && $wardId != 0) {
                $customer->WardId = $wardId;
                $isChanged = true;
            }

            if ($isChanged && !$customer->save()) {
                throw new \Exception('Failed saving customer');
            }

            // Invoice
            if (!empty($customerInfo['CompanyName'])) {

                $invoice = CustomerInvoiceConfiguration::where(
                    'CustomerId',
                    $customer->CustomerId
                )->first();

                if (!$invoice) {
                    CustomerInvoiceConfiguration::insert([
                        'CustomerId' => $customer->CustomerId,
                        'CompanyName' => trim($customerInfo['CompanyName']),
                        'CompanyAddress' => $address,
                        'TaxNumber' => isset($customerInfo['TaxNumber']) ? trim($customerInfo['TaxNumber']) : '',
                        'State' => 1,
                        'UpdatedDate' => date('Y-m-d H:i:s'),
                        'UpdatedBy' => 0
                    ]);
                } else {
                    $invoice->CompanyName    = trim($customerInfo['CompanyName']);
                    $invoice->CompanyAddress = $address;
                    $invoice->TaxNumber      = isset($customerInfo['TaxNumber'])
                        ? trim($customerInfo['TaxNumber'])
                        : '';

                    if (!$invoice->save()) {
                        throw new \Exception('Failed saving invoice config');
                    }
                }
            }

            // Email
            $email = isset($customerInfo['Email'])
                ? strtolower(trim($customerInfo['Email']))
                : '';

            if (!empty($email)) {

                $customerEmail = CustomerEmail::where('CustomerId', $customer->CustomerId)
                    ->where('Email', $email)
                    ->first();

                if (!$customerEmail) {
                    CustomerEmail::insert([
                        'CustomerId' => $customer->CustomerId,
                        'Email' => $email,
                        'AddedAt' => time(),
                        'IsPrimary' => 1
                    ]);
                } else {
                    $customerEmail->IsPrimary = 1;
                    $customerEmail->AddedAt = time();

                    if (!$customerEmail->save()) {
                        throw new \Exception('Failed saving email');
                    }
                }
                // reset others AFTER save success
                CustomerEmail::where('CustomerId', $customer->CustomerId)
                    ->where('Email', '!=', $email)
                    ->update(['IsPrimary' => 0]);
            }

            DB::commit();
            return true;

        } catch (\Exception $e) {

            DB::rollBack();
            Log::error('Error saving customer HIS: ' . $e->getMessage());
            return false;
        }
    }
}