<?php


namespace App\Exports;

use App\Libs\BaseExportExcel;

class BaseReport extends BaseExportExcel
{
    protected $data = [];

    public function __construct($data)
    {
        $this->data = $data ?? [];
    }

    protected function getData()
    {
        return $this->data;
    }
}
