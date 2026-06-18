<?php

namespace App\Repositories;

use App\Appointment;
use App\AppointmentDoctorAssistant;
use App\Doctor;
use App\Libs\Factory;
use App\ParamConfig;
use App\AppointmentTemp;
use App\AppointmentExtend;
use App\Repositories\Abstracts\EloquentRepository;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AppointmentRepository extends EloquentRepository
{
   /**
    * @return string
    */
   protected function getModel(): string
   {
      //Required model
      return Appointment::class;
   }

   public function checkConditionDoctorOfAppointment($data)
   {
      if (!isset($data['AppointmentId'])) {
         return false;
      }
      $lastAppointmentStatusAccept = ParamConfig::where('ObjectCode', 'AppointmentStatusAcceptChangeDoctor')->first();
      $appointmentStatus = 61; //Đã thanh toán
      if ($lastAppointmentStatusAccept && !empty($lastAppointmentStatusAccept)) {
         $appointmentStatus = $lastAppointmentStatusAccept->ObjectValue ?? 0;
      }
      $appointment = $this->_model->where('AppointmentId', $data['AppointmentId'] ?? 0)->first();
      if (!$appointment || empty($appointment)) {
         return false;
      }
      if (!isset($appointment->AppointmentStatusId) || empty($appointment->AppointmentStatusId)) {
         return false;
      }
      if ($appointment->AppointmentStatusId > $appointmentStatus) {
         return false;
      }
      if ($appointment->AppointmentStatusId == 1) { //Huỷ hẹn
         return false;
      }
      return true;
   }
   
   public function changeDoctorOfAppointment($data)
   {
      if (!isset($data['AppointmentId']) || !isset($data['AppointedTo'])) {
         return false;
      }
      $dataUpdate  = [
         'AppointedTo' => $data['AppointedTo'],
         'EditedAt' => time(),
         'EditedBy' => Auth::user()['StaffId'] ?? 0,
      ];
      return $this->_model->where('AppointmentId', $data['AppointmentId'] ?? 0)->update($dataUpdate);
   }
   
   public function saveAppointmentExtend($data)
   {
      if (!is_array($data)) {
         return false;
      }
      
      $staffId = Auth::user()['StaffId'] ?? 0;
      $dateAppointment = $data['date_appointment'] ?? date('Y-m-d H:i:s');
      $dataInsert = [
         'FullName' => $data['name'] ?? '',
         'Phone' => $data['phone'] ?? '',
         'AtBranchId' => $data['branch_id'] ?? 0,
         'IsAdvise' => 0,
         'Note' => $data['note'] ?? '',
         'StartDate' => date('Y-m-d', strtotime($dateAppointment)),
         'StartHour' => date('H:i:s', strtotime($dateAppointment)),
         'Source' => $data['source'] ?? '',
         'ClientIP' => $data['ip'] ?? '',
         'ExtraData' => $data,
         'CreatedBy' => $staffId,
         'UpdatedBy' => $staffId,
         'CreatedAt' => date('Y-m-d H:i:s'),
         'UpdatedAt' => date('Y-m-d H:i:s')
      ];
      return AppointmentExtend::create($dataInsert);
   }

   public function mappingAppointmentExtend($data)
   {
      if (!is_array($data)) {
         return false;
      }
      
      $staffId = Auth::user()['StaffId'] ?? 0;
      $dataUpdate = [
         'CustomerId' => $data['CustomerId'] ?? 0,
         'UpdatedBy' => $staffId,
         'UpdatedAt' => date('Y-m-d H:i:s')
      ];
      return AppointmentExtend::where('AppointmentExtendId', $data['AppointmentExtendId'] ?? 0)->update($dataUpdate);
   }

   public function getExpiredAppointmentExtends($date)
   {
      if (empty($date) ) {
         return [];
      }
      return AppointmentExtend::where('StartDate', '=', $date)
         ->whereNull('AppointmentId')
         ->whereNull('CustomerId')
         ->get();
   }
   
   public function removeDoctorOfAppointment($data)
   {
      if (!isset($data['AppointmentId'])) {
         return false;
      }
      $dataUpdate  = [
         'AppointedTo' => null,
         'EditedAt' => time(),
         'EditedBy' => Auth::user()['StaffId'] ?? 0,
      ];
      return $this->_model->where('AppointmentId', $data['AppointmentId'] ?? 0)->update($dataUpdate);
   }

   public function countAppointmentHasReceivedByDoctor($doctorId, $exceptionAppointmentId = 0)
   {
      if (!$doctorId || empty($doctorId)) {
         return 0;
      }
      
      $query= $this->_model->where('AppointedTo', $doctorId)
         ->where('AppointmentStatusId', '>=', 41)
         ->where('AppointmentStatusId', '<', 51)
         ->whereBetween('StartAt', [strtotime(date('Y-m-d 00:00:00')), strtotime(date('Y-m-d 23:59:59'))]);
      if ($exceptionAppointmentId && !empty($exceptionAppointmentId)) {
         $query->where('AppointmentId', '!=', $exceptionAppointmentId);
      }
      return $query->count();
   }

   public function saveAppointmentDoctorAssistant($appointmentId, $doctorId = 0, $doctorAssistantIds = [])
   {
      if (!$appointmentId || empty($appointmentId)) {
         return false;
      }
      if (!$doctorId || empty($doctorId)) {
         $doctorId = 0;
      }
      if (!is_array($doctorAssistantIds)) {
         $doctorAssistantIds = [$doctorAssistantIds];
      }
      if (!$doctorAssistantIds || empty($doctorAssistantIds) || count($doctorAssistantIds) < 1) {
         $doctorAssistantIds = [];
      }
      //Check appointment exist
      $appointment = $this->_model->where('AppointmentId', $appointmentId)->first();
      if (!$appointment || empty($appointment)) {
         return false;
      }
      //Check doctor have 3 appointment in day
      if (!$doctorAssistantIds || !is_array($doctorAssistantIds)) {
         $doctorAssistantIds = [$doctorAssistantIds];
      }
      $doctorAssistantIds = array_values(array_unique($doctorAssistantIds));
      DB::beginTransaction();
      try {
         //Set DoctorId for appointment
         $dataUpdate  = [
            'AppointedTo' => $doctorId,
            'EditedAt' => time(),
            'EditedBy' => Auth::user()['StaffId'] ?? 0,
         ];
         $updateAppointment = $this->_model->where('AppointmentId', $appointmentId)->update($dataUpdate);
         //Delete all doctor assistant of appointment
         $markDeleted = AppointmentDoctorAssistant::where('AppointmentId', $appointmentId)->update(['State' => 0, 'CreatedDate' => date('Y-m-d H:i:s')]);
         //Check doctor assistant
         if ($doctorAssistantIds && count($doctorAssistantIds) < 1) {
            DB::commit();
            return true;
         }
         foreach ($doctorAssistantIds as $doctorAssistantId) {
            if (!$doctorAssistantId || empty($doctorAssistantId) || !is_numeric($doctorAssistantId)) {
               continue;
            }
            $data = [
               'AppointmentId' => $appointmentId,
               'DoctorAssistantId' => $doctorAssistantId,
               'State' => 1,
               'CreatedDate' => date('Y-m-d H:i:s'),
            ];
            $saveAppointmentDoctorAssistantt = AppointmentDoctorAssistant::updateOrCreate(
               ['AppointmentId' => $appointmentId, 'DoctorAssistantId' => $doctorAssistantId],
               $data
            );
         }
         DB::commit();
         return true;
      } catch (\Exception $e) {
         DB::rollBack();
         Log::error("Error saveAppointmentDoctorAssistant", [$e->getMessage()]);
         return false;
      }
      DB::rollBack();
      return false;
   }

   public function sendNotiRefreshPageByUser($appointmentId)
   {
      if (!$appointmentId || empty($appointmentId)) {
         return false;
      }
      $appointment = $this->_model->where('AppointmentId', $appointmentId)->first();
      if (!$appointment || empty($appointment)) {
         return false;
      }
      $branch = $appointment->branch ?? null;
      if (!$branch || empty($branch)) {
         return false;
      }
      $doctorInfo = Doctor::join('in.Staff', 'Doctor.StaffId', '=', 'Staff.StaffId')
      ->select('Staff.UserId', 'Doctor.StaffId', 'Doctor.DoctorId')
      ->where('DoctorId', $appointment->AppointedTo ?? 0)
      ->first();
      if (!$doctorInfo || empty($doctorInfo)) {
         return false;
      }
      try {
         // Log::info("==== START Send Noti Refresh Page By User: ".($appointmentId ?? 0)." ===");
         $header = ['Authorization'=>'Bearer ' . JWT_APP_TOKEN];
         $data = [
               "Title" => "Lịch hẹn của bác sĩ",
               "Message" => "Lịch hẹn của bác sĩ chi nhánh ".($branch->BranchCode ?? ''),
               "BranchCode" => $branch->BranchCode ?? '',
               "Type" => "AppointmentForDoctor",
               "RedirectLink" => "/pos/AppointmentForDoctor/MyAppointment",
               "UserId" => $doctorInfo->UserId ?? 0,
               "StaffId" => $doctorInfo->StaffId ?? 0,
         ];
         $remote = Factory::getRemote();
         $remote->request('module')
            ->from(API_SEND_NOTIFICATION_REFRESH_PAGE_BY_USER)
            ->where($data)
            ->execute(true, $header);
         
         $response = $remote->loadVar(false);
         // Log::info("Remote Send Send Noti Refresh Page By User url:", [API_SEND_NOTIFICATION_REFRESH_PAGE_BY_USER]);
         // Log::info("Remote Send Noti Refresh Page By User header:", $header);
         // Log::info("Remote Send Noti Refresh Page By User data:", $data);
         // Log::info("Remote Send Noti Refresh Page By User response", [$response]);
         if ((isset($response->code) && $response->code == false) || !$response) {
            return false;
         }
         return true;
         // Log::info("==== END Send Noti Refresh Page ===");
      } catch (\Exception $e) {
         Log::error("Error sendNotiRefreshPage", [$e->getMessage()]);
         return false;
      }
      return false;
   }
   public function sendNotiRefreshPage($appointmentId)
   {
      if (!$appointmentId || empty($appointmentId)) {
         return false;
      }
      $appointment = $this->_model->where('AppointmentId', $appointmentId)->first();
      if (!$appointment || empty($appointment)) {
         return false;
      }
      $branch = $appointment->branch ?? null;
      if (!$branch || empty($branch)) {
         return false;
      }
      try {
         // Log::info("==== START Send Noti Refresh Page: ".($appointmentId ?? 0)." ===");
         $header = ['Authorization'=>'Bearer ' . JWT_APP_TOKEN];
         $data = [
            "Title" => "Hoạt động bác sĩ",
            "Message" => "Hoạt động bác sĩ chi nhánh ".($branch->BranchCode ?? ''),
            "BranchCode" => $branch->BranchCode ?? '',
            "Type" => "BranchExecutive",
            "RedirectLink" => "/pos/BranchExecutive/RoomV2",
        ];
         $remote = Factory::getRemote();
         $remote->request('module')
            ->from(API_SEND_NOTIFICATION_REFRESH_PAGE)
            ->where($data)
            ->execute(true, $header);
         
         $response = $remote->loadVar(false);
         // Log::info("Remote Send Send Noti Refresh Page url:", [API_SEND_NOTIFICATION_REFRESH_PAGE]);
         // Log::info("Remote Send Noti Refresh Page header:", $header);
         // Log::info("Remote Send Noti Refresh Page data:", $data);
         // Log::info("Remote Send Noti Refresh Page response", [$response]);
         if ((isset($response->code) && $response->code == false) || !$response) {
            return false;
         }
         return true;
         // Log::info("==== END Send Noti Refresh Page ===");
      } catch (\Exception $e) {
         Log::error("Error sendNotiRefreshPage", [$e->getMessage()]);
         return false;
      }
      return false;
   }

   public function getAppointmentAndRating($appointmentId)
   {
      if (!$appointmentId || empty($appointmentId)) {
         return false;
      }
      $query = $this->_model->newQuery();
      $query->where('AppointmentId', $appointmentId);

      $query->with(['customer' => function ($query) {
         $query->select('CustomerId', 'CustomerCode', 'FullName', 'Birthday', 'Gender');
      }]);

      $query->with(['branch' => function ($query) {
         $query->select('BranchId', 'BranchCode', 'Name', 'Address', 'GoogleRatingURL');
      }]);

      $query->with(['rating']);

      $appointment = $query->first();

      return $appointment;
   }

   public function listDoctorAndAssistantByCustomer($request)
   {
      $customerId = $request['CustomerId'] ?? 0;
      $appointmentId = $request['AppointmentId'] ?? 0;

      $query = $this->_model->newQuery();

      $query->join('pos.Customer as c', 'c.CustomerId', 'Appointment.CustomerId');

      $query->with([
         'doctor' => function ($queryDoctor) {
            $queryDoctor->with(['staff']);
         },
         'appointmentDoctorAssistant' =>function ($queryADA) {
            $queryADA->with(['doctorAssistant' => function ($queryDA) {
               $queryDA->with('staff');
            }]);
         }]);

      $query->where('Appointment.AppointmentStatusId', '>', 0);
      $query->where('Appointment.AppointmentStatusId', '<=', 41);
      $query->where('Appointment.AppointmentId', $appointmentId);
      $query->where('c.CustomerId', $customerId);

      return $query->get();
   }

   public function getAppointmentStatusHistory($request)
   {
      $appointmentId = $request['AppointmentId'] ?? 0;
      if (!$appointmentId || empty($appointmentId)) {
         return [];
      }
      $query = $this->_model->newQuery()
      ->select('Appointment.AppointmentId', 'Appointment.CreatedAt', 'Appointment.CreatedBy', 'Appointment.AppointmentStatusId', 'Appointment.CheckInTime', 'Appointment.TransferredToDoctorTime', 'Appointment.CustomerId', 'Appointment.LatestUpdated', 'Appointment.EditedAt', 'Appointment.EditedBy', 'Customer.FullName as CustomerName', 'Customer.CustomerCode', 'Staff.FullName as StaffName', 'Staff.StaffCode', 's.FullName as StaffNameEdit', 's.StaffCode as StaffCodeEdit')
      ->join('pos.Customer', 'Customer.CustomerId', '=', 'Appointment.CustomerId')
      ->join('in.Staff', 'Staff.StaffId', '=', 'Appointment.CreatedBy')
      ->join('in.Staff as s', 's.StaffId', '=', 'Appointment.EditedBy')
      ->where('AppointmentId', $appointmentId);

      $infoAppointment = $query->first();

      $sub = DB::table('pos.AppointmentHistory')
         ->select(
            'AppointmentStatusId',
            DB::raw('MIN(EditedAt) as MinEditedAt')
         )
         ->where('AppointmentId', $appointmentId)
         ->groupBy('AppointmentStatusId');

      $histories = DB::table('pos.AppointmentHistory as ah')
         ->joinSub($sub, 'm', function($join) {
            $join->on('ah.AppointmentStatusId', '=', 'm.AppointmentStatusId')
                  ->on('ah.EditedAt', '=', 'm.MinEditedAt');
         })
         ->leftJoin('in.Staff as s', 's.StaffId', '=', 'ah.EditedBy')
         ->select(
            'ah.AppointmentStatusId',
            'ah.AppointmentId',
            'ah.EditedBy',
            'ah.EditedAt',
            's.FullName as StaffName',
            's.StaffCode',
            'ah.PushedAt'
         )
         ->where('ah.AppointmentId', $appointmentId)
         ->get();

      return [
         'Appointment' => $infoAppointment,
         'AppointmentHistory' => $histories,
      ];
   }
}
