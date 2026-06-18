<?php

namespace App\Console\Commands;
use Illuminate\Console\Command;
class KeyGenerateCommand extends Command
{
	protected $name = 'key:generate';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Create a new app key';

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
		$permitted_chars = '0123456789abcdefghijklmnopqrstuvwxyzAWERTYJGFXDGKJHKJHDRWEYOPOTIYEYS';
		$key=substr(str_shuffle($permitted_chars), 0, 32);
		$path = base_path('.env');
		if (file_exists($path)) {
			file_put_contents($path, str_replace(
				'APP_KEY='.env('APP_KEY'), 'APP_KEY='.$key, file_get_contents($path)
			));
		}
	}
}