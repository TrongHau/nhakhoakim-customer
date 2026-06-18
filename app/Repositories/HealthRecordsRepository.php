<?php

namespace App\Repositories;

use App\Repositories\Abstracts\EloquentRepository;
use App\TreatmentHistory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;
use Maatwebsite\Excel\Concerns\ToArray;
use Illuminate\Support\Facades\DB;

class HealthRecordsRepository extends EloquentRepository
{
   /**
    * @return string
    */
    protected function getModel()
    {
        return TreatmentHistory::class;
    }

    public function exportHealthRecords ($fromDate, $toDate, $branchId)
    {
        $query = $this->_model->newQuery();
        $query->select([
            'TreatmentHistory.AdmittedTime',
            'TreatmentHistory.TreatmentHistoryId',
            'TreatmentHistory.DiseaseProgressionNote',
            'TreatmentHistory.NextTimeTreatmentNote',
            'b.BranchCode',
            'b.Name',
            'c.CustomerCode',
            'c.FullName',
            'c.Gender',
            'e.Name as EthnicName',
            'c.Birthday',
            'c.Address',
            'w.LabelVi as LabelWard',
            'w.NameVi as NameWard',
            'd.LabelVi as LabelDistrict',
            'd.NameVi as NameDistrict',
            'p.LabelVi as LabelProvince',
            'p.NameVi as NameProvince',
            'c.JobName',
            'b.Name',
            'b.BranchCode',
            's.FullName as DoctorName',
            'a.StartAt',
            't.ChiefComplaint'
        ]);
        $query->join('pos.Customer as c', 'c.PersonId', '=', 'TreatmentHistory.PersonId');
        $query->join('pos.Appointment as a', 'a.CustomerId', '=', 'c.CustomerId');
        $query->join('pos.Treatment as t', 't.TreatmentId', '=', 'TreatmentHistory.TreatmentId');
        $query->join('in.Staff as s', 's.StaffId', '=', 'TreatmentHistory.UpdatedBy');
        $query->join('in.Branch as b', 'b.BranchId', '=', 'a.AtBranchId');
        $query->leftJoin('pos.VnWard as w', 'w.VnWardId', '=', 'c.WardId');
        $query->leftJoin('pos.VnDistrict as d', 'd.VnDistrictId', '=', 'c.DistrictId');
        $query->leftJoin('pos.VnProvince as p', 'p.VnProvinceId', '=', 'c.ProvinceId');
        $query->leftJoin('in.Ethnic as e', 'e.EthnicId', '=', 'c.EthnicId');
        if(count($branchId)){
            $query->whereIn('a.AtBranchId', $branchId);
        }
        $query->where('TreatmentHistory.AdmittedTime', '>=', $fromDate." 00:01");
        $query->where('TreatmentHistory.AdmittedTime', '<=', $toDate." 23:59");
        $query->where('a.StartAt', '>=', strtotime($fromDate." 00:01"));
        $query->where('a.StartAt', '<=', strtotime($toDate." 23:59"));
        $query->where('a.AppointmentStatusId', '>', 11);

        $results = $query->get()->toArray();

        return $results;
    }

    public function replaceChiefComplaint($chiefComplaints, array $arrChiefComplaint = [])
    {
        if ($chiefComplaints === null || $chiefComplaints === '' || (is_array($chiefComplaints) && empty($chiefComplaints))) {
            return '';
        }
        if (is_array($chiefComplaints)) {
            $codes = $chiefComplaints;
        } else {
            $decoded = json_decode($chiefComplaints, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $codes = $decoded;
            } else {
                $clean = str_replace(['[', ']', '"'], '', trim($chiefComplaints));
                if ($clean === '') {
                    return '';
                }
                $codes = array_map('trim', explode(',', $clean));
            }
        }

        $result = [];
        foreach ($codes as $v) {
            if ($v === null) continue;
            $v = trim((string)$v);
            if ($v === '') continue;

            if (strpos($v, '|@|') !== false) {
                $parts = explode('|@|', $v);
                $codePart = trim($parts[0] ?? '');
                $textPart = trim($parts[1] ?? '');
                if ($textPart !== '') {
                    $result[] = $textPart;
                    continue;
                }
                $v = $codePart !== '' ? $codePart : $v;
            }
            if (isset($arrChiefComplaint[$v])) {
                $result[] = $arrChiefComplaint[$v];
            } else {
                $result[] = $v;
            }
        }

        return implode(', ', $result);
    }
}