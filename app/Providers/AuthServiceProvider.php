<?php

namespace App\Providers;

use App\Libs\ApiToken;
use App\Libs\Formatter;
use App\Repositories\ApiTokenRepository;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Auth\GenericUser;

class AuthServiceProvider extends ServiceProvider
{
	use Formatter;

	/**
	 * Register any application services.
	 *
	 * @return void
	 */
	public function register()
	{

	}

	/**
	 * Boot the authentication services for the application.
	 *
	 * @return void
	 */
	public function boot()
	{
		// Here you may define how you wish users to be authenticated for your Lumen
		// application. The callback which receives the incoming request instance
		// should return either a User instance or null. You're free to obtain
		// the User instance via an API token or any other method necessary.

		$this->app['auth']->viaRequest('api', static function ($request) {
			$auth = new ApiToken();
			$auth = $auth->getResult($request);
			if ($auth && isset($auth->user)) {
				/**
				 * choose once to get data you need
				 */
				//	return new GenericUser($auth);
				$user = $auth->user ?? null;
				$permissions = $auth->permission_actions ?? [];
				
				// Optional: Gate::before cho quyền bot
				Gate::before(function ($user, $ability) {
					if (isset($user->IsBot) && $user->IsBot) {
						return true;
					}
				});
				//Set permission to user
				if ($permissions && !empty($permissions) && is_array($permissions)) {
					foreach ($permissions as $permission) {
						if (!$permission || empty($permission)) {
							continue;
						}
						if (isset($permission->AllowActions) && !empty($permission->AllowActions)) {
							foreach ($permission->AllowActions as $allowAction) {
								Gate::define(($permission->PermissionCode ?? 'empty') .':'.($allowAction ?? 'View'), function ($user){
									return true;
								});
								Gate::define(($permission->PermissionCode ?? 'empty') .'.'.($allowAction ?? 'View'), function ($user){
									return true;
								});
							}
						} else {
							Gate::define(($permission->PermissionCode ?? 'empty') . ':' . 'View', function ($user){
								return true;
							});
							Gate::define(($permission->PermissionCode ?? 'empty') . '.' . 'View', function ($user){
								return true;
							});
						}
					}
				}
				
				return (array) $user;
			}

			return null;
		});

	}
}
