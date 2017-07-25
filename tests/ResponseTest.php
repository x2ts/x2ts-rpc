<?php
/**
 * Created by IntelliJ IDEA.
 * User: rek
 * Date: 2017/7/20
 * Time: PM3:54
 */

namespace x2ts\rpc\tests;

use PHPUnit\Framework\TestCase;
use x2ts\rpc\Message;
use x2ts\rpc\RemoteException;
use x2ts\rpc\Response;
use x2ts\rpc\RPCException;

class ResponseTest extends TestCase {
    /**
     * @covers \x2ts\rpc\Response::__construct
     */
    public function testAssignValuesInConstructor() {
        $res = new Response('test', [
            'id'        => '139dda0c8be',
            'error'     => null,
            'result'    => 399,
            'exception' => new RPCException('abc'),
        ]);
        self::assertEquals('139dda0c8be', $res->id);
        self::assertEquals(399, $res->result);
        self::assertNull($res->error);
        self::assertInstanceOf(RPCException::class, $res->exception);
    }

    /**
     * @covers \x2ts\rpc\Response::stringify
     */
    public function testStringifyVer2() {
        $ex = new \Exception('a test exception', 5);
        $line = __LINE__ - 1;
        $res = new Response('test', [
            'id'        => '139dda0c8be',
            'error'     => null,
            'result'    => 399,
            'exception' => $ex,
        ]);
        self::assertEquals(
            [
                'id'        => '139dda0c8be',
                'error'     => null,
                'result'    => 399,
                'exception' => [
                    'class' => 'RPCException',
                    'args'  => ['', [
                        'name'  => 'Exception',
                        'file'  => __FILE__,
                        'line'  => $line,
                        'code'  => 5,
                        'msg'   => 'a test exception',
                        'trace' => $ex->getTraceAsString(),
                    ]],
                ],
            ],
            msgpack_unpack($res->stringify(2))
        );
    }

    public function testStringifyVer3() {
        $ex = new \x2ts\ExtensionNotLoadedException('another test exception', 500);
        $line = __LINE__ - 1;
        $res = new Response('test', [
            'id'        => 'pingpong',
            'error'     => null,
            'result'    => 'ok',
            'exception' => $ex,
        ], ['compressor' => 'z']);

        self::assertEquals(
            [
                'id'        => 'pingpong',
                'error'     => null,
                'result'    => 'ok',
                'exception' => [
                    'class' => RPCException::class,
                    'args'  => ['', [
                        'name'  => 'x2ts\ExtensionNotLoadedException',
                        'file'  => __FILE__,
                        'line'  => $line,
                        'code'  => 500,
                        'msg'   => 'another test exception',
                        'trace' => $ex->getTraceAsString(),
                    ]],
                ],
            ],
            Message::unpack(
                Message::decompress(
                    substr($res->stringify(3), 1),
                    $compressor
                ),
                $packer
            )
        );
    }

    public function testParseVer2() {
        $res = Response::parse(msgpack_pack([
            'id'        => 'panda',
            'error'     => null,
            'result'    => 'ok',
            'exception' => null,
        ]), 'test');

        self::assertEquals('panda', $res->id);
        self::assertNull($res->error);
        self::assertNull($res->exception);
        self::assertEquals('ok', $res->result);
        self::assertEquals('test', $res->package);
    }

    public function testParseVer2WithEx() {
        $res = Response::parse(msgpack_pack([
            'id'        => 'shirokuma',
            'error'     => null,
            'result'    => null,
            'exception' => [
                'class' => 'RPCException',
                'args'  => ['', [
                    'code'  => 255,
                    'msg'   => 'hehe',
                    'line'  => 5,
                    'file'  => __FILE__,
                    'trace' => 'abc',
                ]],
            ],
        ]), 'test');
        self::assertEquals('shirokuma', $res->id);
        self::assertNull($res->error);
        self::assertNull($res->result);
        self::assertInstanceOf(RPCException::class, $res->exception);
        $ex = $res->exception;
        self::assertEquals('hehe', $ex->remoteMessage);
        self::assertEquals(5, $ex->remoteLine);
        self::assertEquals(255, $ex->remoteCode);
        self::assertEquals(__FILE__, $ex->remoteFile);
        self::assertEquals('abc', $ex->remoteCallStack);
    }

    public function testParseVer3() {
        $res = Response::parse('3ns' . \swoole_serialize::pack([
                'id'        => 'kame',
                'error'     => null,
                'result'    => 'xts',
                'exception' => null,
            ]), 'test');

        self::assertEquals('kame', $res->id);
        self::assertEquals('test', $res->package);
        self::assertEquals('xts', $res->result);
        self::assertNull($res->error);
        self::assertNull($res->exception);
    }

    public function testParseVer3WithEx() {
        $e = new RPCRemoteException('great exception example', 9);
        $res = Response::parse('3nm' . msgpack_pack([
                'id'        => 'penguin',
                'error'     => null,
                'result'    => null,
                'exception' => $e,
            ]), 'test');
        self::assertEquals('penguin', $res->id);
        self::assertEquals('test', $res->package);
        self::assertNull($res->error);
        self::assertNull($res->result);
        self::assertInstanceOf(RemoteException::class, $res->exception);
        $ex = $res->exception;
        self::assertEquals('great exception example', $ex->remoteMessage);
        self::assertEquals(9, $ex->remoteCode);
        self::assertEquals(__FILE__, $ex->remoteFile);
    }

    public function testParseVer3WithEx2() {
        $e = new RPCRemoteException('great exception example', 9);
        $res = Response::parse('3nm' . msgpack_pack([
                'id'        => 'penguin',
                'error'     => null,
                'result'    => null,
                'exception' => [
                    'class' => RPCException::class,
                    'args'  => ['', [
                        'code'  => 9,
                        'msg'   => 'great exception example',
                        'line'  => __LINE__,
                        'file'  => __FILE__,
                        'trace' => 'abc',
                    ]],
                ],
            ]), 'test');
        self::assertEquals('penguin', $res->id);
        self::assertEquals('test', $res->package);
        self::assertNull($res->error);
        self::assertNull($res->result);
        self::assertInstanceOf(RPCException::class, $res->exception);
        $ex = $res->exception;
        self::assertEquals('great exception example', $ex->remoteMessage);
        self::assertEquals(9, $ex->remoteCode);
        self::assertEquals(__FILE__, $ex->remoteFile);
    }

    /**
     * @expectedException \x2ts\rpc\PacketFormatException
     * @expectedExceptionMessage Unsupported response format, cannot parse
     */
    public function testParseVer9() {
        Response::parse('9cca', 'test');
    }

    /**
     * @expectedException \x2ts\rpc\PacketFormatException
     * @expectedExceptionMessage Unsupported protocol version
     */
    public function testStringifyVer9() {
        $res = new Response('test', [
            'id'        => '139dda0c8be',
            'error'     => null,
            'result'    => 399,
            'exception' => null,
        ]);
        $res->stringify(9);
    }
}
