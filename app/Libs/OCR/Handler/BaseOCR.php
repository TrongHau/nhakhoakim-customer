<?php

namespace App\Libs\OCR\Handler;

abstract class BaseOCR
{

    protected $image;

    protected $api;

    protected $secretKey;

    public function setAPI($api)
    {
        $this->api = $api;
        return $this;
    }

    public function setSecretKey($secretKey)
    {
        $this->secretKey = $secretKey;
        return $this;
    }

    public function setImage($image)
    {
        $this->image = $image;
        return $this;
    }

    abstract function exec();
}
