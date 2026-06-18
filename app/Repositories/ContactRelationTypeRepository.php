<?php

namespace App\Repositories;

use App\ContactRelationType;
use App\Repositories\Abstracts\EloquentRepository;

class ContactRelationTypeRepository extends EloquentRepository
{
    protected function getModel()
    {
        return ContactRelationType::class;
    }

    public function getListByReverseGender($relationGender, $parentGender) {
        $query = $this->_model->newQuery();
        $query->select(['Id as RelationshipId', 'RelationName as Name', 'Priority']);
        if ($parentGender && !empty($parentGender)) {
            $query->where('ReverseGender', $parentGender);
        }
        if ($relationGender && !empty($relationGender)) {
            $query->where('SourceGender', $relationGender);
        }
        return $query->get();
    }

    public function getRelationshipByTypeFriend() {
        return $this->_model->where('ReverseRelationCode', 'Friend')
            ->select(['Id as RelationshipId', 'RelationName as Name', 'Priority'])->get();
    }

    public static function getNameByReverseGender($source, $reverse) {
        $repo = new self();
        $data = $repo->_model->where('SourceRelationCode', $source)
            ->where('ReverseRelationCode', $reverse)
            ->select(['RelationName as Name', 'Id as RelationId', 'ReverseRelationCode as Code'])->get()->toArray();
        if(!empty($data[0])) {
            return $data[0];
        } else {
            return '';
        } 
    }


	public static function findById($id){
    	$repo = new self();
    	$result= $repo->find($id);
    	if($result){
    		return $result;
	    }
    	return false;
    }
    
}