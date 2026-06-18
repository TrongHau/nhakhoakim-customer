<?php

namespace App\Repositories;

use App\Libs\RedisLib;
use App\ContactRelationship;
use App\Customer;
use App\Repositories\Abstracts\EloquentRepository;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ContactRelationShipRepository extends EloquentRepository
{

	public function createContactRelationship($data)
	{
		if (!$data || empty($data) || !is_array($data)) {
			return false;
		}
		$contactRelationTypeRepo = new ContactRelationTypeRepository();
		if(isset($data['Id']) && !empty($data['Id'])) {
			$contactRelationType = $contactRelationTypeRepo->find($data['Id']);
			$sourceRelationCode = $contactRelationType->SourceRelationCode;
			$reverseRelationCode = $contactRelationType->ReverseRelationCode;
		} else {
			$sourceRelationCode = $data['SourceRelationCode'] ?? '';
			$reverseRelationCode = $data['DestinationRelationCode'] ?? '';
		}
		$sourceCustomerId = $data['SourceCustomerId'] ?? 0;
		$destinationCustomerId = $data['DestinationCustomerId'] ?? 0;
		
		return $this->syncRelationship($sourceCustomerId, $destinationCustomerId, $sourceRelationCode, $reverseRelationCode);
	}
	
	public function syncRelationship($sourceCustomerId, $destinationCustomerId, $sourceRelationCode, $reverseRelationCode) {
		$existRelation = $this->checkingExistsRelation($sourceCustomerId, $destinationCustomerId);

		if ($existRelation) {
			$result =  $this->updateRelationship($sourceCustomerId, $destinationCustomerId, $sourceRelationCode, $reverseRelationCode);
			// Log::info('updateRelationship'. $result);
			// Update Relation if change data
			return $this->updateRelationship($sourceCustomerId, $destinationCustomerId, $sourceRelationCode, $reverseRelationCode);
		} else {
			// Log::info('createRelationship');
			return $this->createRelationship($sourceCustomerId, $destinationCustomerId, $sourceRelationCode, $reverseRelationCode);
		}
	}

    // public function mappingContactRelationship($request)
    // {
    //     $SourceCustomerId = $request->input('SourceCustomerId', null);
    //     $DestinationCustomerId = $request->input('DestinationCustomerId', null);

    //     $contactRelationTypeRepo = new ContactRelationTypeRepository();
    //     $contactRelationType = $contactRelationTypeRepo->find($request->input('ContactRelationTypeId'));

    //     $relationData['SourceCustomerId'] = $SourceCustomerId;
    //     $relationData['DestinationCustomerId'] = $DestinationCustomerId;
    //     $relationData['SourceRelationCode'] = $contactRelationType->ReverseRelationCode ?? null;
    //     $relationData['DestinationRelationCode'] = $contactRelationType->SourceRelationCode ?? null;
    //     $relationData['CreatedBy'] = Auth::id();
    //     $relationData['CreatedDate'] = Carbon::now();
    //     return $this->create($relationData);
    // }

	public function update($id, array $attributes)
	{
		$editingContact = $this->find($id);
		$relationType = ContactRelationTypeRepository::findById($attributes['Id']);
		if ($editingContact) {
			$editingContact->SourceRelationCode = $relationType->SourceRelationCode;
			$editingContact->DestinationRelationCode = $relationType->ReverseRelationCode;
			if ($editingContact->save()) {
				return true;
			}
			return false;
		}
		return false;
	}

    public function checkingExistsRelation($sourceContact, $destContact)
    {
        if ($sourceContact && $destContact) {
            $exists = $this->_model->where(function ($query) use ($destContact, $sourceContact) {
                $query->where('SourceCustomerId', $sourceContact)->where('DestinationCustomerId', $destContact);
            })->exists();
            if ($exists) return true;
        }
        return false;
	}
	
	public function getCustomerRelationShip($customerId, $customerReplationId) {
		$data = $this->_model->where('SourceCustomerId', $customerId)->where('DestinationCustomerId', $customerReplationId)->get();
		return $data;
	}

	public function ListRelation($customerId,$customerCode=false,$getMain=false)
	{
		 $relatedContact = $this->_model::where('SourceCustomerId', $customerId)
			 ->selectRaw('*, DestinationCustomerId as RelatedContactId',[$customerId]);
		 $relatedData = Customer::joinSub($relatedContact,'RelationContact',function($join){
		 	$join->on('Customer.CustomerId','=','RelationContact.RelatedContactId');
		 })->get();

		 if($customerCode){
		 	$relatedData->load('hasClientRelation');
		 }

        $return = [];
		 if ($getMain) {
             $contactRepo = new Customer();
             $contactObj = $contactRepo->find($customerId);
             $mainContact = [];
             if ($contactObj) {
                 $mainContact['CustomerId'] = $contactObj->ContactId ?? null;
                 $mainContact['RelationName'] = trans('messages.relationship_main');
             }
             if ($mainContact) {
                 $return[] = $mainContact;	
             }
         }

		$result = $relatedData;
		if ($result->count() > 0) {
			foreach ($result as $key => $data) {
			    if ($data->SourceCustomerId == $customerId) {
			    	$relationData = ContactRelationTypeRepository::getNameByReverseGender($data->DestinationRelationCode , $data->SourceRelationCode);     
			    	$relationData2 = ContactRelationTypeRepository::getNameByReverseGender($data->SourceRelationCode, $data->DestinationRelationCode);     
                }
                if(isset($relationData) && !empty($relationData)) {
                	 $data->setAttribute('ReverseRelationCode', $relationData['Code']);
                	 $data->setAttribute('RelationName', $relationData['Name']);
                	 $data->setAttribute('RelationName2', $relationData['Name']);
                	 $data->setAttribute('RelationId', $relationData['RelationId']);
                }

				$data->makeHidden(['CreatedAt','UpdatedBy','UpdatedAt','LastActive','Migrate','CustomerCodeOld','CreatedAt','CreatedDate','CreatedBy','LastUpdatedDate','LastUpdatedBy','IsDeleted','PaymentCounter']);
				$data->makeHidden(['SourceRelationCode', 'DestinationRelationCode','DestinationCustomerId','SourceCustomerId','RelatedContactId', 'Id', 'UpdatedDate']);
			}
            $result = $result->toArray() ?? [];
            $return = array_merge($return, $result);
		}
		return $return;
	}

	protected function getModel()
	{
		return ContactRelationship::class;
	}

	public function updateRelationship($sourceContactId, $destContactId, $sourceCode, $destinationCode) {
		Log::info("updateRelationship $sourceContactId $destContactId $sourceCode $destinationCode");
        
        $result1 = $this->_model->where('SourceCustomerId', $sourceContactId)
            ->where('DestinationCustomerId', $destContactId)
            ->update([
                'SourceRelationCode' => $destinationCode,
                'DestinationRelationCode' => $sourceCode
            ]);
			
        Log::info("updateRelationship result 1", [$result1]);

        $result2 = $this->_model->where('SourceCustomerId', $destContactId)
            ->where('DestinationCustomerId', $sourceContactId)
            ->update([
                'SourceRelationCode' => $sourceCode,
                'DestinationRelationCode' => $destinationCode
            ]);
        Log::info("updateRelationship result 2", [$result2]);
        
		return true;
    }

    public function deleteRelationship($sourceContactId, $destinationContactId) {
		
		if (!$sourceContactId || !$destinationContactId) return false;
		
		Log::info("deleteRelationship $sourceContactId - $destinationContactId");
		
        $query = $this->_model->newQuery();
        return $query->where(function ($query) use ($sourceContactId, $destinationContactId) {
            $query->where('SourceCustomerId', $sourceContactId)->where('DestinationCustomerId', $destinationContactId);
        })->orWhere(function ($query) use ($sourceContactId, $destinationContactId) {
            $query->where('SourceCustomerId', $destinationContactId)->where('DestinationCustomerId', $sourceContactId);
        })->delete();
    }

    public function createRelationship($sourceContactId, $destContactId, $sourceCode, $destinationCode) {
		
		// Create relationship
        $createRelation['SourceCustomerId'] = $sourceContactId;
        $createRelation['DestinationCustomerId'] = $destContactId;
        $createRelation['SourceRelationCode'] = $sourceCode;
        $createRelation['DestinationRelationCode'] = $destinationCode;
		$createRelation['UpdatedDate'] = date('Y-m-d');
		$createRelation['CreatedDate'] = date('Y-m-d');
		
		Log::info('createRelationship 1', $createRelation);
		
		$result1 = $this->create($createRelation);

        // Create Reverse relationship
        $createRelation['SourceCustomerId'] = $destContactId;
        $createRelation['DestinationCustomerId'] = $sourceContactId;
        $createRelation['SourceRelationCode'] = $destinationCode;
        $createRelation['DestinationRelationCode'] = $sourceCode;
		
		Log::info('createRelationship 2', $createRelation);
		
		$result2 = $this->create($createRelation);
        if ($result1 && $result2) return true;
        return false;
    }

}