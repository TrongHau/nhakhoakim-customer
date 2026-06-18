<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Repositories\TicketSupportRepository;
use Illuminate\Support\Facades\Validator;

class DepositTransactionController extends Controller
{
    /**
     * @var TicketSupportRepository
     */
    protected $ticketSupportRepo;

    public function __construct(TicketSupportRepository $ticketSupportRepo)
    {
        parent::__construct();
        $this->ticketSupportRepo = $ticketSupportRepo;
    }

    public function updateInvoiceDiscount(Request $request)
    {
        $validate = Validator::make($request->all(), [
            'CustomerCode' => 'required',
            'Amount' => 'required',
            'TicketId' => 'required|numeric',
        ]);

        if ($validate->failed()) {
            $errors = $validate->errors();
            $this->addMessage($errors->first(), 'ERR001', self::$ERROR);
            return $this->json(false, 'bool');
        }

        $data = $this->ticketSupportRepo->updateInvoiceDiscount($request->all());

        if (isset($data) && $data && is_array($data)) {
            if ($data['Result'] == 1) {
                $this->addMessage($data['ResultMessage'] ?? '', 'MSGSTORE001', self::$SUCCESS);
                return $this->json(true, 'bool');
            } else {
                $this->addMessage($data['ResultMessage'] ?? 'Có lỗi xảy ra, vui lòng thử lại!', 'MSGSTORE001', self::$ERROR);
                return $this->json(false, 'bool');
            }
        }

        $this->addMessage('Có lỗi xảy ra, vui lòng thử lại!', 'ERR002', self::$ERROR);
        return $this->json(false, 'bool');
    }
}
