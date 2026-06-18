<?php


namespace App\Imports;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Receipt;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Illuminate\Support\MessageBag;
use Illuminate\Support\Facades\Auth;
use App\Staff;

use function GuzzleHttp\json_encode;

class InsuranceReceiptImport implements ToModel, WithHeadingRow
{
    protected $errors; // Đối tượng để lưu trữ thông báo lỗi

    public function __construct()
    {
        $this->errors = new MessageBag;
    }

    public function model(array $row) {

        $receipt = Receipt::where('ReceiptCode', trim($row['ma_phieu_thu']))->first();
        Log::info('---InsuranceReceiptImport---', [json_encode($receipt)]);
        $userId = Auth::user()['UserId'];
        $staff = DB::table('in.Staff')->where('UserId', $userId )->first();
        if ($staff && !empty($staff)) {
            $staffId = $staff->StaffId ?? 0;
        }
        if ($receipt) {
            $totalPayment = trim($row['so_tien_thanh_toan']);
            if ((((int)$totalPayment) <= ((int)$receipt->InsuranceAmount))) {
                if((int)$totalPayment > 0){
                    if($receipt->InsuranceSendDate != ''){
                        DB::table('pos.Receipt')->where('ReceiptCode','=', trim($row['ma_phieu_thu']))
                        ->update([
                            'InsurancePaidAmount' => (int)$totalPayment,
                            'InsuranceUpdatedDate' => date('Y-m-d H:m:s'),
                            'InsuranceUpdatedBy' => $staffId
                        ]);
                        DB::table('pos.ReceiptTracking')
                        ->insert([
                            'ReceiptId' => $receipt->ReceiptId,
                            'ActionId' => 40,
                            'NewData' => json_encode([
                                'Receipt' => [
                                    'InsurancePaidAmount' => (int)$totalPayment,
                                    'InsuranceUpdatedDate' => date('Y-m-d H:m:s'),
                                    'InsuranceUpdatedBy' => $staffId
                                ]
                            ]),
                            'OldData' => json_encode([
                                'Receipt' => $receipt->toArray()
                            ]),
                            'CreatedBy' => $staffId,
                            'CreatedDate' => date('Y-m-d H:m:s'),
                        ]);
                    }else{
                        $this->errors->add('IPR0001', 'Dòng số ' .$row['stt'] . ' lỗi. Ngày nộp hồ sơ chưa có.');
                    }
                }
            }else{
                $this->errors->add('IPR0001', 'Dòng số ' .$row['stt'] . ' lỗi. Số tiền thanh toán của Mã phiếu thu ' . trim($row['ma_phieu_thu']) . ' phải nhỏ hơn hoặc bằng số tiền phiếu thu.');
            }
        }else{
            $this->errors->add('IPR0001', 'Dòng số ' .$row['stt'] . ' lỗi. Mã phiếu thu: ' . trim($row['ma_phieu_thu']) . ' không tồn tại.');
        }
    }

    public function rules(): array
    {
        return [
            'ReceiptCode' => 'required'
        ];
    }

    public function startRow(): int
    {
        return 2;
    }

    public function chunkSize(): int
    {
        return 1000;
    }

    public function getErrors()
    {
        return $this->errors;
    }
}