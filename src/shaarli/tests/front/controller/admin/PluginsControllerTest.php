<?php

declare(strict_types=1);

namespace Shaarli\Front\Controller\Admin;

use Shaarli\Config\ConfigManager;
use Shaarli\Front\Exception\WrongTokenException;
use Shaarli\Plugin\PluginManager;
use Shaarli\Security\SessionManager;
use Shaarli\TestCase;
use Slim\Http\Request;
use Slim\Http\Response;

class PluginsControllerTest extends TestCase
{
    use FrontAdminControllerMockHelper;

    const PLUGIN_NAMES = ['plugin1', 'plugin2', 'plugin3', 'plugin4'];

    /** @var PluginsController */
    protected $controller;

    public function setUp(): void
    {
        $this->createContainer();

        $this->controller = new PluginsController($this->container);

        mkdir($path = __DIR__ . '/folder');
        PluginManager::$PLUGINS_PATH = $path;
        array_map(function (string $plugin) use ($path) { touch($path . '/' . $plugin); }, static::PLUGIN_NAMES);
    }

    public function tearDown(): void
    {
        $path = __DIR__ . '/folder';
        array_map(function (string $plugin) use ($path) { unlink($path . '/' . $plugin); }, static::PLUGIN_NAMES);
        rmdir($path);
    }

    /**
     * Test displaying plugins admin page
     */
    public function testIndex(): void
    {
        $assignedVariables = [];
        $this->assignTemplateVars($assignedVariables);

        $request = $this->createMock(Request::class);
        $response = new Response();

        $data = [
            'plugin1' => ['order' => 2, 'other' => 'field'],
            'plugin2' => ['order' => 1],
            'plugin3' => ['order' => false, 'abc' => 'def'],
            'plugin4' => [],
        ];

        $this->container->pluginManager
            ->expects(static::once())
            ->method('getPluginsMeta')
            ->willReturn($data);

        $result = $this->controller->index($request, $response);

        static::assertSame(200, $result->getStatusCode());
        static::assertSame('pluginsadmin', (string) $result->getBody());

        static::assertSame('Plugin Administration - Shaarli', $assignedVariables['pagetitle']);
        static::assertSame(
            ['plugin2' => $data['plugin2'], 'plugin1' => $data['plugin1']],
            $assignedVariables['enabledPlugins']
        );
        static::assertSame(
            ['plugin3' => $data['plugin3'], 'plugin4' => $data['plugin4']],
            $assignedVariables['disabledPlugins']
        );
    }

    /**
     * Test save plugins admin page
     */
    public function testSaveEnabledPlugins(): void
    {
        $parameters = [
            'plugin1' => 'on',
            'order_plugin1' => '2',
            'plugin2' => 'on',
        ];

        $request = $this->createMock(Request::class);
        $request
            ->expects(static::atLeastOnce())
            ->method('getParams')
            ->willReturnCallback(function () use ($parameters): array {
                return $parameters;
            })
        ;
        $response = new Response();

        $this->container->pluginManager
            ->expects(static::once())
            ->method('executeHooks')
            ->with('save_plugin_parameters', $parameters)
        ;
        $this->container->conf
            ->expects(static::atLeastOnce())
            ->method('set')
            ->with('general.enabled_plugins', ['plugin1', 'plugin2'])
        ;

        $result = $this->controller->save($request, $response);

        static::assertSame(302, $result->getStatusCode());
        static::assertSame(['/subfolder/admin/plugins'], $result->getHeader('location'));
    }

    /**
     * Test save plugin parameters
     */
    public function testSavePluginParameters(): void
    {
        $parameters = [
            'parameters_form' => true,
            'parameter1' => 'blip',
            'parameter2' => 'blop',
            'token' => 'this parameter should not be saved'
        ];

        $request = $this->createMock(Request::class);
        $request
            ->expects(static::atLeastOnce())
            ->method('getParams')
            ->willReturnCallback(function () use ($parameters): array {
                return $parameters;
            })
        ;
        $response = new Response();

        $this->container->pluginManager
            ->expects(static::once())
            ->method('executeHooks')
            ->with('save_plugin_parameters', $parameters)
        ;
        $this->container->conf
            ->expects(static::exactly(2))
            ->method('set')
            ->withConsecutive(['plugins.parameter1', 'blip'], ['plugins.parameter2', 'blop'])
        ;

        $result = $this->controller->save($request, $response);

        static::assertSame(302, $result->getStatusCode());
        static::assertSame(['/subfolder/admin/plugins'], $result->getHeader('location'));
    }

    /**
     * Test save plugin parameters - error encountered
     */
    public function testSaveWithError(): void
    {
        $request = $this->createMock(Request::class);
        $response = new Response();

        $this->container->conf = $this->createMock(ConfigManager::class);
        $this->container->conf
            ->expects(static::atLeastOnce())
            ->method('write')
            ->willThrowException(new \Exception($message = 'error message'))
        ;

        $this->container->sessionManager = $this->createMock(SessionManager::class);
        $this->container->sessionManager->method('checkToken')->willReturn(true);
        $this->container->sessionManager
            ->expects(static::once())
            ->method('setSessionParameter')
            ->with(
                SessionManager::KEY_ERROR_MESSAGES,
                ['Error while saving plugin configuration: ' . PHP_EOL . $message]
            )
        ;

        $result = $this->controller->save($request, $response);

        static::assertSame(302, $result->getStatusCode());
        static::assertSame(['/subfolder/admin/plugins'], $result->getHeader('location'));
    }

    /**
     * Test save plugin parameters - wrong token
     */
    public function testSaveWrongToken(): void
    {
        $this->container->sessionManager = $this->createMock(SessionManager::class);
        $this->container->sessionManager->method('checkToken')->willReturn(false);

        $request = $this->createMock(Request::class);
        $response = new Response();

        $this->expectException(WrongTokenException::class);

        $this->controller->save($request, $response);
    }
}
