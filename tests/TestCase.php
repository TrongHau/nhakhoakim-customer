<?php

abstract class TestCase extends Laravel\Lumen\Testing\TestCase
{

	protected $baseUrl;
    /**
     * Creates the application.
     *
     * @return \Laravel\Lumen\Application
     */
//	protected $baseUrl = 'http://crm-service.dv:90';
	//protected $baseUrl = 'http://localhost';
	protected function setUp(): void
	{
		parent::setUp(); //
		$this->baseUrl= env('APP_URL');
	}

	public function createApplication()
    {
        return require __DIR__.'/../bootstrap/app.php';
    }

}
