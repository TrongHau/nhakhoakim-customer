<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Repositories\LuckyDrawCampaignRepository;
use App\Repositories\PersonTitleRepository;
use Illuminate\Support\Facades\Validator;

class CommonController extends Controller
{
	protected $luckyDrawCampaignRepo;
	protected $personTitleRepo;

	public function __construct()
	{
		parent::__construct();
		$this->luckyDrawCampaignRepo = new LuckyDrawCampaignRepository();
		$this->personTitleRepo = new PersonTitleRepository();
	}

    public function getOptionList(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'Options' => 'required|array',
            'Options.*' => 'required'
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'ERR001', self::$ERROR);
            return $this->json([]);
        }

        $options = $request->input('Options', []);
        $results = [];
        foreach ($options as $option) {
            $data = null;
            switch ($option) {
                case 'LuckyDrawCampaign':
                    $data = $this->luckyDrawCampaignRepo->getLuckyDrawCampaign();
                    $results[] = $this->formatData('LuckyDrawCampaign', $data, 'Grid');
                    break;
                default:
                    break;
            }
        }

        return $this->json($results, 'views');
    }

    public function getPersonTitleList()
    {
        $data = $this->personTitleRepo->getPersonTitleList();
        $results[] = $this->formatData('PersonTitleList', $data, 'Grid');

        return $this->json($results, 'views');
    }

    public function getGenderList()
    {
        $data = $this->personTitleRepo->getPersonTitleList();
        foreach ($data as &$item) {
            $item['GenderId'] = 0;
            $item['GenderName'] = "";
        }
        unset($item); // best practice

        // Thêm Nam, Nữ
        $data[] = [
            'PersonTitleId' => 0,
            'PersonTitleName' => '',
            'GenderId' => 1,
            'GenderName' => "Nam",
            'IsActive' => 1,
        ];

        $data[] = [
            'PersonTitleId' => 0,
            'PersonTitleName' => '',
            'GenderId' => 2,
            'GenderName' => "Nữ",
            'IsActive' => 1,
        ];

        $results[] = $this->formatData('GenderList', $data, 'Grid');
        return $this->json($results, 'views');
    }
}
