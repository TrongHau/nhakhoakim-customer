<?php

namespace App\Exceptions;

use Exception;

class ImportExcelException extends Exception
{
    public function errorMessage()
    {
        return $this->getMessage();
    }
}