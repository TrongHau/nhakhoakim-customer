<?php
namespace App\Libs\Remote\Protocol;

use App\Libs\Factory;

use \Exception AS Exception;
use App\Libs\Helper;

/**
 * 
 * @author giangdn
 *
 */
class Token
{
    /**
     * @var string
     */
    protected $JWTToken         = '';
    
    /**
     * @var string
     */
    protected $Carrier    = 'Bearer';
    
    /**
     * @param string $JWTToken
     * @param string $Carrier
     */
    public function __construct($JWTToken = '', $Carrier = 'Bearer')
    {
        if ($JWTToken) {
            $this->JWTToken = $JWTToken;
            $this->Carrier  = $Carrier;
        } else {
            //app('log')->error('empty jwt init');
        }
    }
    
    /**
     * @return string
     */
    public function build()
    {
        $str = '';
        if ($this->JWTToken) {
            if ($this->Carrier) $str .= $this->Carrier;
            $str .= ' ' . $this->JWTToken;
        }
        
        return $str;
    }
    
    /**
     * @param string $JWTToken
     */
    public function setToken($JWTToken)
    {
        $this->JWTToken =  $JWTToken;
    }
    
    /**
     * @param string $Carrier
     */
    public function setCarrier($Carrier)
    {
        $this->Carrier =  $Carrier;
    }
    
    /**
     * @return string
     */
    public function getToken()
    {
        return $this->JWTToken;
    }
    
    /**
     * @throws Exception
     * @return Token
     */
    public function bindFromApp()
    {
        try {
            $AuthToken = Helper::getToken();
            if ($AuthToken) $this->setToken($AuthToken);
        } catch (Exception $e) {
           app('log')->error('Bind application authen-token fail: ' . $e->getMessage());
            throw $e;
        }
        return $this;
    }
    
    /**
     * @return string
     */
    public function getCarrier()
    {
        return $this->Carrier;
    }
    
    /**
     * send by bearer attact method
     * @var string
     */
    static public $BEARER   = 'Bearer';
    
    /**
     * send by basic attact method
     * @var string
     */
    static public $BASIC    = 'Basic';
}