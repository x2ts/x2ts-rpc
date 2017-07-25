<?php
/**
 * Created by IntelliJ IDEA.
 * User: rek
 * Date: 2017/7/19
 * Time: PM12:44
 */

namespace x2ts\rpc;

use Swoole\Serialize;

abstract class Message {
    const C_NO_COMPRESS = 'n';
    const C_GZIP = 'z';
    const C_BZ2 = 'j';
    const C_LZF = 'l';

    const P_SWOOLE = 's';
    const P_MSGPACK = 'm';
    const P_PHP = 'p';
    const P_JSON = 'j';

    /**
     * @param string          $data
     * @param string|callable $compressor
     *
     * @return string
     * @throws PacketFormatException
     */
    public static function compress($data, $compressor = 'n') {
        if (is_callable($compressor)) {
            $compressor = $compressor($data);
        }
        switch ($compressor) {
            case 'n':
                return 'n' . $data;
            case 'z':
                return 'z' . gzencode($data);
            case 'j':
                return 'j' . bzcompress($data);
            case 'l':
                return 'l' . lzf_compress($data);
            default:
                throw new PacketFormatException('Unsupported compressor');
        }
    }

    /**
     * @param string $data
     * @param string $compressor
     *
     * @return string
     * @throws PacketFormatException
     */
    public static function decompress($data, &$compressor) {
        $compressor = $data[0];
        $compressed = substr($data, 1);
        switch ($compressor) {
            case 'n':
                return $compressed;
            case 'z':
                return gzdecode($compressed);
            case 'l':
                return lzf_decompress($compressed);
            case 'j':
                return bzdecompress($compressed);
            default:
                throw new PacketFormatException('Unsupported compressor');
        }
    }

    /**
     * @param string $data
     * @param string $packer
     *
     * @return array
     * @throws PacketFormatException
     */
    public static function unpack($data, &$packer) {
        $packer = $data[0];
        $packed = substr($data, 1);
        switch ($packer) {
            case 's':
                return Serialize::unpack($packed);
            case 'm':
                return msgpack_unpack($packed);
            case 'p':
                return unserialize($packed, ['allowed_class' => true]);
            case 'j':
                return json_decode($packed, true);
            default:
                throw new PacketFormatException('Unsupported data packer');
        }
    }

    /**
     * @param array  $data
     * @param string $packer
     *
     * @return string
     * @throws PacketFormatException
     */
    public static function pack($data, $packer = 'm') {
        switch ($packer) {
            case 's':
                return 's' . Serialize::pack($data);
            case 'm':
                return 'm' . msgpack_pack($data);
            case 'p':
                return 'p' . serialize($data);
            case 'j':
                return 'j' . json_encode($data);
            default:
                throw new PacketFormatException('Unsupported data packer');
        }
    }

    /**
     * @param int $version
     *
     * @return string
     */
    abstract public function stringify(int $version = 3): string;

    /**
     * @param string $data
     * @param string $package
     *
     * @return $this
     */
    abstract public static function parse(string $data, string $package);

}