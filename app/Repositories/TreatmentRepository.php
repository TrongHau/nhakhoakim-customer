<?php

namespace App\Repositories;

use App\Libs\Factory;
use App\Libs\Helper;
use App\OrderDetail;
use App\Promotion;
use App\PromotionService;
use App\PromotionVoucher;
use App\ReceiptServiceMapping;
use App\Treatment;
use App\Repositories\Abstracts\EloquentRepository;
use App\TreatmentHistory;
use App\TreatmentMedicalProcedureOffer;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TreatmentRepository extends EloquentRepository
{
   /**
    * @return string
    */
   protected function getModel(): string
   {
      //Required model
      return Treatment::class;
   }

   public function getTreatmentActive($customerId)
   {
      if (!$customerId || empty($customerId)) {
         return [];
      }
      //Get Treatment Active
      return $this->_model->where('PersonId', $customerId)->whereNull('ClosedAt')->first();
   }

   public function getPrescriptionMedicines($customerId)
   {
      if (!$customerId || empty($customerId)) {
         return [];
      }
      $treatment = $this->getTreatmentActive($customerId);
      if (!$treatment || empty($treatment)) {
         return [];
      }
      //Get Prescription Medicines
      $query = TreatmentHistory::where('TreatmentId', $treatment->TreatmentId);
      $query->whereNotNull('PrescriptionMedicine');
      $query->where('PersonId', $customerId);
      $query->orderBy('PushedAt', 'desc');
      $treatmentHistories = $query->get();

      if (!$treatmentHistories || count($treatmentHistories) <= 0) {
         return [];
      }

      $result = [];
      foreach ($treatmentHistories as $keyPrescription => $valuePrescription) {
         if (Helper::isJSON($valuePrescription->PrescriptionMedicine ?? '')) {
            $valuePrescription->PrescriptionMedicine = json_decode($valuePrescription->PrescriptionMedicine, true);
         }
         if (Helper::isJSON($valuePrescription->DiseaseProgressionNote ?? '')) {
            $valuePrescription->DiseaseProgressionNote = json_decode($valuePrescription->DiseaseProgressionNote, true);
         }

         $result[] = [
            'PrescriptionMedicine' => $valuePrescription->PrescriptionMedicine ?? '',
            'PushedAt' =>  date('d/m/Y', $valuePrescription->PushedAt ?? time()),
            'PrescriptionDiagnosis' => $valuePrescription->PrescriptionDiagnosis ?? '',
            'DiseaseProgressionNote' => $valuePrescription->DiseaseProgressionNote ?? '',
            'Note' => $valuePrescription->Note ?? '',
            'TreatmentHistoryId' => $valuePrescription->TreatmentHistoryId ?? 0,
            'PrescriptionDoctorNote' => $valuePrescription->PrescriptionDoctorNote ?? '',
            'PrescriptionGuardian' => $valuePrescription->PrescriptionGuardian ?? ''
         ];
      }
      return $result;
   }

   public function removePromotionTreatmentOffer($data)
   {
      if (!$data || empty($data)) {
         return false;
      }
      DB::beginTransaction();
      try {
         foreach ($data as $item) {
            $treatmentMedicalProcedureOfferId = $item['TreatmentMedicalProcedureOfferId'] ?? 0;
            //Get offer
            $offer = TreatmentMedicalProcedureOffer::find($treatmentMedicalProcedureOfferId);

            if (!$offer || empty($offer)) {
               DB::rollBack();
               return false;
            }
            //Get Tax percent
            $taxPercent = 0;
            $serviceTaxs = DB::select(DB::raw("SELECT  FGetServiceTaxPercent(" . ($offer->ServiceId ?? 0) . ",0,from_unixtime(UNIX_TIMESTAMP(),'%Y-%m-%d')) as ServiceTax;"));
            if ($serviceTaxs && !empty($serviceTaxs)) {
               foreach ($serviceTaxs as $serviceTax) {
                  if (empty($serviceTax) || !isset($serviceTax->ServiceTax)) {
                     continue;
                  }
                  $taxPercent = $serviceTax->ServiceTax ?? 0;
               }
            }
            $taxAmount = 0;
            //101 KCT - Không chịu thuế
            if ($taxPercent && !empty($taxPercent) && ((int) $taxPercent != 101)) {
               $taxAmount = ($taxPercent * ((float) $offer->SalePrice)) / 100;
            }

            $offer->PromotionId = null;
            $offer->PromotionVoucherId = null;
            $offer->DiscountPercent = 0;
            $offer->DiscountAmount = 0;
            $offer->DiscountType = 0;
            $offer->Amount = (((float) $taxAmount) + ((float) $offer->SalePrice)) * ((int) $offer->Quantity ?? 1);
            $offer->TaxAmount = $taxAmount;
            $offer->TaxPercent = $taxPercent ?? 0;
            $offer->save();
         }
         DB::commit();
         return true;
      } catch (\Exception $e) {
         Log::error('Func removePromotionTreatmentOffer: ' . $e->getMessage());
         return false;
      }
      DB::rollBack();
      return false;
   }

   public function addPromotionTreatmentOffer($treatmentId, $data)
   {
      $result = [
         'code' => false,
         'message' => 'Thêm khuyến mãi không thành công'
      ];
      if (!$treatmentId || empty($treatmentId) || !$data || empty($data) || !is_array($data)) {
         $result['message'] = 'Dữ liệu không hợp lệ';
         return $result;
      }
      //Add Promotion Treatment Offer
      DB::beginTransaction();
      try {
         foreach ($data as $item) {
            if (empty($item) || !is_array($item)) {
               continue;
            }
            $offer = TreatmentMedicalProcedureOffer::join('Treatment', 'TreatmentMedicalProcedureOffer.TreatmentId', '=', 'Treatment.TreatmentId')
               ->where('TreatmentMedicalProcedureOffer.TreatmentId', $treatmentId)
               ->where('TreatmentMedicalProcedureOffer.Id', $item['TreatmentMedicalProcedureOfferId'])
               ->select('TreatmentMedicalProcedureOffer.*', 'Treatment.PersonId as CustomerId')
               ->first();

            if (!$offer || empty($offer)) {
               DB::rollBack();
               Log::error('Func addPromotionTreatmentOffer: TreatmentMedicalProcedureOffer not found ', $item);
               $result['message'] = 'Dịch vụ tư vấn cho khách hàng không tồn tại';
               return $result;
            }
            if (isset($item['VoucherCode']) && !empty($item['VoucherCode'])) {
               //Check Voucher Code Valid
               if (!$this->checkVoucherCodeValid(
                  $offer->CustomerId ?? 0,
                  $offer->ServiceId ?? 0,
                  $item['PromotionId'] ?? 0,
                  $item['VoucherCode'] ?? '',
                  $offer->BasePrice ?? 0
               )) {
                  DB::rollBack();
                  Log::error('Func addPromotionTreatmentOffer: Promotion not valid ', $item);
                  $result['message'] = 'Chương trình khuyến mãi cho dịch vụ không hợp lệ';
                  return $result;
               }
               //Check Promotion Valid
            } else if (!$this->checkPromotionValid($offer->CustomerId ?? 0, $offer->ServiceId ?? 0, $item['PromotionId'] ?? 0)) {
               DB::rollBack();
               Log::error('Func addPromotionTreatmentOffer: Promotion not valid ', $item);
               $result['message'] = 'Chương trình khuyến mãi cho dịch vụ không hợp lệ';
               return $result;
            }
            //Get Tax percent
            $taxPercent = 0;
            $serviceTaxs = DB::select(DB::raw("SELECT  FGetServiceTaxPercent(" . ($offer->ServiceId ?? 0) . ",0,from_unixtime(UNIX_TIMESTAMP(),'%Y-%m-%d')) as ServiceTax;"));
            if ($serviceTaxs && !empty($serviceTaxs)) {
               foreach ($serviceTaxs as $serviceTax) {
                  if (empty($serviceTax) || !isset($serviceTax->ServiceTax)) {
                     continue;
                  }
                  $taxPercent = $serviceTax->ServiceTax ?? 0;
               }
            }
            if (isset($item['VoucherCode']) && !empty($item['VoucherCode']) && $item['VoucherCode'] != 'null') {
               $discount = $this->getDiscountByVoucher($offer->ServiceId ?? 0, $offer->SalePrice ?? 0, $taxPercent, $offer->Quantity, $item['PromotionId'] ?? 0, $item['VoucherCode'] ?? '');
            } else {
               $discount = $this->getDiscountByService($offer->ServiceId ?? 0, $offer->SalePrice ?? 0, $taxPercent, $offer->Quantity, $item['PromotionId'] ?? 0);
            }
            if (!$discount || empty($discount)) {
               DB::rollBack();
               Log::error('Func addPromotionTreatmentOffer: Discount not found ', $item);
               $result['message'] = 'Không tìm thấy thông tin khuyến mãi';
               return $result;
            }
            if (isset($discount['SalePrice']) && $discount['SalePrice'] < 0) {
               DB::rollBack();
               Log::error('Func addPromotionTreatmentOffer: SalePrice invalid ', $item);
               $result['message'] = 'Giá giảm không hợp lệ';
               return $result;
            }
            //Save Offer
            if (isset($discount['PromotionVoucherId']) && $discount['PromotionVoucherId'] > 0) {
               $offer->PromotionVoucherId = $discount['PromotionVoucherId'] ?? 0;
            }
            $offer->PromotionId = $item['PromotionId'] ?? 0;
            $offer->DiscountType = $discount['DiscountType'] ?? 0;
            $offer->DiscountPercent = $discount['DiscountPercent'] ?? 0;
            $offer->DiscountAmount = $discount['DiscountAmount'] ?? 0;
            $offer->Amount = $discount['Amount'] ?? 0;
            $offer->TaxAmount = $discount['TaxAmount'] ?? 0;
            $offer->TaxPercent = $taxPercent ?? 0;
            $offer->save();
         }
         DB::commit();
         $result = [
            'code' => true,
            'message' => 'Thêm khuyến mãi thành công'
         ];
         return $result;
      } catch (\Exception $e) {
         Log::error('Func addPromotionTreatmentOffer: ' . $e->getMessage());
         DB::rollBack();
         return $result;
      }
      DB::rollBack();
      return $result;
   }

   protected function checkVoucherCodeValid($customerId, $serviceId, $promotionId, $voucherCode, $price)
   {
      if (!$customerId || empty($customerId)) {
         return false;
      }
      if (!$serviceId || empty($serviceId)) {
         return false;
      }
      if (!$promotionId || empty($promotionId)) {
         return false;
      }
      if (!$voucherCode || empty($voucherCode)) {
         return false;
      }
      try {
         $header = ['Authorization' => 'Bearer ' . JWT_APP_TOKEN];
         $data = [
            'voucherCode' => $voucherCode,
            'customerId' => $customerId,
            'serviceId' => $serviceId,
            'promotionId' => $promotionId,
            'serviceCode' => [
               [
                  'code' => $serviceId,
                  'quantity' => 1,
                  'price' => (int) $price
               ]
            ]
         ];
         $remote = Factory::getRemote();
         $remote->request('module.views')
            ->from(API_PROMOTION_CHECK_VOUCHER_CODE)
            ->where($data)
            ->execute(true, $header);

         $response = $remote->loadVar(false);
         // Log::info("Remote check valid voucher code url:", [API_PROMOTION_CHECK_VOUCHER_CODE]);
         // Log::info("Remote check valid voucher code header:", $header);
         // Log::info("Remote check valid voucher code data:", $data);
         // Log::info("Remote check valid voucher code response", [$response]);
         if (!$response || $response === false) {
            return false;
         }
         return true;
      } catch (\Exception $e) {
         Log::error('Func checkVoucherCodeValid: ' . $e->getMessage());
         return false;
      }
      return false;
   }

   protected function checkPromotionValid($customerId, $serviceId, $promotionId)
   {
      $ipAddress = Helper::getClientIp();
      if (!$customerId || empty($customerId)) {
         return false;
      }
      if (!$serviceId || empty($serviceId)) {
         return false;
      }
      if (!$promotionId || empty($promotionId)) {
         return false;
      }
      $result = DB::select(DB::raw("call usp_AutoPromotion_GetDetail(?, ?, ?, ?)"), [$customerId, $serviceId, $promotionId, $ipAddress]);

      if (!$result || empty($result) || !is_array($result)) {
         return false;
      }
      foreach ($result as $promotion) {
         if (!isset($promotion->id) || empty($promotion->id)) {
            continue;
         }
         if ($promotion->id == $promotionId) {
            return true;
         }
      }

      //Check Promotion inValid
      return false;
   }

   protected function getDiscountByService($serviceId, $salePrice = 0, $taxPercent = 0, $quantity = 1, $promotionId = 0)
   {
      $result = [
         'DiscountType' => 0,
         'DiscountPercent' => 0,
         'DiscountAmount' => 0,
         'Amount' => 0,
         'TaxAmount' => 0,
         'TaxPercent' => $taxPercent
      ];
      if (!$serviceId || empty($serviceId)) {
         return $result;
      }
      if (!$salePrice || empty($salePrice)) {
         return $result;
      }
      if (!$promotionId || empty($promotionId)) {
         return $result;
      }

      $promotionService = PromotionService::where('ServiceId', $serviceId)
         ->where('PromotionId', $promotionId)
         ->first();
      if (!$promotionService || empty($promotionService)) {
         $promotion = Promotion::find($promotionId);
         if (!$promotion || empty($promotion)) {
            return $result;
         }
      }

      $resCalculate = $this->processDiscountCalculation($salePrice, $taxPercent, $quantity, $promotionService ?? $promotion);
      $result = [
         'DiscountType' => $resCalculate['DiscountType'] ?? 0,
         'DiscountPercent' => $resCalculate['DiscountPercent'] ?? 0,
         'DiscountAmount' => $resCalculate['DiscountAmount'] ?? 0,
         'Amount' => $resCalculate['Amount'],
         'TaxAmount' => $resCalculate['TaxAmount'],
         'TaxPercent' => $taxPercent
      ];

      return $result;
   }

   protected function getDiscountByVoucher($serviceId, $salePrice = 0, $taxPercent = 0, $quantity = 1, $promotionId = 0, $voucherCode = '')
   {
      $result = [
         'DiscountType' => 0,
         'DiscountPercent' => 0,
         'DiscountAmount' => 0,
         'PromotionVoucherId' => 0,
         'Amount' => 0,
         'TaxAmount' => 0,
         'TaxPercent' => $taxPercent
      ];
      if (!$serviceId || empty($serviceId)) {
         return $result;
      }
      if (!$salePrice || empty($salePrice)) {
         return $result;
      }
      if (!$promotionId || empty($promotionId)) {
         return $result;
      }
      if (!$voucherCode || empty($voucherCode)) {
         return $result;
      }
      $promotionVoucher = PromotionVoucher::where('PromotionId', $promotionId)
         ->where('Code', $voucherCode)
         ->first();
      if (!$promotionVoucher || empty($promotionVoucher)) {
         return $result;
      }
      $resCalculate = $this->processDiscountCalculation($salePrice, $taxPercent, $quantity, $promotionVoucher);
      $result = [
         'DiscountType' => $resCalculate['DiscountType'] ?? 0,
         'DiscountPercent' => $resCalculate['DiscountPercent'] ?? 0,
         'DiscountAmount' => $resCalculate['DiscountAmount'] ?? 0,
         'PromotionVoucherId' => $promotionVoucher->ID ?? 0,
         'Amount' => $resCalculate['Amount'] ?? 0,
         'TaxAmount' => $resCalculate['TaxAmount'] ?? 0,
         'TaxPercent' => $taxPercent
      ];
      return $result;
   }

   protected function processDiscountCalculation($salePrice, $taxPercent, $quantity, $promotion)
   {
      $discountAmount = 0;
      $discountPercent = 0;
      $taxAmount = 0;
      $amount = 0;
      $discountType = 0;

      //101 KCT - Không chịu thuế
      if ($taxPercent && !empty($taxPercent) && ((int) $taxPercent != 101)) {
         $taxAmount = ($taxPercent * ((float) $salePrice)) / 100;
      }
      //Set default amount
      $amount = (((float) $taxAmount) + ((float) $salePrice));
      
      $result = [
         'DiscountType' => $discountType,
         'DiscountPercent' => $discountPercent,
         'DiscountAmount' => $discountAmount,
         'Amount' => $amount,
         'TaxAmount' => $taxAmount,
         'TaxPercent' => $taxPercent
      ];
      try {
         //Get Discount Percent And Amount
         $maxDiscountValue = $promotion->MaxDiscountValue ?? 0;

         //Giảm theo phần trăm
         if (isset($promotion->DiscountType) && $promotion->DiscountType == 'PRDT001') {
            $discountAmount = $salePrice * ($promotion->DiscountValue ?? 0) / 100;
            if ($maxDiscountValue > 0 && $discountAmount > $maxDiscountValue) {
               $discountAmount = $maxDiscountValue;
            }
         } else {
            //Giảm theo số tiền
            $discountAmount = $promotion->DiscountValue ?? 0;
         }
         $discountAmount = round($discountAmount);

         //Nếu số tiền được giảm lớn hơn giá trị tối đa
         if ($maxDiscountValue > 0 && $discountAmount > $maxDiscountValue) {
            $discountAmount = $maxDiscountValue;
         }

         //Nếu số tiền được giảm nhiều hơn giá tiền thì lấy bằng giá tiền
         if ($discountAmount > $salePrice) {
            $discountAmount = $salePrice;
         }

         //Tính phần trăm giảm thực tế
         $discountPercent = 100 - ((($salePrice - $discountAmount) / $salePrice) * 100);
         $discountPercent = round($discountPercent);
         $salePrice = (int) $salePrice - $discountAmount;

         //101 KCT - Không chịu thuế
         if ($taxPercent && !empty($taxPercent) && ((int) $taxPercent != 101)) {
            $taxAmount = ($taxPercent * ((float) $salePrice)) / 100;
         }

         //Set amount
         $amount = (((float) $taxAmount) + ((float) $salePrice));
         $discountType = $this->convertDiscountType($promotion->DiscountType ?? '');
         $result = [
            'DiscountType' => $discountType,
            'DiscountPercent' => $discountPercent,
            'DiscountAmount' => $discountAmount,
            'Amount' => $amount,
            'TaxAmount' => $taxAmount,
            'TaxPercent' => $taxPercent
         ];
      } catch (\Exception $e) {
         Log::error('Func getDiscountPercentAndAmountByService: ' . $e->getMessage());
      }
      return $result;
   }

   protected function convertDiscountType($discountType)
   {
      $result = 0;
      if (!$discountType || empty($discountType)) {
         return $result;
      }
      switch ($discountType) {
         case 'PRDT001':
            $result = 1;
            break;
         case 'PRDT002':
            $result = 2;
            break;
         default:
            $result = 0;
            break;
      }
      return $result;
   }

   public function getListTreatment($customerId)
   {
      if ($customerId <= 0) {
         return [];
      }

      return $this->_model
         ->where('PersonId', $customerId)
         ->orderByDesc('TreatmentId')
         ->get()
         ->toArray();
   }
}
