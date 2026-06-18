<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Repositories\HealthRecordsRepository;
use App\Repositories\PDFRepository;
use Illuminate\Support\Facades\Validator;
use App\Exports\ReportExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Log;

class HealthRecordsController extends Controller
{

    public function exportHealthRecords(Request $request)
    {
        $healthRecordsRepo = new HealthRecordsRepository;

        // Validate input
        $validator = Validator::make($request->all(), [
            'FromDate'  => 'required|string',
            'ToDate'    => 'required|string',
            'BranchIds' => 'required'
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'ERR001', self::$ERROR);
            return $this->json(false, 'bool');
        }

        $fromDate = $request->input('FromDate');
        $toDate   = $request->input('ToDate');
        $branchId = $request->input('BranchIds');

        try {
            $array = $healthRecordsRepo->exportHealthRecords($fromDate, $toDate, $branchId);

            $arrChiefComplaint = [
                'R0' => 'Muốn làm răng',
                'R1' => 'Răng mọc lệch/khấp khểnh',
                'R2' => 'Đau răng',
                'R3' => 'Mất răng',
                'R4' => 'Khám định kỳ',
            ];

            $groupedArray = [];
            $data = [];

            foreach ($array as $item) {
                $chief = $item['ChiefComplaint'] ?? '';
                if (is_string($chief)) {
                    $chief = str_replace(['[', ']', '"'], '', $chief);
                    $chief = $chief !== '' ? explode(",", $chief) : [];
                } else {
                    $chief = [];
                }

                $item['ChiefComplaint'] = $healthRecordsRepo->replaceChiefComplaint($chief, $arrChiefComplaint);

                if(date('Y-m-d', strtotime($item['AdmittedTime'])) == date('Y-m-d', $item['StartAt'])) {
                    $groupedArray[] = $item;
                }
            }

            foreach ($groupedArray as $value) {

                // Diagnosis JSON safe
                $diagnosis = '';
                if (!empty($value['DiseaseProgressionNote'])) {
                    $dp = json_decode($value['DiseaseProgressionNote']);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $diagnosis = $dp->CheckinPatientStatus->Diagnosis ?? '';
                    }
                }

                // Services JSON safe
                $listServiceName = [];
                if (!empty($value['NextTimeTreatmentNote'])) {
                    $note = json_decode($value['NextTimeTreatmentNote']);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $HC6 = $note->OptionData->HC6 ?? [];
                        if (is_array($HC6)) {
                            foreach ($HC6 as $v) {
                                if (isset($v->service)) {
                                    $serviceCode = isset($v->objects) ? implode(', ', (array)$v->objects) : '';
                                    $listServiceName[] = $v->service . ' - ' . $serviceCode;
                                }
                            }
                        }
                    }
                }

                $service = implode(', ', $listServiceName);

                // Gender
                $gender = ((int)($value['Gender'] ?? 1) === 1) ? "Nam" : "Nữ";

                // Safe address
                $add = trim(
                    ($value['Address'] ?? '') . 
                    (!empty($value['LabelWard']) ? ', '.$value['LabelWard'] : '') .
                    (!empty($value['NameWard']) ? ' '.$value['NameWard'] : '') .
                    (!empty($value['LabelDistrict']) ? ', '.$value['LabelDistrict'] : '') .
                    (!empty($value['NameDistrict']) ? ' '.$value['NameDistrict'] : '') .
                    (!empty($value['LabelProvince']) ? ', '.$value['LabelProvince'] : '') .
                    (!empty($value['NameProvince']) ? ' '.$value['NameProvince'] : '')
                );

                $data[] = [
                    'Ngày điều trị'       => $value['AdmittedTime'] ? date('d-m-Y', strtotime($value['AdmittedTime'])) : '',
                    'Mã phòng khám'        => $value['BranchCode'] ?? '',
                    'Tên phòng khám'       => $value['Name'] ?? '',
                    'Mã khách hàng'        => $value['CustomerCode'] ?? '',
                    'Họ và tên'            => $value['FullName'] ?? '',
                    'Giới tính'            => $gender,
                    'Năm sinh'             => !empty($value['Birthday']) ? date('Y', strtotime($value['Birthday'])) : '',
                    'Số thẻ BHYT'          => '',
                    'Địa chỉ'              => $add,
                    'Nghề nghiệp'          => $value['JobName'] ?? '',
                    'Dân tộc'              => $value['EthnicName'] ?? '',
                    'Triệu chứng'          => $value['ChiefComplaint'] ?? '',
                    'Chẩn đoán'            => $diagnosis,
                    'Phương pháp điều trị' => $service,
                    'Y, BS khám bệnh'      => $value['DoctorName'] ?? '',
                    'Ghi chú'              => '',
                ];
            }

            // Nếu không có data
            if (empty($data)) {
                $data[] = array_fill_keys([
                    'Ngày điều trị', 'Mã phòng khám', 'Tên phòng khám', 'Mã khách hàng',
                    'Họ và tên', 'Giới tính', 'Năm sinh', 'Số thẻ BHYT', 'Địa chỉ',
                    'Nghề nghiệp', 'Dân tộc', 'Triệu chứng', 'Chẩn đoán',
                    'Phương pháp điều trị', 'Y, BS khám bệnh', 'Ghi chú'
                ], '');
            }

            return $this->json([$this->formatData('ListHealthRecords', $data)]);

        } catch (\Exception $e) {
            Log::error($e->getMessage());
            $this->addMessage("Xuất sổ khám bệnh A4 không thành công!", 'EHR0003', self::$ERROR);
            return $this->json(false, 'bool');
        }
    }
}