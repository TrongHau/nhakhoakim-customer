<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Repositories\AppointmentRepository;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class AppointmentController extends Controller
{
    /**
     * @var AppointmentRepository
     */
    protected $appointmentRepo;

    /**
     * @param AppointmentRepository $appointmentRepo 
     * @return void 
     */
    public function __construct(AppointmentRepository $appointmentRepo) {
        parent::__construct();
        $this->appointmentRepo = $appointmentRepo;
    }

    public function changeDoctorOfAppointment(Request $request) {
        $validator = Validator::make($request->all(), [
            'AppointmentId'    => 'required|numeric',
            'AppointedTo'        => 'required|numeric',
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'ERR001', 3);
            return $this->json(false, 'bool');
        }

        $accepted = $this->appointmentRepo->checkConditionDoctorOfAppointment($request->all());
        if (!$accepted) {
            $this->addMessage('Không thể thay đổi bác sĩ cho lịch hẹn này', 'ACB0002', self::$ERROR);
            return $this->json(false, 'bool');
        }
        $appointment = $this->appointmentRepo->find($request->get('AppointmentId'));
        if (!$appointment) {
            $this->addMessage('Lịch hẹn không tồn tại', 'ACB0002', self::$ERROR);
            return $this->json(false, 'bool');
        }
        if (
            $this->appointmentRepo->countAppointmentHasReceivedByDoctor($request->get('AppointedTo', 0), $request->get('AppointmentId')) >= 3
            && $appointment->AppointmentStatusId >= 41
            && $appointment->AppointmentStatusId < 5
        ) {
            $this->addMessage('Bác sĩ không thể tiếp nhận nhiều hơn 3 khách hàng cùng lúc', 'ACB0002', self::$ERROR);
            return $this->json(false, 'bool');
        }

        $res = $this->appointmentRepo->changeDoctorOfAppointment($request->all());
        if ($res) {
            $this->appointmentRepo->sendNotiRefreshPage($request->get('AppointmentId'));
            $this->appointmentRepo->sendNotiRefreshPageByUser($request->get('AppointmentId'));
            $this->addMessage("Thay đổi bác sĩ thành công", 'ACB0001', self::$SUCCESS);
            return $this->json(true, 'bool');
        }
        $this->addMessage('Thay đổi bác sĩ thất bại', 'ACB0003', self::$ERROR);
        return $this->json(false, 'bool');
    }

    public function addDoctorOfAppointment(Request $request) {
        $validator = Validator::make($request->all(), [
            'AppointmentId'    => 'required|numeric',
            'AppointedTo'        => 'required|numeric',
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'ERR001', 3);
            return $this->json(false, 'bool');
        }

        $accepted = $this->appointmentRepo->checkConditionDoctorOfAppointment($request->all());
        if (!$accepted) {
            $this->addMessage('Không thể thêm bác sĩ cho lịch hẹn này', 'ACB0002', self::$ERROR);
            return $this->json(false, 'bool');
        }
        $appointment = $this->appointmentRepo->find($request->get('AppointmentId'));
        if (!$appointment) {
            $this->addMessage('Lịch hẹn không tồn tại', 'ACB0002', self::$ERROR);
            return $this->json(false, 'bool');
        }
        if (isset($appointment->AppointedTo) && !empty($appointment->AppointedTo)) {
            $this->addMessage('Lịch hẹn đã có bác sĩ', 'ACB0002', self::$ERROR);
            return $this->json(false, 'bool');
        }
        if (
            $this->appointmentRepo->countAppointmentHasReceivedByDoctor($request->get('AppointedTo', 0), $request->get('AppointmentId')) >= 3
            && $appointment->AppointmentStatusId >= 41
            && $appointment->AppointmentStatusId < 51
        ) {
            $this->addMessage('Bác sĩ không thể tiếp nhận nhiều hơn 3 khách hàng cùng lúc', 'ACB0002', self::$ERROR);
            return $this->json(false, 'bool');
        }

        $res = $this->appointmentRepo->changeDoctorOfAppointment($request->all());
        if ($res) {
            $this->appointmentRepo->sendNotiRefreshPage($request->get('AppointmentId'));
            $this->appointmentRepo->sendNotiRefreshPageByUser($request->get('AppointmentId'));
            $this->addMessage("Thêm bác sĩ cho lịch hẹn thành công", 'ACB0001', self::$SUCCESS);
            return $this->json(true, 'bool');
        }
        $this->addMessage('Thêm bác sĩ cho lịch hẹn thất bại', 'ACB0003', self::$ERROR);
        return $this->json(false, 'bool');
    }


    public function removeDoctorOfAppointment(Request $request) {
        $validator = Validator::make($request->all(), [
            'AppointmentId'    => 'required|numeric'
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'ERR001', 3);
            return $this->json(false, 'bool');
        }

        $accepted = $this->appointmentRepo->checkConditionDoctorOfAppointment($request->all());
        if (!$accepted) {
            $this->addMessage('Không thể xoá bác sĩ cho lịch hẹn này', 'ACB0002', self::$ERROR);
            return $this->json(false, 'bool');
        }
        $appointment = $this->appointmentRepo->find($request->get('AppointmentId'));
        if (!$appointment) {
            $this->addMessage('Lịch hẹn không tồn tại', 'ACB0002', self::$ERROR);
            return $this->json(false, 'bool');
        }
        if (isset($appointment->AppointedTo) && empty($appointment->AppointedTo)) {
            $this->addMessage('Lịch hẹn đã không có bác sĩ', 'ACB0002', self::$ERROR);
            return $this->json(false, 'bool');
        }
        $request->merge(['AppointedTo' => 0]);

        $res = $this->appointmentRepo->removeDoctorOfAppointment($request->all());
        if ($res) {
            $this->appointmentRepo->sendNotiRefreshPage($request->get('AppointmentId'));
            $this->appointmentRepo->sendNotiRefreshPageByUser($request->get('AppointmentId'));
            $this->addMessage("Xoá bác sĩ cho lịch hẹn thành công", 'ACB0001', self::$SUCCESS);
            return $this->json(true, 'bool');
        }
        $this->addMessage('Xoá bác sĩ cho lịch hẹn thất bại', 'ACB0003', self::$ERROR);
        return $this->json(false, 'bool');
    }

    public function saveDoctorAssistantOfAppointment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'AppointmentId'    => 'required|numeric',
            'DoctorAssistantIds'    => 'nullable|array',
            'DoctorId' => 'required|numeric'
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'ERR001', 3);
            return $this->json(false, 'bool');
        }
        $accepted = $this->appointmentRepo->checkConditionDoctorOfAppointment($request->all());
        if (!$accepted) {
            $this->addMessage('Không thể thay đổi bác sĩ/phụ tá cho lịch hẹn này', 'ACB0002', self::$ERROR);
            return $this->json(false, 'bool');
        }
        $appointment = $this->appointmentRepo->find($request->get('AppointmentId'));
        if (!$appointment) {
            $this->addMessage('Lịch hẹn không tồn tại. Vui lòng kiểm tra lại', 'ACB0002', self::$ERROR);
            return $this->json(false, 'bool');
        }
        if (
            $this->appointmentRepo->countAppointmentHasReceivedByDoctor($request->get('DoctorId', 0), $request->get('AppointmentId')) >= 3
            && $appointment->AppointmentStatusId >= 41
            && $appointment->AppointmentStatusId < 51
        ) {
            $this->addMessage('Bác sĩ không thể tiếp nhận nhiều hơn 3 khách hàng cùng lúc', 'ACB0002', self::$ERROR);
            return $this->json(false, 'bool');
        }
        $res = $this->appointmentRepo->saveAppointmentDoctorAssistant($request->get('AppointmentId'), $request->get('DoctorId'), $request->get('DoctorAssistantIds'));
        if ($res) {
            $this->appointmentRepo->sendNotiRefreshPage($request->get('AppointmentId'));
            $this->addMessage("Thay đổi bác sĩ/phụ tá thành công", 'ACB0001', self::$SUCCESS);
            return $this->json(true, 'bool');
        }
        $this->addMessage('Thay đổi bác sĩ/phụ tá thất bại', 'ACB0003', self::$ERROR);
        return $this->json(false, 'bool');
    }

    public function saveAppointmentExtend(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required',
            'name' => 'required',
            'date_appointment' => 'required|date',
            'branch_id' => 'required|numeric',
            'note' => 'nullable'
            ]);
        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'ERR001', 3);
            return $this->json(false, 'bool');
        }
        $res = $this->appointmentRepo->saveAppointmentExtend($request->all());
        if ($res) {
            $this->addMessage("Tạo lịch hẹn thành công!", 'SAT0001', self::$SUCCESS);
            return $this->json(true, 'bool');
        }
        $this->addMessage("Tạo lịch hẹn không thành công!", 'SAT0003', self::$ERROR);
        return $this->json(false, 'bool');
    }

    public function mappingAppointmentExtend(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'CustomerId' => 'required|numeric',
            'AppointmentExtendId' => 'required|numeric',
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'ERR001', 3);
            return $this->json(false, 'bool');
        }
        $res = $this->appointmentRepo->mappingAppointmentExtend($request->all());
        if ($res) {
            $this->addMessage("Cập nhật thành công!", 'SAT0001', self::$SUCCESS);
            return $this->json(true, 'bool');
        }
        $this->addMessage("Cập nhật không thành công!", 'SAT0003', self::$ERROR);
        return $this->json(false, 'bool');
    }

    public function getAppointmentAndRating(Request $request) {
        $validator = Validator::make($request->all(), [
            'AppointmentId'    => 'required|numeric'
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'ERR001', 3);
            return $this->json(false, 'bool');
        }
        $data = $this->appointmentRepo->getAppointmentAndRating($request->get('AppointmentId'));
        $results[] = $this->formatData('AppointmentAndRating', $data);
        return $this->json($results, 'views');
    }

    public function listDoctorAndAssistantByCustomer(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'AppointmentId'    => 'required|numeric',
            'CustomerId' => 'required|numeric'
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'ERR001', 3);
            return $this->json(false, 'bool');
        }
        $data = $this->appointmentRepo->listDoctorAndAssistantByCustomer($request->all());
        $results[] = $this->formatData('DoctorAndAssistantByCustomer', $data);
        return $this->json($results, 'views');
    }

    public function getAppointmentStatusHistory(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'AppointmentId' => 'required|numeric',
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'ERR001', self::$ERROR);
            return $this->json(false, 'bool');
        }

        try {

            $infoAppointment = $this->appointmentRepo->getAppointmentStatusHistory($request->all());
            $appointment = $infoAppointment['Appointment'];
            $appointmentHistory = $infoAppointment['AppointmentHistory'];

            $data = [];
            if (!empty($appointmentHistory)) {
                foreach ($appointmentHistory as $history) {
                    $appointmentStatusId = $history->AppointmentStatusId;

                    switch ($appointmentStatusId) {
                        case 11:
                            if($appointment->AppointmentStatusId == 1 || $appointment->AppointmentStatusId >= $appointmentStatusId) { // Tạo lịch hẹn
                                $data[] = [
                                    'CustomerId' => $appointment->CustomerId,
                                    'CustomerName' => $appointment->CustomerName,
                                    'CustomerCode' => $appointment->CustomerCode,
                                    'AppointmentStatusId' => 11,
                                    'AppointmentStatusLabel' => 'Khách hàng chưa đến',
                                    'StaffName' => $history->StaffName,
                                    'StaffCode' => $history->StaffCode,
                                    'StaffId' => $history->EditedBy,
                                    'CreatedDate' => date('Y-m-d H:i:s', $history->EditedAt)
                                ];
                            }
                            break;
                        case 21: // Checkin
                            if($appointment->AppointmentStatusId >= $appointmentStatusId) {
                                $data[] = [
                                    'CustomerId' => $appointment->CustomerId,
                                    'CustomerName' => $appointment->CustomerName,
                                    'CustomerCode' => $appointment->CustomerCode,
                                    'AppointmentStatusId' => 21,
                                    'AppointmentStatusLabel' => 'Đã checkin',
                                    'StaffName' => $history->StaffName,
                                    'StaffCode' => $history->StaffCode,
                                    'StaffId' => $history->EditedBy,
                                    'CreatedDate' => date('Y-m-d H:i:s', $history->EditedAt)
                                ];
                            }
                            break;
                        case 31: // Đã chuyển đến bác sĩ
                            if($appointment->AppointmentStatusId >= $appointmentStatusId) {
                                $data[] = [
                                    'CustomerId' => $appointment->CustomerId,
                                    'CustomerName' => $appointment->CustomerName,
                                    'CustomerCode' => $appointment->CustomerCode,
                                    'AppointmentStatusId' => 31,
                                    'AppointmentStatusLabel' => 'Đã chuyển đến bác sĩ',
                                    'StaffName' => $history->StaffName,
                                    'StaffCode' => $history->StaffCode,
                                    'StaffId' => $history->EditedBy,
                                    'CreatedDate' => date('Y-m-d H:i:s', $history->EditedAt)
                                ];
                            }
                            break;
                        case 41: // Đã tiếp nhận
                            if($appointment->AppointmentStatusId >= $appointmentStatusId) {
                                $data[] = [
                                    'CustomerId' => $appointment->CustomerId,
                                    'CustomerName' => $appointment->CustomerName,
                                    'CustomerCode' => $appointment->CustomerCode,
                                    'AppointmentStatusId' => 41,
                                    'AppointmentStatusLabel' => 'Đã tiếp nhận',
                                    'StaffName' => $history->StaffName,
                                    'StaffCode' => $history->StaffCode,
                                    'StaffId' => $history->EditedBy,
                                    'CreatedDate' => date('Y-m-d H:i:s', $history->EditedAt)
                                ];
                            }
                            break;
                        case 51: // Đã chuyển đến thu ngân
                            if($appointment->AppointmentStatusId >= $appointmentStatusId) {
                                $data[] = [
                                    'CustomerId' => $appointment->CustomerId,
                                    'CustomerName' => $appointment->CustomerName,
                                    'CustomerCode' => $appointment->CustomerCode,
                                    'AppointmentStatusId' => 51,
                                    'AppointmentStatusLabel' => 'Đã chuyển đến thu ngân',
                                    'StaffName' => $history->StaffName,
                                    'StaffCode' => $history->StaffCode,
                                    'StaffId' => $history->EditedBy,
                                    'CreatedDate' => date('Y-m-d H:i:s', $history->EditedAt)
                                ];
                            }
                            break;
                        case 61: // Đã thanh toán
                            if($appointment->AppointmentStatusId >= $appointmentStatusId) {
                                $data[] = [
                                    'CustomerId' => $appointment->CustomerId,
                                    'CustomerName' => $appointment->CustomerName,
                                    'CustomerCode' => $appointment->CustomerCode,
                                    'AppointmentStatusId' => 61,
                                    'AppointmentStatusLabel' => 'Đã thanh toán',
                                    'StaffName' => $history->StaffName,
                                    'StaffCode' => $history->StaffCode,
                                    'StaffId' => $history->EditedBy,
                                    'CreatedDate' => date('Y-m-d H:i:s', $history->EditedAt)
                                ];
                            }
                            break;
                        default:
                    }
                }
            }
            $appointmentStatusIds = array_column($data, 'AppointmentStatusId');
            
            switch ($appointment->AppointmentStatusId) {
                case 1: // Huỷ hẹn
                    $data[] = [
                        'CustomerId' => $appointment->CustomerId,
                        'CustomerName' => $appointment->CustomerName,
                        'CustomerCode' => $appointment->CustomerCode,
                        'AppointmentStatusId' => 1,
                        'AppointmentStatusLabel' => 'Đã huỷ hẹn',
                        'StaffName' => $appointment->StaffNameEdit,
                        'StaffCode' => $appointment->StaffCodeEdit,
                        'StaffId' => $appointment->EditedBy,
                        'CreatedDate' => date('Y-m-d H:i:s', $appointment->EditedAt)
                    ];
                    break;
                case 11: // Tạo lịch hẹn
                    if(!in_array(11, $appointmentStatusIds)) {
                        $data[] = [
                            'CustomerId' => $appointment->CustomerId,
                            'CustomerName' => $appointment->CustomerName,
                            'CustomerCode' => $appointment->CustomerCode,
                            'AppointmentStatusId' => 11,
                            'AppointmentStatusLabel' => 'Khách hàng chưa đến',
                            'StaffName' => $appointment->StaffName,
                            'StaffCode' => $appointment->StaffCode,
                            'StaffId' => $appointment->CreatedBy,
                            'CreatedDate' => date('Y-m-d H:i:s', $appointment->CreatedAt)
                        ];
                    }
                    break;
                case 21: // Checkin
                    if(!in_array(21, $appointmentStatusIds)) {
                        $data[] = [
                            'CustomerId' => $appointment->CustomerId,
                            'CustomerName' => $appointment->CustomerName,
                            'CustomerCode' => $appointment->CustomerCode,
                            'AppointmentStatusId' => 21,
                            'AppointmentStatusLabel' => 'Đã checkin',
                            'StaffName' => $appointment->StaffNameEdit,
                            'StaffCode' => $appointment->StaffCodeEdit,
                            'StaffId' => $appointment->EditedBy,
                            'CreatedDate' => date('Y-m-d H:i:s', $appointment->EditedAt)
                        ];
                    }
                    break;
                case 31: // Chuyển đến bác sĩ
                    if(!in_array(31, $appointmentStatusIds)) {
                        $data[] = [
                            'CustomerId' => $appointment->CustomerId,
                            'CustomerName' => $appointment->CustomerName,
                            'CustomerCode' => $appointment->CustomerCode,
                            'AppointmentStatusId' => 31,
                            'AppointmentStatusLabel' => 'Đã chuyển đến bác sĩ',
                            'StaffName' => $appointment->StaffNameEdit,
                            'StaffCode' => $appointment->StaffCodeEdit,
                            'StaffId' => $appointment->EditedBy,
                            'CreatedDate' => date('Y-m-d H:i:s', $appointment->EditedAt)
                        ];
                    }
                    break;
                case 41: // Đã tiếp nhận
                    if(!in_array(41, $appointmentStatusIds)) {
                        $data[] = [
                            'CustomerId' => $appointment->CustomerId,
                            'CustomerName' => $appointment->CustomerName,
                            'CustomerCode' => $appointment->CustomerCode,
                            'AppointmentStatusId' => 41,
                            'AppointmentStatusLabel' => 'Đã tiếp nhận',
                            'StaffName' => $appointment->StaffNameEdit,
                            'StaffCode' => $appointment->StaffCodeEdit,
                            'StaffId' => $appointment->EditedBy,
                            'CreatedDate' => date('Y-m-d H:i:s', $appointment->EditedAt)
                        ];
                    }
                    break;
                case 51: // Đã chuyển đến thu ngân
                    if(!in_array(51, $appointmentStatusIds)) {
                        $data[] = [
                            'CustomerId' => $appointment->CustomerId,
                            'CustomerName' => $appointment->CustomerNameEdit,
                            'CustomerCode' => $appointment->CustomerCodeEdit,
                            'AppointmentStatusId' => 51,
                            'AppointmentStatusLabel' => 'Đã chuyển đến thu ngân',
                            'StaffName' => $appointment->StaffNameEdit,
                            'StaffCode' => $appointment->StaffCodeEdit,
                            'StaffId' => $appointment->EditedBy,
                            'CreatedDate' => date('Y-m-d H:i:s', $appointment->EditedAt)
                        ];
                    }
                    break;
                case 61: // Thanh toán
                    if(!in_array(61, $appointmentStatusIds)) {
                        $data[] = [
                            'CustomerId' => $appointment->CustomerId,
                            'CustomerName' => $appointment->CustomerNameEdit,
                            'CustomerCode' => $appointment->CustomerCodeEdit,
                            'AppointmentStatusId' => 61,
                            'AppointmentStatusLabel' => 'Đã thanh toán',
                            'StaffName' => $appointment->StaffNameEdit,
                            'StaffCode' => $appointment->StaffCodeEdit,
                            'StaffId' => $appointment->EditedBy,
                            'CreatedDate' => date('Y-m-d H:i:s', $appointment->EditedAt)
                        ];
                    }
                    break;
                case 71: // Checkout
                    $data[] = [
                        'CustomerId' => $appointment->CustomerId,
                        'CustomerName' => $appointment->CustomerName,
                        'CustomerCode' => $appointment->CustomerCode,
                        'AppointmentStatusId' => 71,
                        'AppointmentStatusLabel' => 'Đã checkout',
                        'StaffName' => $appointment->StaffNameEdit,
                        'StaffCode' => $appointment->StaffCodeEdit,
                        'StaffId' => $appointment->EditedBy,
                        'CreatedDate' => date('Y-m-d H:i:s', $appointment->EditedAt)
                    ];
                    break;
                default:
            }
            usort($data, function($a, $b) {
                return strtotime($b['CreatedDate']) - strtotime($a['CreatedDate']);
            });
            $results[] = $this->formatData('AppointmentStatusHistory', $data);
            return $this->json($results, 'views');
        } catch (\Exception $e) {
            Log::error("Fetching appointment status history errors", [$e->getMessage()]);
            $results[] = $this->formatData('AppointmentStatusHistory', []);
            return $this->json($results, 'views');
        }
    }
}
