<?php

namespace App\Repositories;

use App\Customer;
use App\Repositories\Abstracts\EloquentRepository;
use App\LaboOrder;
use App\ImplantOrder;
use App\ImplantOrderTracking;
use App\OrderComment;
use App\Staff;
use App\OrderCommentAttachment;
use App\LaboOrderRating;
use App\LaboOrderStyle;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use App\Libs\Helper;
use App\Libs\Factory;

class LaboOrderRepository extends EloquentRepository
{
    protected function getModel()
    {
        return LaboOrder::class;
    }

    public function createCommentOrder($dataRequest)
    {
        try {
            $laboOrderId = $dataRequest['Id'] ?? 0;
            $parentOrderCommentId = $dataRequest['OrderCommentId'] ?? 0;
            $orderType = $dataRequest['OrderType'] ?? '';
            $content = $dataRequest['Content'] ?? '';
            $staffId = Auth::user()['StaffId'] ?? 0;
            $files = $dataRequest['Files'] ?? [];
            $laboOrder = '';
            $createdBy = 0;
            $laboConfirmStaff = 0;
            $customerName = NULL;
            $staffIdNotification = [];
            if($orderType == 'LABO'){
                $laboOrder = LaboOrder::find($laboOrderId);
                if (!$laboOrder) {
                    return false;
                }
                $createdBy = $laboOrder->CreatedBy;
                $laboConfirmStaff = $laboOrder->LaboConfirmStaff;
                $staffIdNotification[] = $createdBy;
                $staffIdNotification[] = $laboConfirmStaff;
                $infoCustomer = Customer::where('CustomerId', $laboOrder->CustomerId)->first();
                if($infoCustomer){
                    $customerName = $infoCustomer->FullName;
                }
            } elseif($orderType == 'IMPLANT') {
                $laboOrder = ImplantOrder::find($laboOrderId);
                $implantOrderTracking = ImplantOrderTracking::where('ImplantOrderId', $laboOrderId)->where('Type', 20)->first();
                if (!$laboOrder) {
                    return false;
                }
                $createdBy = $laboOrder->CreatedBy;
                $staffIdNotification[] = $createdBy;
                if($implantOrderTracking) {
                    $staffIdNotification[] = $implantOrderTracking->CreatedBy;
                }
                $infoCustomer = Customer::where('CustomerId', $laboOrder->CustomerId)->first();
                if($infoCustomer){
                    $customerName = $infoCustomer->FullName;
                }
            } else {
                return false;
            }
            $linkCDNs = [];
            // Upload file đính kèm
            if(!empty($files)) {
                foreach ($files as $file) {
                    if (!$file || empty($file)) {
                        continue;
                    }
                    if (is_string($file)) {
                        $linkCDNs[] = $file;
                        continue;
                    }
                    $urlFile = Helper::uploadFileToServer($file, 'OrderComment/'.($laboOrderId ?? 0));

                    if (!$urlFile || empty($urlFile)) {
                        continue;
                    }
                    $linkCDNs[] = API_MEDIA .'/'. $urlFile;
                }
            }
            $data = [
                'ObjectType' => $orderType,
                'ObjectId' => $laboOrderId,
                'ParentOrderCommentId' => $parentOrderCommentId > 0 ? $parentOrderCommentId : NULL,
                'CreatedBy' => $staffId,
                'Content' => $content,
                'CreatedDate' => Carbon::now(),
                'CreatedBy' => $staffId
            ];
            // Tạo mới comment hoặc reply comment
            DB::beginTransaction();
            $orderCommentId = OrderComment::insertGetId($data);
            if ($orderCommentId && count($linkCDNs) > 0) {
                foreach ($linkCDNs as $linkCDN) {
                    $dataAttachment = [
                        'OrderCommentId' => $orderCommentId,
                        'FileUrl' => $linkCDN,
                        'UploadedAt' => Carbon::now(),
                    ];
                    // Tạo mới file đính kèm
                    OrderCommentAttachment::insert($dataAttachment);
                }
            }
            
            $title = '';
            $redirectLink = '';
            $contentNoti = '';
            // Cập nhật thời gian chỉnh sửa
            $staffName = Staff::select('FullName')->where('StaffId', $staffId)->first();
            if($orderType == 'LABO'){
                $dataUpdate  = [
                    'EditedAt' => time(),
                    'EditedBy' => $staffId,
                    'LatestComment' => $content,
                    'LatestCommentBy' => $staffId,
                    'LatestCommentDate' => Carbon::now(),
                ];
                LaboOrder::where('Id', $laboOrderId)->update($dataUpdate);
                $title = $staffName->FullName .' đã bình luận đơn hàng Labo của KH '.$customerName;
                $redirectLink = "/pos/LaboOrder/Detail/".$laboOrderId;
                $contentNoti = "Đơn hàng Labo của KH ".$customerName." được bình luận bởi " . $staffName->FullName . " lúc " . Carbon::now()->format('H:i:s d/m/Y');
            }elseif($orderType == 'IMPLANT'){
                $dataUpdate  = [
                    'UpdatedDate' => Carbon::now(),
                    'UpdatedBy' => $staffId,
                    'LatestComment' => $content,
                    'LatestCommentBy' => $staffId,
                    'LatestCommentDate' => Carbon::now(),
                ];
                ImplantOrder::where('ImplantOrderId', $laboOrderId)->update($dataUpdate);
                $title = $staffName->FullName .' đã bình luận đơn hàng Implant của KH '.$customerName;
                $redirectLink = "/pos/ImplantOrderManagement/Detail/".$laboOrderId;
                $contentNoti = "Đơn hàng Implant của KH ".$customerName." được bình luận bởi " . $staffName->FullName . " lúc " . Carbon::now()->format('H:i:s d/m/Y');
            }
            DB::commit();
            // Gửi thông báo tới bác sĩ hoặc người comment
            $userIdNotification = [];
            if($parentOrderCommentId > 0) { // Trả lời cmt
                $infoOrder = OrderComment::where('OrderCommentId', $parentOrderCommentId)->orWhere('ParentOrderCommentId', $parentOrderCommentId)->get()->toArray();
                if ($infoOrder) {
                    foreach ($infoOrder as $key => $value) {
                        if(!in_array($value['CreatedBy'], $staffIdNotification)){
                            $staffIdNotification[] = $value['CreatedBy'];
                        }
                    }
                }
            }

            $staffIdNotification = array_values(array_diff($staffIdNotification, [$staffId]));
            $staffIdNotification = array_unique($staffIdNotification);
            $userIdNotification = self::mapStaffIdToUserId($staffIdNotification);


            if (count($userIdNotification) > 0){
                foreach ($userIdNotification as $key => $userId) {
                    self::sendNotificationOrderImplant($title, $contentNoti, $userId, $redirectLink);
                }
            }
            return true;

        } catch(\Exception $e) {

            DB::rollback();
            Log::error("createCommentOrder error", [$e->getMessage()]);
            return false;

        }
    }

    public function mapStaffIdToUserId($staffIds)
    {
        try {
            $staffInfo = Staff::select('UserId')->whereIn('StaffId', $staffIds)->get()->toArray();
            if (count($staffInfo) > 0) {
                $userIds = [];
                foreach ($staffInfo as $key => $value) {
                    $userIds[] = $value['UserId'];
                }
                return $userIds;
            }
            return [];
        } catch(\Exception $e) {
            Log::error("mapStaffIdToUserId error", [$e->getMessage()]);
            return [];
        }
    }

    public function sendNotificationOrderImplant($title,$content,$userIdNotification,$redirectLink)
    {
        try {
            Log::info("==== START Send Notification Order Comment ===");
            $header = ['Authorization'=>'Bearer ' . JWT_APP_TOKEN];
            $remote = Factory::getRemote();

            $data = [
                "notification[title]" => $title,
                "notification[message]" => $content,
                "notification[exprire_date]" => Carbon::now(),
                "notification[message_type]" => 'normal',
                "notification[link]" => '',
                "notification[important]" => 0,
                "notification[hasStaff]" => true,
                "notification[user_list]" => $userIdNotification,
                "notification[sender]" => Auth::user()['UserId'],
                "notification[type]" => 'OrderComment',
                "notification[redirect_link]" => $redirectLink
            ];
            $remote = Factory::getRemote();
            $remote->request('module')->from(API_SEND_NOTIFICATION)->where($data)->execute(true, $header);
            $response = $remote->loadVar(true);

            Log::info("Remote Send Notification Order Comment data:", [$remote->getResponseMessages()]);
            Log::info("Remote Send Notification Order Comment response", [$response]);
            Log::info("==== END Send Notification Order Comment ===");

            if ((isset($response->code) && $response->code == false) || !$response) {
                Log::error("Remote Send Noti Create Comment Order By User response", [$response]);
            }
            return true;

        } catch(\Exception $e) {
            Log::error("sendNotificationOrderImplant error", [$e->getMessage()]);
            return false;
        }
    }

    public function getListCommentOrder($dataRequest)
    {
        try {
            $laboOrderId = $dataRequest['Id'] ?? 0;
            $laboOrder = LaboOrder::find($laboOrderId);
            if ($laboOrder) {
                $query = DB::table('pos.OrderComment as oc')
                    ->select('oc.*', 's.FullName as StaffName', 's.StaffCode')
                    ->join('in.Staff as s', 'oc.CreatedBy', '=', 's.StaffId')
                    ->where('oc.ObjectId', $laboOrderId)
                    ->orderBy('oc.OrderCommentId', 'desc');
                return $query->get();
            }
            return [];

        } catch(\Exception $e) {
            Log::error("getListCommentOrder error", [$e->getMessage()]);
            return [];
        }
    }

    public function getListCommentOrderAttachment($orderCommentId)
    {
        try {
            $query = OrderCommentAttachment::where('OrderCommentId', $orderCommentId)->orderBy('UploadedAt', 'desc');
            return $query->get();
        } catch(\Exception $e) {
            Log::error("getListCommentOrderAttachment error", [$e->getMessage()]);
            return [];
        }
    }

    public function countLaboOrderRating($dataRequest)
    {
        try {
            $fromDate = $dataRequest['FromDate'] ?? '';
            $toDate = $dataRequest['ToDate'] ?? '';
            $query = LaboOrderRating::whereNull('ReadResponsibleStaffId')
                ->where('CreatedDate', '>=', strtotime($fromDate.' 00:00:01'))
                ->where('CreatedDate', '<=', strtotime($toDate.' 23:59:59'));
            return $query->count() ?? 0;

        } catch(\Exception $e) {
            Log::error("countLaboOrderRating error", [$e->getMessage()]);
            return 0;
        }
    }

    public function getLaboOrderStyle()
    {
        try {
            $query = LaboOrderStyle::select('LaboOrderStyleId', 'LaboOrderStyleName', 'State', 'Priority')->where('State', 1)->orderBy('Priority')->get();
            return $query->toArray();
        } catch(\Exception $e) {
            Log::error("getLaboOrderStyle error", [$e->getMessage()]);
            return [];
        }
    }
}