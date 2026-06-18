<?php

namespace App\Console\Commands;
use Illuminate\Console\Command;
use OpenApi;
class ApiGenerateCommand extends Command
{
	protected $name = 'api:generate';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Create a new api document';

	public function __construct()
	{
		parent::__construct();
	}
	/**
	 * Execute the console command.
	 *
	 * @return mixed
	 */
	public function handle()
	{
		$path= __DIR__.'../../';
		$openapi=OpenApi::scan($path);
		header('Content-Type: application/x-yaml');
		echo $openapi->toYaml();
	}
}