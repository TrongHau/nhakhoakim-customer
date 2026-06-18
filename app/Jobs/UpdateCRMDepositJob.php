<?php

namespace App\Jobs;

use Illuminate\Support\Facades\Log;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Receipt;
use App\Libs\Factory;

class UpdateCRMDepositJob extends Job implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    public $queue   = 'queue_v2';
    public $tries   = 3;
    public $timeout = 30;

    protected $customerId;
    protected $receiptId;
    protected $appointmentId;
    protected $totalAmount;

    public function __construct($customerId, $receiptId, $appointmentId, $totalAmount)
    {
        $this->customerId    = $customerId;
        $this->receiptId     = $receiptId;
        $this->appointmentId = $appointmentId;
        $this->totalAmount   = $totalAmount;
    }

    public function handle()
    {
        $receipt = Receipt::find($this->receiptId);

        if (!$receipt) {
            // Không retry nếu receipt không tồn tại
            $this->fail(new \Exception("Receipt not found: {$this->receiptId}"));
            return;
        }

        if ($this->totalAmount <= 0) {
            $this->fail(new \Exception("Invalid totalAmount: {$this->totalAmount}"));
            return;
        }

        $data = [
            'CustomerId'    => $this->customerId,
            'ReceiptId'     => $this->receiptId,
            'AddedAt'       => $receipt->AddedAt,
            'AppointmentId' => $this->appointmentId,
            'TotalAmount'   => $this->totalAmount,
        ];

        $header = ['Authorization' => 'Bearer ' . JWT_APP_TOKEN];
        $remote = Factory::getRemote();
        $remote->request('module')
            ->from(API_SYNC_PAYMENT_APPOINTMENT)
            ->where([
                'data'         => $data,
                'service_type' => 'kim',
            ])
            ->execute(true, $header);

        $response = $remote->loadVar(false);

        Log::info('updateCRMDeposit success', [
            'ReceiptId'  => $this->receiptId,
            'CustomerId' => $this->customerId,
            'response'   => $response,
        ]);
    }

    public function failed(\Throwable $e)
    {
        Log::error('updateCRMDeposit failed all retries', [
            'CustomerId'    => $this->customerId,
            'ReceiptId'     => $this->receiptId,
            'AppointmentId' => $this->appointmentId,
            'TotalAmount'   => $this->totalAmount,
            'Error'         => $e->getMessage(),
        ]);
    }
}