<?php
/**
 * Created by IntelliJ IDEA.
 * User: rek
 * Date: 2017/7/19
 * Time: PM7:24
 */

namespace x2ts\rpc\tests;

use PHPUnit\Framework\TestCase;
use x2ts\rpc\Request;

class RequestTest extends TestCase {
    /**
     * @covers \x2ts\rpc\Request::__construct
     */
    public function testAssignValuesInConstructor() {
        $req = new Request('test', [
            'func' => 'testFunc',
            'args' => ['test', 'abc'],
            'void' => true,
        ]);

        self::assertEquals(
            'testFunc',
            $req->func
        );

        self::assertEquals(
            ['test', 'abc'],
            $req->args
        );

        self::assertEquals(
            [],
            $req->meta
        );
    }

    /**
     * @covers \x2ts\rpc\Request::stringify
     */
    public function testStringifyVer2() {
        $req = new Request('test', [
            'func' => 'functionName',
            'args' => ['abc', 'aa'],
            'meta' => ['profile' => true],
        ], [
            'compressor' => 'n',
            'packer'     => 's',
        ]);

        self::assertEquals(
            msgpack_pack([
                'id'             => $req->id,
                'name'           => 'functionName',
                'args'           => ['abc', 'aa'],
                'void'           => false,
                'profileEnabled' => true,
            ]),
            $req->stringify(2)
        );
    }

    /**
     * @covers \x2ts\rpc\Request::stringify
     */
    public function testStringifyVer3() {
        $req = new Request('test', [
            'func' => 'functionName',
            'args' => ['abc', 'aa'],
            'meta' => ['profile' => true],
        ], [
            'compressor' => 'n',
            'packer'     => 's',
        ]);

        self::assertEquals(
            [
                'id'   => $req->id,
                'func' => 'functionName',
                'args' => ['abc', 'aa'],
                'void' => false,
                'meta' => ['profile' => true],
            ],
            \swoole_serialize::unpack(substr($req->stringify(3), 3))
        );
    }

    /**
     * @expectedException \x2ts\rpc\PacketFormatException
     * @expectedExceptionMessage Unsupported protocol version
     */
    public function testStringifyVer9() {
        $req = new Request('test', [
            'func' => 'functionName',
            'args' => ['abc', 'aa'],
            'meta' => ['profile' => true],
        ], [
            'compressor' => 'n',
            'packer'     => 's',
        ]);
        $req->stringify(9);
    }

    /**
     * @covers \x2ts\rpc\Request::parse
     */
    public function testParseVer2() {
        $req = Request::parse(msgpack_pack([
            'id'             => '57e09fcb',
            'name'           => 'functionName',
            'args'           => ['abc', 'aa'],
            'void'           => false,
            'profileEnabled' => true,
        ]), 'test');
        self::assertEquals('57e09fcb', $req->id);
        self::assertEquals('functionName', $req->func);
        self::assertEquals(['abc', 'aa'], $req->args);
        self::assertEquals(false, $req->void);
        self::assertEquals(['profile' => true], $req->meta);
        self::assertEquals(2, $req->version);
        self::assertEquals('test', $req->package);
    }

    /**
     * @covers \x2ts\rpc\Request::parse
     */
    public function testParseVer3() {
        $buffer = '3z' . gzencode(
                's' . \swoole_serialize::pack([
                    'id'   => '57e09fcb',
                    'func' => 'functionName',
                    'args' => ['abc', 'aa'],
                    'void' => false,
                    'meta' => ['profile' => true],
                ])
            );
        $req = Request::parse($buffer, 'test');
        self::assertEquals('57e09fcb', $req->id);
        self::assertEquals('functionName', $req->func);
        self::assertEquals(['abc', 'aa'], $req->args);
        self::assertEquals(false, $req->void);
        self::assertEquals(['profile' => true], $req->meta);
        self::assertEquals(3, $req->version);
        self::assertEquals('test', $req->package);
    }

    /**
     * @expectedException \x2ts\rpc\PacketFormatException
     * @expectedExceptionMessage Unsupported protocol version, cannot parse
     */
    public function testParseVer9() {
        Request::parse('9z', 'test');
    }
}
