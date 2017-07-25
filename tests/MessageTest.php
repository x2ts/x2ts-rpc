<?php
/**
 * Created by IntelliJ IDEA.
 * User: rek
 * Date: 2017/7/19
 * Time: PM4:33
 */

namespace x2ts\rpc\tests;

use PHPUnit\Framework\TestCase;
use x2ts\rpc\Message;

/**
 * Class MessageTest
 *
 * @package  x2ts\rpc\tests
 */
class MessageTest extends TestCase {
    private $data = ['abc' => 123, 'aa' => 'bb'];

    private $str = <<<'STR'
1.9.6版本增加了一个新的模块swoole_serialize，是一个高性能的序列化库，与PHP官方提供的serialize和json_encode相比，swoole_serialize的不同之处是：

序列化后的结果为二进制格式，只适合机器读取，不适合人读
序列化性能更高，可节省大量CPU资源，基准测试中序列化和反序列化耗时为PHP官方serialize的40%
序列化后的结果数据尺寸更小，可节省内存资源，基准测试中序列化结果尺寸为PHP官方serialize的50%
serialize模块仅在PHP7或更高版本中可用
相关配置

可修改php.ini配置，在swoole_server中的task功能中使用swoole_serialize对异步任务数据序列化。
STR;

    public function testPhpSerialize() {
        self::assertEquals(
            'p' . serialize($this->data),
            Message::pack($this->data, 'p')
        );
    }

    /**
     * @requires extension swoole
     * @requires function swoole_serialize::pack
     */
    public function testSwoolePack() {
        self::assertEquals(
            's' . \swoole_serialize::pack($this->data),
            Message::pack($this->data, 's')
        );
    }

    /**
     * @requires extension msgpack
     */
    public function testMsgpackPack() {
        self::assertEquals(
            'm' . msgpack_pack($this->data),
            Message::pack($this->data, 'm')
        );
    }

    public function testJsonPack() {
        self::assertEquals(
            'j' . json_encode($this->data),
            Message::pack($this->data, 'j')
        );
    }

    public function testPhpUnpack() {
        self::assertEquals(
            $this->data,
            Message::unpack(
                'p' . serialize($this->data),
                $packer
            )
        );
        self::assertEquals(Message::P_PHP, $packer);
    }

    /**
     * @requires extension swoole
     * @requires function swoole_serialize::unpack
     */
    public function testSwooleUnpack() {
        self::assertEquals(
            $this->data,
            Message::unpack(
                's' . \swoole_serialize::pack($this->data),
                $packer
            )
        );
        self::assertEquals(Message::P_SWOOLE, $packer);
    }

    /**
     * @requires extension msgpack
     */
    public function testMsgpackUnpack() {
        self::assertEquals(
            $this->data,
            Message::unpack(
                'm' . msgpack_pack($this->data),
                $packer
            )
        );
        self::assertEquals(Message::P_MSGPACK, $packer);
    }

    public function testJsonUnpack() {
        self::assertEquals(
            $this->data,
            Message::unpack(
                'j' . json_encode($this->data),
                $packer
            )
        );
        self::assertEquals(Message::P_JSON, $packer);
    }

    /**
     * @requires function gzencode
     */
    public function testGzipCompress() {
        self::assertEquals(
            'z' . gzencode($this->str),
            Message::compress($this->str, Message::C_GZIP)
        );
    }

    /**
     * @requires function bzcompress
     */
    public function testBz2Compress() {
        self::assertEquals(
            'j' . bzcompress($this->str),
            Message::compress($this->str, Message::C_BZ2)
        );
    }

    /**
     * @requires extension lzf
     */
    public function testLzfCompress() {
        self::assertEquals(
            'l' . lzf_compress($this->str),
            Message::compress($this->str, Message::C_LZF)
        );
    }

    public function testNoCompress() {
        self::assertEquals(
            'n' . $this->str,
            Message::compress($this->str, Message::C_NO_COMPRESS)
        );
    }

    public function testNoDecompress() {
        self::assertEquals(
            $this->str,
            Message::decompress(
                'n' . $this->str,
                $format
            )
        );
        self::assertEquals(Message::C_NO_COMPRESS, $format);
    }

    /**
     * @requires extension lzf
     */
    public function testLzfDecompress() {
        self::assertEquals(
            $this->str,
            Message::decompress(
                'l' . lzf_compress($this->str),
                $format
            )
        );
        self::assertEquals(Message::C_LZF, $format);
    }

    public function testBz2Decompress() {
        self::assertEquals(
            $this->str,
            Message::decompress(
                'j' . bzcompress($this->str),
                $format
            )
        );
        self::assertEquals(Message::C_BZ2, $format);
    }

    public function testGzipDecompress() {
        self::assertEquals(
            $this->str,
            Message::decompress(
                'z' . gzencode($this->str),
                $format
            )
        );
        self::assertEquals(Message::C_GZIP, $format);
    }

    /**
     * @expectedException \x2ts\rpc\PacketFormatException
     * @expectedExceptionMessage Unsupported compressor
     */
    public function testUnsupportCompress() {
        Message::compress($this->str, 'k');
    }

    /**
     * @expectedException \x2ts\rpc\PacketFormatException
     * @expectedExceptionMessage Unsupported compressor
     */
    public function testUnsupportDecompress() {
        Message::decompress('f' . $this->str, $format);
    }

    /**
     * @expectedException \x2ts\rpc\PacketFormatException
     * @expectedExceptionMessage Unsupported data packer
     */
    public function testUnsupportPack() {
        Message::pack($this->data, 'x');
    }

    /**
     * @expectedException \x2ts\rpc\PacketFormatException
     * @expectedExceptionMessage Unsupported data packer
     */
    public function testUnsupportUnpack() {
        Message::unpack($this->str, $format);
    }

    public function testDelayCompressor() {
        self::assertEquals(
            'z' . gzencode($this->str),
            Message::compress($this->str, function ($data) {
                return 'z';
            })
        );
    }
}
