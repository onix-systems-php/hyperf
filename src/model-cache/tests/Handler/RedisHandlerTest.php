<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */
namespace HyperfTest\ModelCache\Handler;

use DateInterval;
use Hyperf\ModelCache\Config;
use Hyperf\ModelCache\Handler\HandlerInterface;
use Hyperf\ModelCache\Handler\RedisHandler;
use Hyperf\Redis\RedisProxy;
use Hyperf\Utils\ApplicationContext;
use HyperfTest\ModelCache\Stub\ContainerStub;
use Mockery;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 * @coversNothing
 */
class RedisHandlerTest extends TestCase
{
    protected $handler = RedisHandler::class;

    protected function tearDown(): void
    {
        Mockery::close();
    }

    public function testSetAndGet()
    {
        $handler = $this->mockHandler();
        $key = 'test:model-cache:' . $this->handler . ':1';
        $handler->set($key, ['id' => $id = uniqid()]);
        $data = $handler->get($key);
        $this->assertSame(['id' => $id], $data);
    }

    public function testSetTtl()
    {
        $handler = $this->mockHandler();
        $key = 'test:model-cache:' . $this->handler . ':1';
        $handler->set($key, ['id' => $id = uniqid()], 10);
        $data = $handler->get($key);
        $this->assertSame(['id' => $id], $data);
        $redis = ApplicationContext::getContainer()->make(RedisProxy::class, ['pool' => 'default']);
        $this->assertSame(10, $redis->ttl($key));

        $handler->set($key, ['id' => $id = uniqid()], new DateInterval('PT12S'));
        $this->assertSame(12, $redis->ttl($key));

        $handler->set($key, ['id' => $id = uniqid()], new DateInterval('P1DT12S'));
        $this->assertSame(86400 + 12, $redis->ttl($key));
    }

    public function testDelete()
    {
        $handler = $this->mockHandler();
        $key = 'test:model-cache:' . $this->handler . ':1';
        $handler->set($key, ['id' => $id = uniqid()]);
        $this->assertTrue($handler->has($key));
        $this->assertTrue($handler->delete($key));
        $this->assertFalse($handler->has($key));
    }

    public function testGetMultiple()
    {
        $handler = $this->mockHandler();
        $keys = [
            'test:model-cache:' . $this->handler . ':1',
            'test:model-cache:' . $this->handler . ':2',
        ];
        $result = [];
        foreach ($keys as $key) {
            $handler->set($key, $item = ['id' => uniqid()]);
            $result[] = $item;
        }

        $this->assertSame($result, $handler->getMultiple($keys));
    }

    protected function mockHandler(): HandlerInterface
    {
        $config = new Config([
            'handler' => $this->handler,
            'cache_key' => '{mc:%s:m:%s}:%s:%s',
            'prefix' => 'default',
            'pool' => 'default',
            'ttl' => 3600 * 24,
            'empty_model_ttl' => 3600,
            'load_script' => true,
            'use_default_value' => true,
        ], 'default');

        return ContainerStub::mockContainer()->make($this->handler, ['config' => $config]);
    }
}
