<?php
/**
 * Created by IntelliJ IDEA.
 * User: rek
 * Date: 2016/5/27
 * Time: 下午4:28
 */

namespace x2ts\rpc;

use x2ts\Toolkit;

class Request extends Message {
    /**
     * @var string
     */
    public $id;

    /**
     * @var string
     */
    public $func;

    /**
     * @var array
     */
    public $args;

    /**
     * @var bool
     */
    public $void;

    /**
     * @var array
     */
    public $meta;

    /**
     * @var int
     */
    public $version;

    /**
     * @var string
     */
    public $package;

    /**
     * @var array
     */
    public $options = [
        'compressor' => 'n',
        'packer'     => 'm',
    ];

    public function __construct(
        string $package,
        array $req = [
            'func' => '',
            'args' => [],
            'void' => false,
            'meta' => [],
        ],
        array $options = [
            'compressor' => self::C_NO_COMPRESS,
            'packer'     => self::P_MSGPACK,
        ]
    ) {
        $this->func = $req['func'];
        $this->args = $req['args'] ?? [];
        $this->void = $req['void'] ?? false;
        $this->meta = $req['meta'] ?? [];
        Toolkit::override($this->options, $options);
        $this->id = $req['id'] ?? uniqid('', false);
        $this->package = $package;
        $this->version = 3;
    }

    public static function parse(string $data, string $package) {
        $firstByte = unpack('Cfb', $data)['fb'];
        if (0x80 === (0xf0 & $firstByte)) {// x2ts-rpc 2.x proto
            $r = msgpack_unpack($data);
            $request = new Request($package);
            $request->id = $r['id'];
            $request->func = $r['name'];
            $request->args = $r['args'];
            $request->void = $r['void'];
            if (isset($r['profileEnabled'])) {
                $request->meta = ['profile' => $r['profileEnabled']];
            }
            $request->version = 2;
            return $request;
        }

        if ($firstByte === 0x33) { // x2ts-rpc 3.x proto
            $compressed = substr($data, 1);
            $packed = self::decompress($compressed, $compressor);
            $r = self::unpack($packed, $packer);
            $request = new Request($package, $r, [
                'compressor' => $compressor,
                'packer'     => $packer,
            ]);
            return $request;
        }

        throw new PacketFormatException('Unsupported protocol version, cannot parse');
    }

    public function stringify(int $version = 3): string {
        if ($version === 3) {
            return $version . self::compress(
                    self::pack([
                        'id'   => $this->id,
                        'func' => $this->func,
                        'args' => $this->args,
                        'void' => $this->void,
                        'meta' => $this->meta,
                    ], $this->options['packer']),
                    $this->options['compressor']
                );
        }

        if ($version === 2) {
            return msgpack_pack([
                'id'             => $this->id,
                'name'           => $this->func,
                'args'           => $this->args,
                'void'           => $this->void,
                'profileEnabled' => $this->meta['profile'] ?? false,
            ]);
        }

        throw new PacketFormatException('Unsupported protocol version');
    }
}