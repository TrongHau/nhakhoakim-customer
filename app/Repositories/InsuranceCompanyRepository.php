<?php

namespace App\Repositories;

use App\InsuranceCompany;
use App\Repositories\Abstracts\EloquentRepository;

class InsuranceCompanyRepository extends EloquentRepository
{
   /**
    * @return string
    */
	protected function getModel(): string
	{
		//Required model
		return InsuranceCompany::class;
	}

	
	public function getContactInsurance($data)
	{
		$lmstart = $data['lmstart'] ?? 0;
		$limit = $data['limit'] ?? 20;

		$query = $this->_model->newQuery();

		return $query->paginate($limit, ['*'], 'page', round((int) $lmstart / (int) $limit) + 1);;
	}
}
