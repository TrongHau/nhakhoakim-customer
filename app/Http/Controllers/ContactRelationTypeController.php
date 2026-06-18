<?php

namespace App\Http\Controllers;

use App\Repositories\ContactRelationTypeRepository;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class ContactRelationTypeController extends Controller
{
    protected $contactRelationTypeRepo;

    public function __construct()
    {
        parent::__construct();
        $this->contactRelationTypeRepo = new ContactRelationTypeRepository();
    }

    /**
     * @param $parentGender
     * @param $relationGender
     * @return mixed
     */
    public function getContactRelationTypeList(Request $request) {

        $parentGender = $this->converGender($request['ParentGender']);
        $relationGender = $this->converGender($request['Gender']);
        $data = $this->contactRelationTypeRepo->getListByReverseGender($parentGender, $relationGender);
        $result[] = $this->formatData('CustomerRelationshipByGender', $data);
        return $this->json($result);
    }

    public function getContactRelationTypeFriend(Request $request) {

        $data = $this->contactRelationTypeRepo->getRelationshipByTypeFriend();
        $result[] = $this->formatData('CustomerRelationshipByFriend', $data);
        return $this->json($result);
    }

    protected function converGender($gender) {
        if(empty($gender)) return '';
        if ($gender == 'undefined' || $gender == 'null') {
            return '';
        }
        if($gender == 1) {
            return 'Male';
        } else {
            return 'Female';
        }
    }
}
