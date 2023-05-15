<?php

declare(strict_types=1);

namespace Shaarli\Front\Controller\Admin;

use Shaarli\Config\ConfigManager;
use Shaarli\Formatter\BookmarkMarkdownFormatter;
use Shaarli\Http\HttpAccess;
use Shaarli\TestCase;
use Slim\Http\Request;
use Slim\Http\Response;

class ShaareAddControllerTest extends TestCase
{
    use FrontAdminControllerMockHelper;

    /** @var ShaareAddController */
    protected $controller;

    public function setUp(): void
    {
        $this->createContainer();

        $this->container->httpAccess = $this->createMock(HttpAccess::class);
        $this->controller = new ShaareAddController($this->container);
    }

    /**
     * Test displaying add link page
     */
    public function testAddShaare(): void
    {
        $assignedVariables = [];
        $this->assignTemplateVars($assignedVariables);

        $request = $this->createMock(Request::class);
        $response = new Response();

        $expectedTags = [
            'tag1' => 32,
            'tag2' => 24,
            'tag3' => 1,
        ];
        $this->container->bookmarkService
            ->expects(static::once())
            ->method('bookmarksCountPerTag')
            ->willReturn($expectedTags)
        ;
        $expectedTags = array_merge($expectedTags, [BookmarkMarkdownFormatter::NO_MD_TAG => 1]);

        $this->container->conf = $this->createMock(ConfigManager::class);
        $this->container->conf->method('get')->willReturnCallback(function (string $key, $default) {
            return $key === 'formatter' ? 'markdown' : $default;
        });

        $result = $this->controller->addShaare($request, $response);

        static::assertSame(200, $result->getStatusCode());
        static::assertSame('addlink', (string) $result->getBody());

        static::assertSame('Shaare a new link - Shaarli', $assignedVariables['pagetitle']);
        static::assertFalse($assignedVariables['default_private_links']);
        static::assertTrue($assignedVariables['async_metadata']);
        static::assertSame($expectedTags, $assignedVariables['tags']);
    }

    /**
     * Test displaying add link page
     */
    public function testAddShaareWithoutMd(): void
    {
        $assignedVariables = [];
        $this->assignTemplateVars($assignedVariables);

        $request = $this->createMock(Request::class);
        $response = new Response();

        $expectedTags = [
            'tag1' => 32,
            'tag2' => 24,
            'tag3' => 1,
        ];
        $this->container->bookmarkService
            ->expects(static::once())
            ->method('bookmarksCountPerTag')
            ->willReturn($expectedTags)
        ;

        $result = $this->controller->addShaare($request, $response);

        static::assertSame(200, $result->getStatusCode());
        static::assertSame('addlink', (string) $result->getBody());

        static::assertSame($expectedTags, $assignedVariables['tags']);
    }
}
