<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Repositories\ContactRelationShipRepository;
use App\Repositories\ContactRelationTypeRepository;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;


class ContactRelationshipController extends Controller
{
	protected $contactRelationshipRepo;

	public function __construct()
	{
		parent::__construct();
		$this->contactRelationshipRepo = new ContactRelationShipRepository();
	}

	/**
	 * Display a listing of the resource.
	 *
	 * @return Response
	 */
	public function getRelation(Request $request)
	{
		//TODO: add validator for contact Id
		$validator = Validator::make($request->all(),[
			'CustomerId'=>'required',
		]);
		if($validator->errors()->count()>0){
			$this->formatValidationMessages($validator->errors()->getMessages());
			return $this->json(false,'bool');
		}
		$customerCode = $request->input('CustomerCode',false);
		$result = $this->contactRelationshipRepo->ListRelation($request->CustomerId);
		return $this->json([$this->formatData('List Relation of Contact',$result)]);
	}

    /**
     * @OA\Post(
     *     path="/contact/relationship/create",	
     *     description="Create new contact relationship",
     *     tags={"ContactRelationship"},
     *     @OA\RequestBody(
     *      @OA\MediaType(
     *             mediaType="application/x-www-form-urlencoded",
     *              @OA\Schema(ref="#/components/schemas/ContactRelationshipCreateRequestBody")
     *         )
     *      ),
     *      @OA\Response(
     *         response=200,
     *         description="Status and Messages"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Token wrong"
     *     ),
     *     security={
     *       {"bearerAuth": {}}
     *     }
     * )
     */

	/**
	 * Store a newly created resource in storage.
	 * @param Request $request
	 * @return Response
	 */
	public function store(Request $request)
	{
		
        $validator = Validator::make($request[0], [
            'SourceCustomerId' => 'required|integer',
        ]);

        if ($validator->fails()) {
            $this->formatValidationMessages($validator->errors()->messages());
            return $this->json(false, 'bool');
        }
		// Log::info('create a contact relation 122 ');
		// Log::info($request[0]);
        $created = $this->contactRelationshipRepo->createContactRelationship($request[0]);
        if ($created) {
            $this->addMessage(trans('messages.create_success', ['name' => trans('messages.attributes.contact_relationship')]));
            $result[] = $this->formatData('Contact Relationship', $created);
            return $this->json($result);
        } else {
            $this->addMessage(trans('messages.create_fail', ['name' => trans('messages.attributes.contact_relationship')]), 200, config('constants.messages.error'));
            return $this->json(false, 'bool');
        }

	}

	public function updateRelation(Request $request) {

		$dataRelation = $request->all();
		
		Log::info('updateRelation', $dataRelation);		
		$contactRelationTypeRepo = new ContactRelationTypeRepository();
		$relationTypeId = $dataRelation['RelationshipId'];
		$sourceRelationCode = $dataRelation['DestinationRelationCode'] ?? '';
		$reverseRelationCode = $dataRelation['SourceRelationCode'] ?? '';
		if(empty($sourceRelationCode) && empty($reverseRelationCode)) {
			$relationTypeObj = $contactRelationTypeRepo->find($relationTypeId);
			$sourceRelationCode = isset($relationTypeObj->SourceRelationCode) ? $relationTypeObj->SourceRelationCode : '';
			$reverseRelationCode = isset($relationTypeObj->ReverseRelationCode) ? $relationTypeObj->ReverseRelationCode : '';
		}
		
		$customerId = $dataRelation['CustomerId'];
		$customerReplationId = $dataRelation['RelatedTo'];
		
		$result = $this->contactRelationshipRepo->syncRelationship($customerId, $customerReplationId, $sourceRelationCode, $reverseRelationCode);
		if ($result) {
			return $this->json(true, 'bool', 200);
		} else {
			return $this->json(false, 'bool');
		}
	}

	/**
	 * Update the specified resource in storage.
	 *
	 * @param Request $request
	 * @param int                      $id
	 *
	 * @return Response
	 */
	public function update(Request $request, $id)
	{
		if (Gate::denies('contact_edit')) {
			abort(403);
		}
		$rule = [
			'ContactRelationTypeId' => 'required|integer|exists:ContactRelationship,ContactRelationshipId',
		];
		$validator = Validator::make($request->all(), $rule);
		if($validator->errors()->count()>0){
			$this->formatValidationMessages($validator->errors()->all());
			return $this->json(false,'bool');
		}
		if($this->contactRelationshipRepo->update($id,$request->all())){
			$this->addMessage('Update Contact Relation Success!', 200, 2);
			return $this->json(true, 'bool', 200);
		}
		return $this->json(false, 'bool', 200);
	}

	/**
	 * Remove the specified resource from storage.
	 *
	 * @param int $id
	 *
	 * @return Response
	 */
	public function destroy(Request $request)
	{
		// find Id Relationship

		Log::info('delete relationship', $request->all());

		$customerId = $request[0]['CustomerId'];
		$customerReplationId = $request[0]['RelatedTo'];
		
		$deleteResult = $this->contactRelationshipRepo->deleteRelationship($customerId, $customerReplationId);
		if($deleteResult) {
			return $this->json(true, 'bool', 200);
		}
		return $this->json(false, 'bool', 200);
	}



}
