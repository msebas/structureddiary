<?php

declare(strict_types=1);

namespace OCA\StructuredDiary\Controller;

use OCA\StructuredDiary\AppInfo\Application;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\FrontpageRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\OpenAPI;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IRequest;
use OCP\IConfig;

/**
 * @psalm-suppress UnusedClass
 */
class PageController extends Controller {

    public function __construct(
        string                                         $appName,
        IRequest                                       $request,
        private readonly IConfig                       $config,
    )
    {
        parent::__construct($appName, $request);
    }

	#[NoCSRFRequired]
	#[NoAdminRequired]
	#[OpenAPI(OpenAPI::SCOPE_IGNORE)]
	#[FrontpageRoute(verb: 'GET', url: '/')]
	public function index(): TemplateResponse {
        $response = new TemplateResponse(Application::APP_ID, 'index');
        $dev_server = in_array('structureddiary', $this->config->getSystemValue('dev_server', []), true);
        if (!$dev_server){
            $dev_server = $this->config->getSystemValue('dev_server', [])['structureddiary'];
        }
        if ($dev_server) {
            $response->addHeader("Access-Control-Allow-Origin","*");
            $response->setParams([
                'viteDev' => true,
                'viteUrl' => $dev_server === true ? 'http://localhost:5174' : $dev_server
            ]);
        }

        return $response;
	}
}
