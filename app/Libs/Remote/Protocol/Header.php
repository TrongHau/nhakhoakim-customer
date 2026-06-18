<?php
namespace App\Libs\Remote\Protocol;



/**
 * 
 * @author VQuyen
 *
 */
class Header
{
    /**
     * @var Token
     */
    protected $Token = null;
    
    /**
     * @param Token $Token
     */
    public function __construct($Token = null)
    {
        $this->Token = $Token;
        $this->_init();
    }
    
    /**
     * @return array
     */
    public function build($arr = null)
    {
       
        $fields = [];
        foreach ($this AS $prop => $value) {
            if ($prop != 'Token' && $value !== null) {
                $fields[$prop] = $value;
            } 
        }
       
        if ($this->Token) $fields['Authorization'] = $this->Token->build();

        if ($arr && is_array($arr)) {
            foreach ($arr as $key => $value) {
                $fields[$key] = $value;
            }
        }
        return $fields;
    }
    
    /**
     * @param string $key
     * @param string $value
     * @return Header
     */
/*     public function set($key, $value)
    {
        $key = trim($key);
        if ($key) {
            $this->$key = $value;
        }
        
        return $this;
    } */
    
    /**
     * @return Token
     */
    public function getToken()
    {
        return $this->Token;
    }
    
    /**
     * @param null|Token  $Token
     */
    public function setToken($Token = null)
    {
        if ($Token == null || $Token instanceof Token) {
            $this->Token = $Token;
        }
        
        return $this;
    }
    
    /**
     * initial default http-header properites
     */
    protected function _init()
    {
        // default fields & value
        $fields = [
            'Accept'                        => '*/*',
            'Accept-Charset'                => 'utf-8',
            'Accept-Encoding'               => '*',
            'Cache-Control'                 => 'no-cache',
            'Connection'                    => 'keep-alive',
            'Origin'                        => defined('WWW') ? WWW : '',
            'Access-Control-Allow-Origin'   => '*',
            'Keep-Alive'                    => 'timeout=5, max=1000',
            'Content-Type'                  => 'application/x-www-form-urlencoded'
        ];
        
        // user-agent build
        $UserAgent = 'JALIO Remote Client';
        if (defined('APPNAME')) $UserAgent = APPNAME;
        
        if (isset($_SERVER['SERVER_SOFTWARE'])) {
            $UserAgent .= '. ' . $_SERVER['SERVER_SOFTWARE'] . ' - PHP ' . phpversion();
        }
        
        $fields['User-Agent'] = $UserAgent;
        
        //init properities
        foreach ($fields AS $prop => $value) $this->$prop = $value;
    }
}