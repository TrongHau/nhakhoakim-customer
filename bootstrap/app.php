<?php

require_once __DIR__ . '/../vendor/autoload.php';

(new Laravel\Lumen\Bootstrap\LoadEnvironmentVariables(dirname(__DIR__)))->bootstrap();

/*
|--------------------------------------------------------------------------
| Create The Application
|--------------------------------------------------------------------------
|
| Here we will load the environment and create the application instance
| that serves as the central piece of this framework. We'll use this
| application as an "IoC" container and router for this framework.
|
*/

$app = new Laravel\Lumen\Application(dirname(__DIR__));

$app->withFacades();

$app->withEloquent();

/*
|--------------------------------------------------------------------------
| Register Container Bindings
|--------------------------------------------------------------------------
|
| Now we will register a few bindings in the service container. We will
| register the exception handler and the console kernel. You may add
| your own bindings here if you like or you can make another file.
|
*/

$app->singleton(Illuminate\Contracts\Debug\ExceptionHandler::class, App\Exceptions\Handler::class);

$app->singleton(Illuminate\Contracts\Console\Kernel::class, App\Console\Kernel::class);
/*
 * Passport
 * */
$app->bind(\Illuminate\Contracts\Routing\UrlGenerator::class, function ($app) {
	return new \Laravel\Lumen\Routing\UrlGenerator($app);
});

/*
|--------------------------------------------------------------------------
| Register Middleware
|--------------------------------------------------------------------------
|
| Next, we will register the middleware with the application. These can
| be global middleware that run before and after each request into a
| route or middleware that'll be assigned to some specific routes.
|
*/

// $app->middleware([
//     App\Http\Middleware\ExampleMiddleware::class
// ]);

 $app->routeMiddleware([
     'auth' => App\Http\Middleware\Authenticate::class,
     'client' => \Laravel\Passport\Http\Middleware\CheckClientCredentials::class,
 ]);
//$app->routeMiddleware(['JWTAuth' => App\Http\Middleware\JWTAuthenticate::class]);
/*
|--------------------------------------------------------------------------
| Register Service Providers
|--------------------------------------------------------------------------
|
| Here we will register all of the application's service providers which
| are used to bind services into the container. Service providers are
| totally optional, so you are not required to uncomment this line.
|
*/
$app->register(App\Providers\AppServiceProvider::class);
$app->register(App\Providers\AuthServiceProvider::class);
$app->register(Laravel\Passport\PassportServiceProvider::class);
$app->register(Dusterio\LumenPassport\PassportServiceProvider::class);
$app->register(Maatwebsite\Excel\ExcelServiceProvider::class);
$app->register(Barryvdh\DomPDF\ServiceProvider::class);
$app->register(Maatwebsite\Excel\ExcelServiceProvider::class);
/*
 / redis package
 */
$app->register(Illuminate\Redis\RedisServiceProvider::class);

if(env('APP_ENV')==='local' || env('APP_ENV')==='testing'){
	$app->register(Laravel\Tinker\TinkerServiceProvider::class);
	$app->register(Laracademy\Generators\GeneratorsServiceProvider::class);
}
if(env('APP_DEBUG')){
	$app->register(\Rap2hpoutre\LaravelLogViewer\LaravelLogViewerServiceProvider::class);
}

/**
 * auto generate model
 *
 */

// $app->register(App\Providers\EventServiceProvider::class);
if ($app->environment() !== 'production') {
	$app->register(\Barryvdh\LaravelIdeHelper\IdeHelperServiceProvider::class);
}

/*
 * Register Passport route prefix
 * */

//Dusterio\LumenPassport\LumenPassport::routes($app->router, ['prefix' => 'oauth'] );
/*
|--------------------------------------------------------------------------
| Load The Application Routes
|--------------------------------------------------------------------------
|
| Next we will include the routes file so that they can all be added to
| the application. This will provide all of the URLs the application
| can respond to, as well as the controllers that may handle them.
|
*/

$app->router->group(['namespace' => 'App\Http\Controllers',], function ($router) {
	require __DIR__ . '/../routes/web.php';
});

$app->configure('constants');

return $app;
