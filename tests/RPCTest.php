<?php
/**
 * Created by IntelliJ IDEA.
 * User: rek
 * Date: 2017/7/27
 * Time: 下午4:56
 */

namespace x2ts\tests;

use PHPUnit\Framework\TestCase;
use x2ts\rpc\RPC;

/**
 * Class RPCTest
 *
 * @package x2ts\rpc
 */
class RPCTest extends TestCase {
    private static $server;

    private static $pipes;

    private $rpcClient;

    public static function setUpBeforeClass() {
        $rpcServerFile = __DIR__ . '/server.php';
        self::$server = proc_open("php $rpcServerFile", [
            ['file', '/dev/zero', 'r'],
            ['file', '/dev/null', 'w'],
            ['file', '/dev/null', 'w'],
        ], self::$pipes, __DIR__);
        sleep(1);
    }

    public static function tearDownAfterClass() {
        if (is_resource(self::$server)) {
            proc_terminate(self::$server);
            proc_close(self::$server);
        }
    }

    /**
     * @
     */
    public function testReverse() {
        self::assertEquals(
            'emoclew',
            $this->getRpc()->call('reverse', 'welcome')
        );
    }

    /**
     * @expectedException \x2ts\rpc\UnregisteredFunctionException
     */
    public function testCallUndefinedFunction() {
        $this->getRpc()->call('undef');
    }

    /**
     * @expectedException \x2ts\rpc\UnregisteredFunctionException
     */
    public function testCallSetRPCContext() {
        $this->getRpc()->call('setRPCContext');
    }

    /**
     * @expectedException \x2ts\rpc\UnregisteredFunctionException
     */
    public function testCallStaticMethod() {
        $this->getRpc()->call('staticMethod');
    }

    private function getRpc() {
        if (!$this->rpcClient instanceof RPC) {
            $this->rpcClient = new RPC('test');
            $this->rpcClient->saveConf([], 'rpc');
        }
        return $this->rpcClient;
    }
}
