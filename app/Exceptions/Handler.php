<?php

namespace App\Exceptions;

use App\Libs\Formatter;
use Exception;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Laravel\Lumen\Exceptions\Handler as ExceptionHandler;
use Predis\Connection\ConnectionException as RedisConnectError;
use Symfony\Component\HttpKernel\Exception\HttpException;

class Handler extends ExceptionHandler
{
	use Formatter;
	/**
	 * A list of the exception types that should not be reported.
	 *
	 * @var array
	 */
	protected $dontReport = [AuthorizationException::class];

	/**
	 * Report or log an exception.
	 *
	 * This is a great spot to send exceptions to Sentry, Bugsnag, etc.
	 *
	 * @param \Exception $exception
	 *
	 * @return void
	 */
	public function report(Exception $exception)
	{
		parent::report($exception);
	}

	/**
	 * Render an exception into an HTTP response.
	 *
	 * @param \Illuminate\Http\Request $request
	 * @param \Exception               $exception
	 *
	 * @return \Illuminate\Http\Response|\Illuminate\Http\JsonResponse
	 */
	public function render($request, Exception $exception)
	{

		$this->initLayout();
		if ($exception instanceof HttpException && $exception->getStatusCode() === 404) {
			$this->addMessage('Your data/route not found', 404,1);
			return $this->json(false, 'bool', 404);
		}
		if ($exception instanceof HttpException && $exception->getStatusCode() === 401) {
			if($exception->getMessage()){
				$this->addMessage($exception->getMessage());
			}else{
				$this->addMessage('Not Authorize!!', 401,1);
			}
			return $this->json(false, 'bool', 401);
		}
		if ($exception instanceof HttpException && $exception->getStatusCode() === 400) {
			$this->addMessage($exception->getMessage(),400,1);
			return $this->json(false, 'bool', 200);
		}
		if ($exception instanceof HttpException && $exception->getStatusCode() === 405) {
			$this->addMessage('Wrong method',405,1);
			return $this->json(false, 'bool', 200);
		}
		if ($exception instanceof HttpException && $exception->getStatusCode() === 403) {
			$this->addMessage('You dont have permission!',403,1);
			return $this->json(false, 'bool', 200);
		}
		if ($exception instanceof HttpException && $exception->getStatusCode() === 424) {
			$this->addMessage($exception->getMessage(),424,1);
			return $this->json(false, 'bool', 200);
		}
        if (env('APP_ENV') !== 'local' && $exception instanceof \ErrorException) {
			$this->addMessage('System Error',500,1);
			return $this->json(false, 'bool', 200);
		}
        if($exception instanceof RedisConnectError)
        {
            $this->addMessage('Redis server is down or not found',503,1);
            return $this->json(false, 'bool', 503);
        }
        if($exception instanceof HttpException && $exception->getStatusCode() === 423){
	        $this->addMessage('Your account is locked by Administrator!',423,1);
	        return $this->json(false, 'bool', 423);
        }
		return parent::render($request, $exception);
	}
}
