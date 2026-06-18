<?php


namespace App\Imports;

use App\Branch;
use App\Exports\ReportExport;
use App\MonthlyBonus;
use App\MonthlyBonusDetail;
use App\RatingSummaryByMonth;
use App\Repositories\CampaignImportRepository;
use App\Repositories\StaffRepository;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStartRow;
use Maatwebsite\Excel\Events\BeforeImport;
use Maatwebsite\Excel\Facades\Excel;

class RatingSummaryImport implements ToCollection, WithChunkReading, WithStartRow
{
    protected $ratingDate;

    protected $invalidCount = 0;

    private $totalRows = 0;

    private $currentRow = 0;

    public function __construct($ratingDate)
    {
        $this->ratingDate = $ratingDate;
    }

    public function collection(Collection $rows)
    {
        $invalidRecords = [];

        foreach ($rows as $row)
        {
            $this->currentRow++;
            $isValid = true;
            $_reason = [];
            if (!isset($row[0]) || empty($row[0])) {
                continue;
            }
            //BranchCode are required
            $row[1] = trim($row[1] ?? '');
            //Valid BranchCode
            if (!trim($row[1])) {
                $_reason[] = 'Dữ liệu không có Mã chi nhánh';
                if ($isValid) {
                    $this->invalidCount++;
                    $isValid = false;
                }
            }
            $branch = Branch::where('BranchCode', $row[1])->where('State', 1)->first();
            if (!$branch || empty($branch)) {
                $_reason[] = 'Chi nhánh không hợp lệ';
                if ($isValid) {
                    $this->invalidCount++;
                    $isValid = false;
                }
            }
            //Valid Rating
            if (!trim($row[2]) || !is_numeric($row[2])) {
                $_reason[] = 'Số điểm không hợp lệ';
                if ($isValid) {
                    $this->invalidCount++;
                    $isValid = false;
                }
            }
            //Valid Count
            if (!trim($row[3]) || !is_numeric($row[3])) {
                $_reason[] = 'Số lượng không hợp lệ';
                if ($isValid) {
                    $this->invalidCount++;
                    $isValid = false;
                }
            }
            $this->ratingDate = date('Y-m-01', strtotime($this->ratingDate));

            $data = [
                'RatingDate' => $this->ratingDate ?? date('Y-m-01'),
                'BranchId' => $branch->BranchId ?? 0,
                'RatingValue' => trim($row[2]),
                'RatingNumber' => trim($row[3]),
                'CreatedDate' => Carbon::now()->toDateTimeString(),
                'CreatedBy' => Auth::user()['StaffId'] ?? 0,
                'UpdatedDate' => Carbon::now()->toDateTimeString(),
                'UpdatedBy' => Auth::user()['StaffId'] ?? 0,
            ];

            if (!$isValid) {
                $invalidRecords[] = $data;
                continue;
            }
            RatingSummaryByMonth::updateOrCreate([
                'RatingDate' => $data['RatingDate'] ?? date('Y-m-01'),
                'BranchId' => $data['BranchId'] ?? 0
            ], $data);
            $this->totalRows++;
        }
        return $this->invalidCount;
    }

    public function startRow(): int
    {
        return 2;
    }

    public function chunkSize(): int
    {
        return 1000;
    }

    public function getInvalidCount(): int
    {
        return $this->invalidCount;
    }

    public function getTotalRows(): int
    {
        return $this->totalRows;
    }
}