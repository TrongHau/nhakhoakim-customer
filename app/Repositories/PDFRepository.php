<?php

namespace App\Repositories;

use App\Repositories\Abstracts\EloquentRepository;
use App\Customer;
use App\Libs\Factory;
use App\Treatment;
use Barryvdh\DomPDF\Facade as PDF;
use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class PDFRepository extends EloquentRepository
{
    protected function getModel()
    {
        return Customer::class;
    }

    public function add($customerId, $treatmentId, $content, $branchId)
    {
        $userId = Auth::user()['UserId'];
        $staff = DB::table('in.Staff')->where('UserId', $userId)->first();
        if ($staff && !empty($staff)) {
            $staffId = $staff->StaffId ?? 0;
        }
        $data = [
            'CustomerId' => $customerId,
            'TreatmentId' => $treatmentId,
            'Content' => $content,
            'BranchId' => $branchId,
            'Status' => 5,
            'CreatedDate' => Carbon::now(),
            'CreatedBy' => $staffId
        ];
        try {

            DB::beginTransaction();
            $treatmentPackagePlaningId = DB::table('pos.TreatmentPackagePlaning')->insertGetId($data);
            DB::commit();
            if ($treatmentPackagePlaningId && !empty($treatmentPackagePlaningId)) {
                $this->sendExportPDF($treatmentPackagePlaningId);
            }
        } catch (\Exception $e) {
            DB::rollback();
            Log::error("func add PDFRepository", [$e->getMessage()]);
            return false;
        }
        return true;
    }

    public function list($customerId)
    {

        $query = DB::table('pos.TreatmentPackagePlaning as tpp');
        $query->select('tpp.CustomerId', 'tpp.TreatmentId', 'tpp.BranchId', 'tpp.Status', 'tpp.CDNURL', 'tpp.CreatedDate', 'tpp.CreatedDate', 'tpp.CreatedBy', 's.FullName');
        $query->join('in.Staff as s', 's.StaffId', '=', 'tpp.CreatedBy');
        $query->where('tpp.CustomerId', '=', $customerId);
        $query->orderByDesc('tpp.CreatedDate');

        return $query->get()->toArray();
    }

    public function detail($customerId)
    {

        $query = DB::table('Customer as c');
        $query->select('c.CustomerId', 'c.CustomerCode', 'c.FullName', 'c.Birthday', 'c.Gender', 't.ChiefComplaint', 't.StartDate');
        $query->join('Treatment as t', 'c.CustomerId', '=', 't.PersonId');
        $query->where('c.CustomerId', '=', $customerId);
        $query->whereNull('t.ClosedBy')->limit(1);

        return $query->get()->toArray();
    }

    public function convertHtmlToPdf($html)
    {

        $html = preg_replace('/>\s+</', "><", $html);
        $pdf = PDF::loadHTML($html)->setPaper('a4', 'landscape');
        $storagePath = storage_path('app/public/pdfs');
        if (!file_exists($storagePath)) {
            mkdir($storagePath, 0755, true);
        }
        $fileName = '' . time() . '.pdf';
        $pdf->save($storagePath . $fileName);
        $urlFile = self::uploadFileToServerMin($storagePath . $fileName, 'TreatmentImage', $fileName);
        return $urlFile;
    }

    public static function uploadFileToServerMin($file = null, $saveDir = '', $fileName, $saveFileName = false)
    {
        $bucketName = 'pos';
        $savePath = !empty($saveDir) ? 'files/year_' . date('Y') . '/' . $saveDir :  'files/year_' . date('Y') . '/file';

        if (empty($file)) {
            return false;
        }

        if (!$saveFileName) {
            $fileName = $savePath . '/' . $fileName;
        }

        $s3 = new S3Client([
            'version' => 'latest',
            'region'  => 'us-east-1',
            'endpoint' => API_MEDIA,
            'use_path_style_endpoint' => true,
            'credentials' => [
                'key'    => PUBLIC_MEDIA_KEY,
                'secret' => SECRET_MEDIA_KEY,
            ],
        ]);
        try {

            $res = $s3->putObject([
                'Bucket' => $bucketName,
                'Key'    => $fileName,
                'SourceFile'   => $file,
                'ContentType' => mime_content_type($file)
            ]);

            return API_MEDIA . '/' . $bucketName . '/' . $fileName;
        } catch (S3Exception $e) {
            Log::info('Helper uploadFileToServerMin S3Exception ', [$e->getMessage()]);
            return false;
        } catch (\Exception $exception) {
            Log::error("Helper uploadFileToServerMin fail", [$exception->getMessage()]);
            return false;
        }
        return false;
    }

    public function sendExportPDF($treatmentPackagePlaningId)
    {
        if (!$treatmentPackagePlaningId || empty($treatmentPackagePlaningId)) {
            return false;
        }
        try {
            Log::info("==== START Send Customer Treatment Package Planing: " . ($treatmentPackagePlaningId ?? 0) . " ===");
            $header = ['Authorization' => 'Bearer ' . JWT_APP_TOKEN];
            $remote = Factory::getRemote();
            $remote->request('module')
                ->from(API_QUEUE_EXPORT_CUSTOMER_TREATMENT_PLANING)
                ->where([
                    'TreatmentPackagePlaningId' => $treatmentPackagePlaningId ?? ''
                ])
                ->execute(true, $header);

            $response = $remote->loadVar(false);
            Log::info("Remote Send Customer Treatment Package Planing url:", [API_QUEUE_EXPORT_CUSTOMER_TREATMENT_PLANING]);
            // Log::info("Remote Send Export Customer Paper Work header:", $header);
            // Log::info("Remote Send Export Customer Paper Work data:", $data);
            Log::info("Remote Send Customer Treatment Package Planing response", [$response]);
            if ((isset($response->code) && $response->code == false) || !$response) {
                return false;
            }
            return true;
            Log::info("==== END Send Customer Treatment Package Planing ===");
        } catch (\Exception $e) {
            Log::error("Error sendExportPDF", [$e->getMessage()]);
            return false;
        }
    }
}
