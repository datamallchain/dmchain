<?php

declare(strict_types=1);

namespace front\controller;

use PHPUnit\Framework\TestCase;
use Shaarli\Front\Controller\FrontControllerMockHelper;
use Shaarli\Front\Controller\OpenSearchController;
use Slim\Http\Request;
use Slim\Http\Response;

class OpenSearchControllerTest extends TestCase
{
    use FrontControllerMockHelper;

    /** @var OpenSearchController */
    protected $controller;

    public function setUp(): void
    {
        $this->createContainer();

        $this->controller = new OpenSearchController($this->container);
    }

    public function testOpenSearchController(): void
    {
        $this->createValidContainerMockSet();

        $request = $this->createMock(Request::class);
        $response = new Response();

        // Save RainTPL assigned variables
        $assignedVariables = [];
        $this->assignTemplateVars($assignedVariables);

        $result = $this->controller->index($request, $response);

        static::assertSame(200, $result->getStatusCode());
        static::assertStringContainsString(
            'application/opensearchdescription+xml',
            $result->getHeader('Content-Type')[0]
        );
        static::assertSame('opensearch', (string) $result->getBody());
        static::assertSame('http://shaarli', $assignedVariables['serverurl']);
    }
}
