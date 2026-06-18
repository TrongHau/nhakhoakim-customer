<?php

namespace App\Libs\Protocol;
use \Exception;
trait Params
{
	private $Parameters = array();
	// pass param to remote server by using
	static private $FORM_PARAMS = 'form_params';
	static private $MULTIPART = 'multipart';
	static private $QUERY = 'query';
	/**
	 * @var array
	 */
	private $_callName = array();

	/**
	 * @var array
	 */
	private $_callArguments = array();
	/**
	 * @var array
	 */
	protected $FormParams = [];

	/**
	 * @var array
	 */
	protected $Multipart = [];

	/**
	 * @var array
	 */
	protected $Query = [];
	/**
	 * @var array
	 */
	protected $Selector = [];

    /**
	 * @var string
	 */
	protected $PermissionCode = null;

	/**
	 * @var int
	 */
	protected $CurrentWorkProfilePositionId = 0;
	/**
	 * add parameters with generated from anonymous function
	 */
	public function curlSerialize(): void
	{
		if (is_array($this->Query) && count($this->Query)) {
			$this->Parameters[self::$QUERY] = [];
			foreach ($this->Query AS $key => $val) {
				$this->Parameters[self::$QUERY][$key] = $val;
			}
			$this->Parameters[self::$QUERY]['MainPage'] = $this->MainPage;
			$this->Parameters[self::$QUERY]['PermissionCode'] = $this->PermissionCode;
			$this->Parameters[self::$QUERY]['CurrentWorkProfilePositionId'] = $this->CurrentWorkProfilePositionId;
		}

		//form-data
		if (is_array($this->FormParams) && count($this->FormParams)) {
			$this->Parameters[self::$FORM_PARAMS] = [];
			foreach ($this->FormParams AS $key => $val) {
				$this->Parameters[self::$FORM_PARAMS][$key] = $val;
			}
			$this->Parameters[self::$FORM_PARAMS]['MainPage'] = $this->MainPage;
			$this->Parameters[self::$FORM_PARAMS]['PermissionCode'] = $this->PermissionCode;
			$this->Parameters[self::$FORM_PARAMS]['CurrentWorkProfilePositionId'] = $this->CurrentWorkProfilePositionId;
		}

		//multipart items
		if (is_array($this->Multipart) && count($this->Multipart)) {
			$this->Parameters[self::$MULTIPART] = [];
			foreach ($this->Multipart AS $val) {
				$this->Parameters[self::$MULTIPART][] = $val;
			}
			$this->Parameters[self::$MULTIPART][] = [
				'name' => 'PermissionCode',
				'contents' => $this->PermissionCode
			];
			$this->Parameters[self::$MULTIPART][] = [
				'name' => 'CurrentWorkProfilePositionId',
				'contents' => $this->CurrentWorkProfilePositionId
			];
		}
	}

	/**
	 * @description this function will get anonymous functions's name, data
	 *              for example $app->xxx('some_data')
	 *              this function will get name is xxx and arguments is some_data
	 *
	 * @param $name
	 * @param $arguments
	 * @return Params
	 */
	public function __call($name, $arguments)
	{
		$this->_callArguments[] = $arguments;
		$this->_callName[] = $name;
		return $this;
	}

	protected function _parseChainingToRequest(): void
	{
		for ($i = 0, $n = count($this->_callName); $i < $n; $i++) {
			$call = strtolower($this->_callName[$i]);
			switch ($call) {
				case 'from':
				case 'to':
					if (isset($this->_callArguments[$i][0])) {
						$this->RemoteUri = $this->_callArguments[$i][0];
					} else {
						app('log')->error('Empty REMOTE_URI to perform request to');
						throw new Exception('Empty REMOTE_URI to perform request to');
					}
					break;
				case 'where':
					$this->addMultiple($this->_callArguments[$i]);
					break;
				case 'post':
					$this->Method = self::$POST;
					$this->addMultiple($this->_callArguments[$i]);
					break;
				case 'put':
					$this->Method = self::$PUT;
					$this->addMultiple($this->_callArguments[$i]);
					break;
				case 'get':
					$this->Method = self::$GET;
					$this->Selector = $this->_callArguments[$i];
					break;
				case 'request':
					$this->Method = self::$POST;
					$this->Selector = $this->_callArguments[$i];
					break;
				default:
					app('log')->error('Remote unsuport method: ' . $call);
			}
		}
	}

	/**
	 * @param        $params
	 * @param string $type
	 */
	public function addMultiple($params, $type = 'form_params'): void
	{
		if (is_array($params) && count($params)) {
			foreach ($params AS $param) {
				$this->addMultipartFormData($param, $type);
			}
		}
	}

	/**
	 * @description
	 *
	 * @param        $param
	 * @param string $type
	 */
	private function addMultipartFormData($param, $type = 'form_params'): void
	{
		switch ($type) {
			case self::$FORM_PARAMS:
			case self::$QUERY:
				$this->addFormQuery($param, $type);
				break;
			case self::$MULTIPART:
				$this->Multipart[] = $param;
		}
	}

	/**
	 * @param $param
	 * @param $type
	 */
	private function addFormQuery($param, $type): void
	{
		if ($type === self::$FORM_PARAMS) {
			$target = &$this->FormParams;
		} elseif ($type === self::$QUERY) {
			$target = &$this->Query;
		}

		if (isset($target) && is_array($param) && count($param)) {
			foreach ($param AS $key => $val) {
				$target[$key] = $val;
			}
		}
	}
	public function loadVar($dft = null)
	{
		if (count($this->Selector) && $this->Response) {
			$tem = $this->Selector;
			$selector = array_shift($tem);
			if (($var = $this->_trail($selector)) !== null)
				return $var;

		}

		return $dft;
	}
	public function ClearParameters(): void
	{
		$this->Parameters = [];
		$this->Selector=[];
		$this->_callArguments=[];
		$this->_callName=[];
		$this->FormParams = $this->Query = $this->Multipart = [];
	}
}