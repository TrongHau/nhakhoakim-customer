<?php

namespace App\Repositories;

use App\LuckyDrawCampaign;
use App\Repositories\Abstracts\EloquentRepository;
use Carbon\Carbon;
use Illuminate\Support\Str;

class LuckyDrawCampaignRepository extends EloquentRepository
{
   /**
    * @return string
    */
   protected function getModel(): string
   {
      //Required model
      return LuckyDrawCampaign::class;
   }

   public function getLuckyDrawCampaign()
   {
      $now = Carbon::now()->toDateTimeString();
      $query = $this->_model->newQuery();

      $query->where('State', 1);

      $query->where('StartDate', '<=', $now);

      $campaigns = $query->get()->toArray();

      $now = Carbon::now();
      foreach ($campaigns as &$campaign) {
         $start = Carbon::parse($campaign['StartDate']);
         $end = Carbon::parse($campaign['EndDate']);

         $campaign['IsCurrentCampaign'] = $now->between($start, $end) ? 1 : 0;
         $campaign['NameUnsign'] = Str::slug($campaign['Name'], '_');
      }
      return $campaigns;
   }
}
