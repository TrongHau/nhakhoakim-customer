<?php

namespace App\Jobs;

use App\Libs\Logger\TelegramLogger;
use Illuminate\Support\Facades\Log;
use App\Receipt;
use App\Branch;
use App\CustomerPhoneNumber;
use App\Libs\Factory;

class InsertDepositSendZNSJob extends Job
{
    protected $customerId;
    protected $receiptId;
    protected $totalAmount;
    protected $branchId;
    protected $type;

    /**
     * Create a new job instance.
     *
     * @param int $customerId
     * @param int $receiptId
     * @param float $totalAmount
     * @param int $branchId
     * @param string $type ('Add' or 'Edit')
     * @return void
     */
    public $queue = 'queue_v2';

    public function __construct($customerId, $receiptId, $totalAmount, $branchId, $type)
    {
        $this->customerId = $customerId;
        $this->receiptId = $receiptId;
        $this->totalAmount = $totalAmount;
        $this->branchId = $branchId;
        $this->type = $type;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        Log::info('[debug jobs] UpdateCRMDepositJob dispatched OK');

        $msg = "<b>Test queue: InsertDepositSendZNSJob</b>\n";
        TelegramLogger::send($msg);

        return true;
        try {
            $customerId = $this->customerId;
            $receiptId = $this->receiptId;
            $totalAmount = $this->totalAmount;
            $branchId = $this->branchId;
            $type = $this->type;

            $receipt = Receipt::find($receiptId);

            if (!$receipt) {
                throw new \Exception("Receipt not found: {$receiptId}");
            }

            $receiptCode = $receipt->ReceiptCode;

            $branch = Branch::find($branchId);

            if (!$branch) {
                throw new \Exception("Branch not found: {$branchId}");
            }

            $customer = CustomerPhoneNumber::from('CustomerPhoneNumber as cp')
                ->join('Customer as c', 'c.CustomerId', '=', 'cp.CustomerId')
                ->where('cp.CustomerId', $customerId)
                ->where('cp.IsMain', 1)
                ->select('cp.PhoneNumber', 'c.FullName', 'c.CustomerCode')
                ->first();

            if (!$customer) {
                throw new \Exception("Customer phone number not found for customer: {$customerId}");
            }

            $url = API_SEND_DEPOSIT_CUSTOMER;
            if ($type == 'Add') {
                $url = API_SEND_DEPOSIT_CUSTOMER;
            } else {
                $url = API_SEND_EDIT_DEPOSIT_CUSTOMER;
            }

            $header = ['Authorization' => 'Bearer ' . JWT_APP_TOKEN];
            $remote = Factory::getRemote();
            $remote->request('module')
                ->from($url)
                ->where([
                    'CustomerName' => $customer->FullName ?? '',
                    'CustomerCode' => $customer->CustomerCode ?? '',
                    'PhoneNumber' => $customer->PhoneNumber,
                    'Date' => date('H:i d/m/Y'),
                    'BranchAddress' => $branch->Address ?? '',
                    'Amount' => number_format($totalAmount ?? 0, 0, ',', '.'),
                    'InvoiceCode' => $receiptCode ?? ''
                ])
                ->execute(true, $header);

            $response = $remote->loadVar(false);
            Log::info("SEND_DEPOSIT_CUSTOMER response", [$response]);

            return true;

        } catch (\Throwable $e) {

            Log::error('insertDepositSendZNS failed', [
                'CustomerId' => $this->customerId,
                'ReceiptId' => $this->receiptId,
                'TotalAmount' => $this->totalAmount,
                'BranchId' => $this->branchId,
                'Type' => $this->type,
                'Error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}

