<?php

namespace App\Libs;


abstract class BaseExportStorage
{
    abstract function uploadFile($filePath, $saveDir, $fileExportName = false);
}