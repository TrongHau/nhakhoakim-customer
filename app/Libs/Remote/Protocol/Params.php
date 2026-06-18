<?php
namespace App\Libs\Remote\Protocol;

use \Exception AS Exception;
/**
 * 
 * @author giangdn
 *
 */
class Params
{
    /**
     * @var array
     */
    protected $FormParams   = [];
    
    /**
     * @var array
     */
    protected $Multipart    = [];
    
    /**
     * @var array
     */
    protected $Query        = [];

    /**
	 * @var string
	 */
	protected $PermissionCode = null;

	/**
	 * @var int
	 */
	protected $CurrentWorkProfilePositionId = 0;
    
    /**
     * @param array $param
     * @param string $type
     * @return Params
     */
    public function add($param, $type = 'form_params')
    {
        switch ($type) {
            case self::$FORM_PARAMS:
            case self::$QUERY:
                $this->_add($param, $type);
                break;
            case self::$MULTIPART:
                $this->Multipart[] = $param;
        }
        
        return $this;
    }
    
    /**
     * @param array $params
     * @param string $type
     * @return Params
     */
    public function addMultiple($params, $type = 'form_params')
    {
        if (is_array($params) && count($params)) {
            foreach ($params AS $param) $this->add($param, $type);
        }
        
        return $this;
    }
    
    /**
     * chỉ sử dụng với form-data và query, không hoạt động với multipart
     * @param string $key
     * @param mixed array|scala $value
     * @param string $type
     * @return Params
     */
    public function set($key, $value, $type = 'form_params')
    {
        switch ($type) {
            case self::$FORM_PARAMS:
                $this->FormParams[$key] = $value;
                break;
            case self::$QUERY:
                $this->Query[$key] = $value;
        }
        
        return $this;
    }
    
    /**
     * @param string $key
     * @param string $type
     * @return Params
     */
    public function unset($key, $type = 'form_params')
    {
        switch ($type) {
            case self::$FORM_PARAMS:
                if (isset($this->FormParams[$key])) {
                    unset($this->FormParams[$key]);
                }
                break;
            case self::$QUERY:
                if (isset($this->Query[$key])) {
                    unset($this->Query[$key]);
                }
        }
        
        return $this;
    }
    
    public function getValue($key, $dft = '', $type = 'form_params')
    {
        switch ($type) {
            case self::$FORM_PARAMS:
                return $this->_get($key, $dft, $this->FormParams);
            case self::$QUERY:
                return $this->_get($key, $dft, $this->Query);
            case self::$MULTIPART:
                return $this->_getFromMultipart($key, $dft);
        }
    }
    
    /**
     * @param string $type
     * @return array
     */
    public function getArray($type)
    {
        switch ($type) {
            case self::$FORM_PARAMS:    return $this->FormParams;
            case self::$MULTIPART:      return $this->Multipart;
            case self::$QUERY:          return $this->Query;
        }
        
        return [];
    }
    
    /**
     * @throws Exception
     * @return Params
     */
    public function bindFromApp()
    {
        
        return $this;
    }
    
    /**
     * @return array[]|array|string
     */
    public function curlSerialize()
    {
        $params = [];
        
        // query
        if (is_array($this->Query) && count($this->Query)) {
            $params[self::$QUERY] = [];
            foreach ($this->Query AS $key => $val) {
                $params[self::$QUERY][$key] = $val;
            }
            $params[self::$QUERY]['PermissionCode'] = $this->getPermissionCode();
			$params[self::$QUERY]['CurrentWorkProfilePositionId'] = $this->getCurrentWorkProfilePositionId();
        }
        //multipart items
        if (is_array($this->Multipart) && count($this->Multipart)) {
            $params[self::$MULTIPART] = [];
            foreach ($this->Multipart AS $val) {
                $params[self::$MULTIPART][] = $val;
            }
            $params[self::$MULTIPART][] = [
				'name' => 'PermissionCode',
				'contents' => $this->getPermissionCode()
			];
			$params[self::$MULTIPART][] = [
				'name' => 'CurrentWorkProfilePositionId',
				'contents' => $this->getCurrentWorkProfilePositionId()
			];

            return $params;
        }
        
        //form-data
        if (is_array($this->FormParams) && count($this->FormParams)) {
            $params[self::$FORM_PARAMS] = [];
            foreach ($this->FormParams AS $key => $val) {
                $params[self::$FORM_PARAMS][$key] = $val;
            }
            $params[self::$FORM_PARAMS]['PermissionCode'] = $this->getPermissionCode();
			$params[self::$FORM_PARAMS]['CurrentWorkProfilePositionId'] = $this->getCurrentWorkProfilePositionId();

            return $params;
        }
        
        

        return $params;
    }
    
    /**
     * @param string $key
     * @param string $dft
     * @param array $source
     * @return mixed
     */
    protected function _get($key, $dft = '', $source)
    {
        if (isset($source[$key])) return $source[$key];
        else return $dft;
    }
    
    /**
     * @param array $param
     * @param string $type
     */
    protected function _add($param, $type) {
        if ($type == self::$FORM_PARAMS) {
            $target = &$this->FormParams;
        } elseif ($type == self::$QUERY) {
            $target = &$this->Query;    
        }
        
        if (isset($target) && is_array($param) && count($param)) {
            foreach ($param AS $key => $val) {
                $target[$key] = $val;
            }
        }
    }
    
    /**
     * @param string $key
     * @param array $dft
     * @return array
     */
    protected function _getFromMultipart($key, $dft)
    {
        if (count($this->Multipart)) {
            foreach ($this->Multipart AS $item) {
                if (isset($item->name) && $item->name == $key) {
                    return $item;
                } else return $dft;
            }
        }
    }
    
    // pass param to remote server by using
    static public $FORM_PARAMS     = 'form_params';
    static public $MULTIPART       = 'multipart';
    static public $QUERY           = 'query';

	/**
	 * Get the value of PermissionCode
	 *
	 * @return  string
	 */ 
	public function getPermissionCode()
	{
        if (empty($this->PermissionCode)) {
            $this->setPermissionCode();
        }
		return $this->PermissionCode;
	}

	/**
	 * Get the value of CurrentWorkProfilePositionId
	 *
	 * @return  int
	 */ 
	public function getCurrentWorkProfilePositionId()
	{
        if (empty($this->CurrentWorkProfilePositionId)) {
            $this->setCurrentWorkProfilePositionId();
        }
		return $this->CurrentWorkProfilePositionId;
	}

	/**
	 * Set the value of PermissionCode
	 *
	 * @param  string  $PermissionCode
	 *
	 * @return  self
	 */ 
	public function setPermissionCode()
	{
		$this->PermissionCode = app('request')->PermissionCode ?? '';

		return $this;
	}

	/**
	 * Set the value of CurrentWorkProfilePositionId
	 *
	 * @param  int  $CurrentWorkProfilePositionId
	 *
	 * @return  self
	 */ 
	public function setCurrentWorkProfilePositionId()
	{
		$this->CurrentWorkProfilePositionId = app('request')->CurrentWorkProfilePositionId ?? 0;

		return $this;
	}
}