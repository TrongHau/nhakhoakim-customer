<?php

namespace App\Http\Controllers;

use App\Libs\Formatter;
use  Laravel\Lumen\Routing\Controller as BaseController;
use OpenApi\Annotations as OA;

/**
 * @license Apache 2.0
 */
/**
 * @OA\Info(
 *     description="core API",
 *     version="1.0.0",
 *     title="core API",
 *     termsOfService="http://swagger.io/terms/",
 *     @OA\Contact(
 *         email="hoang.nguyennguyen@aipacific.vn"
 *     ),
 *     @OA\License(
 *         name="Apache 2.0",
 *         url="http://www.apache.org/licenses/LICENSE-2.0.html"
 *     )
 * )
 */
/**

 * @OA\Tag(
 *     name="Test",
 *     description="Test Operations"
 * )
 * @OA\Server(
 *     description="Train",
 *     url="http://tin.aipacific.tech/microservice/crm",
 * )
 *
 */

/**
 * @OA\SecurityScheme(
 *     type="http",
 *     securityScheme="bearerAuth",
 *     scheme="bearer",
 *     bearerFormat="JWT"
 * )
 */
class Controller extends BaseController
{
    use Formatter;
	public function __construct()
	{
		$this->initLayout();
	}
}
