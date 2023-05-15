<?php

declare(strict_types=1);

namespace Shaarli\Legacy;

use PHPUnit\Framework\TestCase;
use Shaarli\Front\Controller\Visitor\FrontControllerMockHelper;
use Slim\Http\Request;
use Slim\Http\Response;

class LegacyControllerTest extends TestCase
{
    use FrontControllerMockHelper;

    /** @var LegacyController */
    protected $controller;

    public function setUp(): void
    {
        $this->createContainer();

        $this->controller = new LegacyController($this->container);
    }

    /**
     * @dataProvider getProcessProvider
     */
    public function testProcess(string $legacyRoute, array $queryParameters, string $slimRoute, bool $isLoggedIn): void
    {
        $request = $this->createMock(Request::class);
        $request->method('getQueryParams')->willReturn($queryParameters);
        $request
            ->method('getParam')
            ->willReturnCallback(function (string $key) use ($queryParameters): ?string {
                return $queryParameters[$key] ?? null;
            })
        ;
        $response = new Response();

        $this->container->loginManager->method('isLoggedIn')->willReturn($isLoggedIn);

        $result = $this->controller->process($request, $response, $legacyRoute);

        static::assertSame('/subfolder' . $slimRoute, $result->getHeader('location')[0]);
    }

    public function testProcessNotFound(): void
    {
        $request = $this->createMock(Request::class);
        $response = new Response();

        $this->expectException(UnknowLegacyRouteException::class);

        $this->controller->process($request, $response, 'nope');
    }

    /**
     * @return array[] Parameters:
     *                   - string legacyRoute
     *                   - array  queryParameters
     *                   - string slimRoute
     *                   - bool   isLoggedIn
     */
    public function getProcessProvider(): array
    {
        return [
            ['post', [], '/admin/shaare', true],
            ['post', [], '/login', false],
            ['post', ['title' => 'test'], '/admin/shaare?title=test', true],
            ['post', ['title' => 'test'], '/login?title=test', false],
            ['addlink', [], '/admin/add-shaare', true],
            ['addlink', [], '/login', false],
            ['login', [], '/login', true],
            ['login', [], '/login', false],
            ['logout', [], '/logout', true],
            ['logout', [], '/logout', false],
            ['picwall', [], '/picture-wall', false],
            ['picwall', [], '/picture-wall', true],
            ['tagcloud', [], '/tags/cloud', false],
            ['tagcloud', [], '/tags/cloud', true],
            ['taglist', [], '/tags/list', false],
            ['taglist', [], '/tags/list', true],
            ['daily', [], '/daily', false],
            ['daily', [], '/daily', true],
            ['daily', ['day' => '123456789', 'discard' => '1'], '/daily?day=123456789', false],
            ['rss', [], '/feed/rss', false],
            ['rss', [], '/feed/rss', true],
            ['rss', ['search' => 'filter123', 'other' => 'param'], '/feed/rss?search=filter123&other=param', false],
            ['atom', [], '/feed/atom', false],
            ['atom', [], '/feed/atom', true],
            ['atom', ['search' => 'filter123', 'other' => 'param'], '/feed/atom?search=filter123&other=param', false],
            ['opensearch', [], '/open-search', false],
            ['opensearch', [], '/open-search', true],
            ['dailyrss', [], '/daily-rss', false],
            ['dailyrss', [], '/daily-rss', true],
        ];
    }
}
