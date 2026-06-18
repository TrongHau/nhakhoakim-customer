<?php
namespace App\Libs\Remote;


use App\Libs\Factory;
use GuzzleHttp\Client as GuzzClient;
use GuzzleHttp\Exception\GuzzleException;
use \Exception AS Exception;
use App\Libs\Remote\Protocol\Header;
use App\Libs\Remote\Protocol\Params;
use App\Libs\Remote\Protocol\Token;
use Illuminate\Support\Facades\Log;

/**
 *
 * @author VQuyen
 *
 * @desc
 *  thực hiện truy vấn http từ server remote (trong hoặc ngoài các server microservice)
 * @uses
 *  kiểu 1: $r->get(array(danh sách các thứ cần lấy))->from(string $uri);
 * @uses
 *  kiểu 2: $r->request(array(danh sách các thứ cần lấy))->from(string $uri)->where(array(mảng các tham số))
 * @uses
 *  kiểu 3: $r->post(array(mảng các tham số))->to(string $uri) hoặc $r->put(array(mảng các tham số))->to(string $uri);
 * @uses
 *  thực hiện truy vấn gọi $r->execute();
 * @uses
 *  lấy kết quả trả về gọi phương thức loadVar() , loadVarList()
 */
class Remote
{
    /**
     * @var array
     */
    protected $_callName = array();
    
    /**
     * @var array
     */
    protected $_callArguments = array();
    
    /**
     * @var string
     */
    protected $RemoteUri = null;
    
    /**
     * @var int
     */
    protected $Method   = 0;
    
    /**
     * @var Params
     */
    protected $Params   = null;
    
    /**
     * @var Header
     */
    protected $Header   = null;
    
    /**
     * @var string
     */
    protected $Response = null;
    
    /**
     * @var int
     */
    protected $ResponseCode = 0;
    
    /**
     * @var array
     */
    protected $Selector = [];
    
    /**
     * @param Header $Header
     * @param Params $Params
     */
    public function __construct($Header = null, $Params = null)
    {
        //header initial
         if ($Header == null || !($Header instanceof Header)) {

            $this->Header = new Header();
        } else $this->Header = $Header;
        
         //params initial
        if ($Params == null || !($Params instanceof Params)) {
            $this->Params = new Params();
        } else $this->Params = $Params;  
    }
    
    /**
     * @param string $name
     * @param string $arguments
     * @return App\Libs\Remote\Remote
     */
    public function __call($name, $arguments)
    {
        $this->_callArguments[] = $arguments;
        $this->_callName[] = $name;
        
        return $this;
    }
    
    protected function _parseChainingToRequest()
    {
        for($i = 0, $n = count($this->_callName); $i < $n; $i++) {
            $call = strtolower($this->_callName[$i]);
            switch ($call) {
                case 'from':
                case 'to':
                    if (isset($this->_callArguments[$i][0])) {
                        $this->RemoteUri    = $this->_callArguments[$i][0];
                    } else {
                       app('log')->error('Empty REMOTE_URI to perform request to');
                        throw new \Exception('Empty REMOTE_URI to perform request to');
                    }
                    break;
                case 'where':
                    $this->Params->addMultiple($this->_callArguments[$i]);
                    break;
                case 'post':
                    $this->Method       = self::$POST;
                    $this->Params->addMultiple($this->_callArguments[$i]);
                    break;
                case 'put':
                    $this->Method       = self::$PUT;
                    $this->Params->addMultiple($this->_callArguments[$i]);
                    break;
                case 'get':
                    $this->Method       = self::$GET;
                    $this->Selector     = $this->_callArguments[$i];
                    break;
                case 'request':
                    $this->Method       = self::$POST;
                    $this->Selector     = $this->_callArguments[$i];
                    break;
                case 'multipart':
                    $this->Method       = self::$POST;
                    $this->Params->addMultiple($this->_callArguments[$i], 'multipart');
                    break;
                default:
                    app('log')->eror('Remote unsuport method: '. $call);
            }
        }
    }
    
    /**
     * @param string $selector
     * @return mixed
     */
    protected function _trail($selector)
    {
        try {
            if ($this->Response && $Response = json_decode($this->Response)) {
                //special selector: select '*'
                if (trim($selector) == '*') return $this->Response;
                
                //trailing selector
                $sltChaining    = explode('.', $selector);
                
                foreach ($sltChaining AS $slt) {
                    $slt = trim($slt);
                    
                    // attribute select or not, for example views[name=grid]
                    $pattern = '/.*\[.*=.*\]/';
                    $matches = [];
                    if (preg_match($pattern, $slt, $matches) > 0) {
                        foreach ($matches AS $match) {
                            $temp       = explode('[', $match);
                            $slt        = $temp[0];
                            $expression = explode('=', str_replace(']', '', $temp[1]));
                            
                            if (isset($Response->$slt) && $Response->$slt && isset($expression[0]) && isset($expression[1])) {
                                $Response = $Response->$slt;
                                if (count($Response)) {
                                    $isFound = false;
                                    foreach ($Response AS $el) {
                                        $con = $expression[0];
                                        $val = $expression[1];
                                        if (isset($el->$con) && $el->$con == $val) {
                                            $Response   = $el;
                                            $isFound    = true;
                                            break;
                                        }
                                    }
                                    if (!$isFound) $Response = null;
                                }
                            } else $Response = null;
                        }
                    } elseif (isset($Response->$slt)) $Response = $Response->$slt;
                }
                
                return $Response;
            }
        } catch (\Exception $e) {
            app('log')->error('Remote trail response fail into: '. $selector);
        }
        
        return null;
    }
    
    /**
     * @return array
     */
    protected function _buildOptions($arr=null)
    {
        $params = $this->Params->curlSerialize();
        $header = $this->Header->build($arr);

        return array_merge(['headers' => $header], $params);
    }
    
    /**
     * lấy tất cả các kết quả tương ứng với các selector
     * @param array $dft giá trị mặc định trả về khi truy vấn không có trong kết quả
     * @return array
     */
    public function loadVarList($dft  = [])
    {
        if (count($this->Selector) && $this->Response) {
            $varList = array();
            foreach ($this->Selector AS $selector) {
                $varList[$selector] = $this->_trail($selector);
            }
            
            return $varList;
        }
        
        return $dft;
    }
    
    /**
     * lấy kết quả ứng với selector đầu tiên
     * @param mixed $dft giá trị mặc định trả về khi truy vấn không có trong kết quả
     * @return mixed
     */
    public function loadVar($dft = null)
    {
        if (count($this->Selector) && $this->Response) {
            $tem            = $this->Selector;
            $selector       = array_shift($tem);
             if (($var = $this->_trail($selector)) !== null) return $var;
            
        }
        
        return $dft;
    }
    
    /**
     * @return NULL | array
     */
    public function getResponseMessages()
    {
        if ($this->Response && $objResponse = json_decode($this->Response)) {
            return isset($objResponse->messages) ? $objResponse->messages : null;
        }
    }
    
    /**
     * @return string
     */
    public function getResponse()
    {
        return $this->Response;
    }
    
    /**
     * @return App\Libs\Remote\Protocol\Header
     */
    public function getHeader()
    {
        return $this->Header;
    }
    
    /**
     * @return App\Libs\Remote\Protocol\Params
     */
    public function getParams()
    {
        return $this->Params;
    }
    
    
    /**
     * @return App\Libs\Remote\Protocol\Token
     */
    public function getToken()
    {
        return $this->Header->getToken();
    }
    
    /**
     * @return number
     */
    public function getResponseCode()
    {
        return $this->ResponseCode;
    }
    
    /**
     * reset all prop to default value
     */
    public function renew()
    {
        $listResetNull = array('Response', 'RemoteUri', 'Header', 'Params');
        foreach ($listResetNull AS $prop) $this->$prop = null;
        
        $listResetArrayNull = array('_callName', '_callArguments', 'Selector');
        foreach ($listResetArrayNull AS $prop) $this->$prop = [];
        
        $this->Method       = 0;
        $this->ResponseCode = 0;
    }

    /**
     * @param string $uri
     * @param array $params
     * @param string $method
     * @param array $headers
     * @return mixed|boolean
     */
    public function curl($uri, $params = [], $method = 'POST', $headers = [])
    {
        try {
            $options = array_merge(['headers' => $headers], $params);
            $guzzClient     = new GuzzClient();
            $res            = $guzzClient->request($method, $uri, $options);
            $this->ResponseCode     = $res->getStatusCode();
            $this->Response         = $res->getBody()->getContents();

        } catch (Exception $e) {
            app('log')->error($e->getMessage());
          
        }
    }
    
    /**
     * @param boolean $JwtAuthorize the remote application required to jwt-authen or not
     */
    public function execute($JwtAuthorize = true,$arr=null)
    {
       
        try {
           
            // parse query to build request params
            $this->_parseChainingToRequest();
            
            // truy vấn nội bộ giữa các server microservice
           if ($JwtAuthorize) {
                $this->Params->bindFromApp();
                
                //jwt token initial
                $token = new Token();
                $this->Header->setToken($token->bindFromApp());
            }   

            // merge Param & Header
            $options = $this->_buildOptions($arr);
            if (isset($options[Params::$MULTIPART]) && isset($options['headers']) && isset($options['headers']['Content-Type'])) {
                    unset($options['headers']['Content-Type']);
            }
            // method valid
            $methods = array(self::$POST => 'POST', self::$GET => 'GET', self::$PUT => 'PUT');
            $guzzClient     = new GuzzClient();
            $res            = $guzzClient->request($methods[$this->Method], $this->RemoteUri, $options);
            $this->ResponseCode     = $res->getStatusCode();
            $this->Response         = $res->getBody()->getContents();

        } catch (Exception $e) {
            app('log')->error($e->getMessage());
          
        }
    }
    public function sendXml(string $uri,$dataXml){
        try{
            $guzzClient     = new GuzzClient();
            $option = ['headers'=>['Content-Type' => 'text/xml'],'body'=>$dataXml];
            $res =$guzzClient->request('POST',$uri,$option);
            $status = $res->getStatusCode();
            $response =  $res->getBody()->getContents();
            switch($status){
                case 200:
                    $body = str_ireplace(['SOAP-ENV:', 'SOAP:'], '', $response);
                    if($response = simplexml_load_string($body)){
                        return $response->Body;
                    }else{
                        return $response;
                    }
                default:
                    return false;
            }
        }catch(\Exception $e){
            Log::error($e->getMessage());
            return false;
        }
        return false;
    }
    
    
    /**
     * @return App\Libs\Remote\Remote
     */
    public static function getInstance()
    {
        if (self::$instance == null) {
            self::$instance = new Remote();
        }
        
        return self::$instance;
    }
    
    /**
     * @param Token $Token
     */
    public function setToken($Token)
    {
        $this->Header->setToken($Token);
        return $this;
    }
    
    /**
     * @var Remote
     */
    protected static $instance = null;
    
    // request-resource options
    static public $POST     = 0;
    static public $GET      = 1;
    static public $PUT      = 2;
}
