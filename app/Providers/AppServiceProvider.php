<?php

namespace App\Providers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
	/**
	 * Register any application services.
	 *
	 */
	public function register()
	{
		$request = app('request');
		if ((isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') || $request->getMethod() === 'OPTIONS') {
			return response()->json([], 200, [
				'Access-Control-Allow-Origin'  => '*',
				'Access-Control-Allow-Headers' => 'Origin, X-Requested-With, Content-Type, Accept',
			]);
		}
	}

	/**
	 *  Handle validate translate to Vietnamese
	 */
	public function boot()
	{
		app('translator')->setLocale('vi');
		/**
		 * [$query->sql,
		 * $query->bindings,
		 * $query->time]
		 */
		if (env('APP_DEBUG')) {
			DB::listen(function ($query) {
				$request = app('request');
				Log::channel('query')->info('QueryLog', [
					'requestUri' => $request->path(),
					'Query'      => $query->sql,
					'Binding'    => $query->bindings,
					'Time'       => $query->time,
				]);
			});
		}

		Validator::extend('custom_boolean', function ($attribute, $value, $parameters) {
			return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) !== null;
		});
	}
}
