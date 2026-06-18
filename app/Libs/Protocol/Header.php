<?php

namespace App\Libs\Protocol;

/**
 * ModelTraits Header
 * @package App\Libs
 */
trait Header
{
	/**
	 * @var null
	 */
	private $Token = null;
	/**
	 * @var
	 */
	private $Headers=null;

	/**
	 *
	 */
	private function InitHeader(): void
	{
		$UserAgent = env('APP_NAME') !== 'Lumen' ? env('APP_NAME') : 'JALIO Remote Client';
		if (isset($_SERVER['SERVER_SOFTWARE'])) {
			$UserAgent .= '. ' . $_SERVER['SERVER_SOFTWARE'] . ' - PHP ' . phpversion();
		}
		$this->Headers['headers'] = ['Accept' => '*/*',
		                             'Accept-Charset' => 'utf-8',
		                             'Accept-Encoding' => '*',
		                             'Cache-Control' => 'no-cache',
		                             'Connection' => 'keep-alive',
		                             'Origin' => env('origin') ?? env('origin') ?? '',
		                             'Access-Control-Allow-Origin' => '*',
		                             'Keep-Alive' => 'timeout=5, max=1000',
		                             'Content-Type' => 'application/x-www-form-urlencoded',
		                             'User-Agent' => $UserAgent,
		                             'Authorization' => ''];
		if ($this->GetTokenFromRequestHeader()) {
			$this->Headers['headers']['Authorization'] = 'Bearer ' . $this->Token;
		}
	}

	/**
	 * @return bool
	 */
	private function GetTokenFromRequestHeader(): bool
	{
		$tokenHeader = app('request')->header('Authorization');
		if ($tokenHeader && strpos($tokenHeader, 'Bearer ') !== false) {
			$this->Token = str_replace('Bearer ', '', $tokenHeader);
			return true;
		}
		return false;
	}

	private function ClearHeader(): void
	{
		$this->Headers=null;
		$this->Token=null;
	}
}