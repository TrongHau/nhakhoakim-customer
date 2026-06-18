<?php


namespace App\Exports;

use App\Libs\BaseExportExcel;

class BaseReportWithTotal extends BaseExportExcel
{
    protected $data = [];
    protected $totalRow;

    public function __construct($data, $totalRow)
    {
        $this->data = $data ?? [];
        $this->totalRow = $totalRow ?? [];
        $this->handleTotalRow();
    }

    protected function getData()
    {
        return $this->data;
    }

    /**
     * Xử lý dòng tổng
     * Tạo dòng tổng từ $totalRow mà không bị giới hạn bởi các items cố định trong object
    */
    public function handleTotalRow()
    {
        $totalRow = $this->totalRow;

        $totalData = ['Tổng'];
        if ($this->totalRow) {
            $totalData = array_merge($totalData, array_values(get_object_vars($totalRow)));
        } else {
            $totalData = array_merge($totalData, array_fill(0, count($this->data[0]) - 1, 0));
        }

        array_unshift($this->data, $totalData);
    }
}
