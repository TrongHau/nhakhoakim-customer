<?php

namespace App\Libs;

use App\Libs\Protocol\Header;
use App\Libs\Protocol\Params;
use Exception;
use GuzzleHttp\Client as GuzzClient;

/**
 * Class ApiProcess
 * @package App\Libs
 */
class ApiProcess
{
	use Formatter;
	use Header;
	use Params;
	/**
	 * @var string
	 */
	protected $RemoteUri = null;
	/**
	 * @var int
	 */
	protected $Method = 0;
	/**
	 * @var string
	 */
	protected $Response;

	/**
	 * @var
	 */
	protected $Permission;
	// request-resource options
	/**
	 * @var int
	 */
	static private $POST = 0;
	/**
	 * @var int
	 */
	static private $GET = 1;
	/**
	 * @var int
	 */
	static private $PUT = 2;

	/**
	 * @var int
	 */
	protected $ResponseCode = 0;
	protected $MainPage='';
	/**
	 * ApiProcess constructor.
	 */
	public function __construct()
	{
		$this->InitHeader();
		$this->MainPage = app('request')->MainPage ?? '';
		$this->PermissionCode = app('request')->PermissionCode ?? '';
		$this->CurrentWorkProfilePositionId = app('request')->CurrentWorkProfilePositionId ?? 0;
	}

	/**
	 * @param $selector
	 *
	 * @return mixed|string|null
	 */
	protected function _trail($selector)
	{
		try {
			if ($this->Response && $Response = json_decode($this->Response)) {
				//special selector: select '*'
				if (trim($selector) === '*') {
					return $this->Response;
				}

				//trailing selector
				$sltChaining = explode('.', $selector);

				foreach ($sltChaining AS $slt) {
					$slt = trim($slt);

					// attribute select or not, for example views[name=grid]
					$pattern = '/.*\[.*=.*\]/';
					$matches = [];
					if (preg_match($pattern, $slt, $matches) > 0) {
						foreach ($matches AS $match) {
							$temp = explode('[', $match);
							$slt = $temp[0];
							$expression = explode('=', str_replace(']', '', $temp[1]));

							if (isset($Response->$slt, $expression[0], $expression[1]) && $Response->$slt) {
								$Response = $Response->$slt;
								if (count($Response)) {
									$isFound = false;
									foreach ($Response AS $el) {
										/*
										 * $con = $expression[0];
//										 * $val = $expression[1];
										 * this line under equal list($con,val) = $expression
										 */
										[$con,
										 $val] = $expression;
										if (isset($el->$con) && $el->$con === $val) {
											$Response = $el;
											$isFound = true;
											break;
										}
									}
									if (!$isFound) {
										$Response = null;
									}
								}
							} else {
								$Response = null;
							}
						}
					} elseif (isset($Response->$slt)) {
						$Response = $Response->$slt;
					}

				}

				return $Response;
			}
		} catch (\Exception $e) {
			app('log')->error('Remote trail response fail into: ' . $selector);
		}

		return null;
	}

	/**
	 * @param $string
	 *
	 * @return bool
	 */
	private function isJson($string)
	{
		json_decode($string, false);
		return (json_last_error() === JSON_ERROR_NONE);
	}

	/**
	 * @throws \GuzzleHttp\Exception\GuzzleException
	 */
	public function execute(): void
	{
		try {
			$this->_parseChainingToRequest();
			$this->curlSerialize();
			$options = array_merge($this->Headers, $this->Parameters);
			$methods = array(self::$POST => 'POST',
			                 self::$GET => 'GET',
			                 self::$PUT => 'PUT');
			$guzzClient = new GuzzClient();
			$res = $guzzClient->request($methods[$this->Method], $this->RemoteUri, $options);
			$this->ResponseCode = $res->getStatusCode();
			$this->Response = $res->getBody()->getContents();
		} catch (Exception $e) {

		}
	}

	/**
	 * @param null $dft
	 *
	 * @return mixed|string|null
	 */
	public function loadVar($dft = null)
	{
		if ($this->Response && count($this->Selector)) {
			$tem = $this->Selector;
			$selector = array_shift($tem);
			if (($var = $this->_trail($selector)) !== null){
				$this->ClearApiVariable();
				return $var;
			}
		}
		$this->ClearApiVariable();
		return $dft;
	}

	/**
	 * @return mixed|null
	 */
	public function getConfigOfCurPage()
	{
		if (isset($this->Permission->MenuItem->MenuItemId)) {
			if ($this->MainPage) {
				$key = base64_encode(strtolower(sprintf('_mod=%s _view=%s', $this->controllerName, $this->actionName)));
				if (isset($this->MenuItem->MenuItem->Config->$key)) {
					if ($this->isJson($this->Permission->MenuItem->Config->$key))
					{
						return json_decode($this->Permission->MenuItem->Config->$key,false);
					}
					return $this->Permission->MenuItem->Config->$key;
				}
			}
			if ($this->isJson($this->Permission->MenuItem->Config)){
				return json_decode($this->Permission->MenuItem->Config,false);
			}
			return $this->Permission->MenuItem->Config;
		}
		return null;
	}
	/**
	 * @return NULL | array
	 */
	public function getResponseMessages()
	{
		if ($this->Response && $objResponse = json_decode($this->Response,false)) {
			return isset($objResponse->messages) ?? $objResponse->messages ;
		}
	}

	/**
	 *
	 */
	public function getResponse()
	{
		return $this->Response;
	}
	/**
	 *
	 */
	private function ClearResponse(): void
	{
		$this->RemoteUri = null;
		$this->Method = 0;
		/*
		 * not sure
		 */
//		$this->Response = null;
	}

	/**
	 *
	 */
	private function ClearApiVariable(): void
	{
		$this->ClearResponse();
		$this->ClearParameters();
		$this->ClearHeader();
	}

}