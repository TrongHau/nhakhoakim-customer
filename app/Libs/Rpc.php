<?php
namespace Remote;

use \Exception AS Exception;
use Illuminate\Support\Facades\Log;
use Remote\Protocol\Header;
use Remote\Protocol\Params;

/**
 * 
 * @author giangdn
 *
 */
class Rpc
{
    /**
     * @var string
     */
    protected $targetUri    = '';
    
    /**
     * @var callable
     */
    protected $callback     = null;
    
    /**
     * @var string
     */
    protected $returnUri    = '';
    
    /**
     * @var Params
     */
    protected $Params       = null;
    
    /**
     * @var Header
     */
    protected $Header       = null;
    
    /**
     * @param string $targetUri
     * @param callable $callback
     * @param string $returnUri
     */
    public function __construct($targetUri, $callback = null, $returnUri = '')
    {
        if (self::_validUrl($targetUri)) {
            $this->targetUri = $targetUri;
            
            if (is_callable($callback)) {
                $this->callback = $callback;
            } else {
                Log::error('Invalid callback argument for RPC of target-uri: ' . $targetUri);
            }
            
            if ($returnUri) {
                if (self::_validUrl($returnUri)) {
                    $this->returnUri = $returnUri;
                } else {
                    
                }
            }
        } else {
            Log::error('Invalid RPC target-uri: ' . $targetUri);
        }
    }
    
    /**
     * @param string $str
     * @return \Remote\Rpc
     */
    static public function init($str)
    {
        return new Rpc(' ');
    }
    
    /**
     * @return string json string
     */
    public function serialize()
    {
        
    }
    
    /**
     * @param Header $Header
     * @param Params $Params
     */
    public function execute($Header = null, $Params = null)
    {
        try {
            
        } catch (Exception $e) {
            Log::error('RPC execute ' . $this->targetUri . ' fail: ' . $e->getMessage());
        }
    }
    
    /**
     * @param Params $Params
     * @return \Remote\Rpc
     */
    public function setParams($Params)
    {
        $this->Params = $Params;
        return $this;
    }
    
    /**
     * @param Header $Header
     * @return \Remote\Rpc
     */
    public function setHeader($Header)
    {
        $this->Header = $Header;
        return $this;
    }
    
    /**
     * @param string $url
     * @return boolean
     */
    public static function _validUrl($url)
    {
        $regex = "((https?|ftp)\:\/\/)?"; // SCHEME
        $regex .= "([a-z0-9+!*(),;?&=\$_.-]+(\:[a-z0-9+!*(),;?&=\$_.-]+)?@)?"; // User and Pass
        $regex .= "([a-z0-9-.]*)\.([a-z]{2,3})"; // Host or IP
        $regex .= "(\:[0-9]{2,5})?"; // Port
        $regex .= "(\/([a-z0-9+\$_-]\.?)+)*\/?"; // Path
        $regex .= "(\?[a-z+&\$_.-][a-z0-9;:@&%=+\/\$_.-]*)?"; // GET Query 
        
        return (bool) preg_match("/^$regex$/i", $url);
    }
}