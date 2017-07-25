<?php
/**
 * Created by IntelliJ IDEA.
 * User: rek
 * Date: 2016/5/27
 * Time: 下午4:25
 */

namespace x2ts\rpc;

use x2ts\Toolkit;

class Response extends Message {
    /**
     * @var string
     */
    public $package;

    /**
     * @var string
     */
    public $id;

    /**
     * @var Request
     */
    public $request;

    /**
     * @var mixed
     */
    public $result;

    /**
     * @var array
     */
    public $error;

    /**
     * @var RPCException|RemoteException
     */
    public $exception;

    /**
     * @var int
     */
    public $version = 3;

    /**
     * @var array
     */
    public $options = [
        'compressor' => self::C_NO_COMPRESS,
        'packer'     => self::P_SWOOLE,
    ];

    public function __construct(
        string $package,
        array $response = [
            'id'        => null,
            'error'     => null,
            'result'    => null,
            'exception' => null,
            'request'   => null,
        ],
        $options = [
            'compressor' => self::C_NO_COMPRESS,
            'packer'     => self::P_MSGPACK,
        ]
    ) {
        $this->package = $package;
        $this->id = $response['id'] ?? null;
        $this->error = $response['error'] ?? null;
        $this->result = $response['result'] ?? null;
        $this->exception = $response['exception'] ?? null;
        $this->request = $response['request'] ?? null;
        Toolkit::override($this->options, $options);
    }

    public static function parse(string $data, string $package) {
        $firstByte = unpack('Cfb', $data)['fb'];
        if (0x80 === ($firstByte & 0xf0)) {
            $r = msgpack_unpack($data);
            $res = new Response($package, [
                'id'     => $r['id'],
                'error'  => $r['error'],
                'result' => $r['result'],
            ]);
            if (null !== $r['exception']) {
                $exClass = class_exists($r['exception']['class']) ?
                    $r['exception']['class'] : (__NAMESPACE__ . '\\' . $r['exception']['class']);
                $res->exception = new $exClass(...$r['exception']['args']);
            }
            $res->version = 2;
            return $res;
        }

        if (0x33 === $firstByte) {
            $r = self::unpack(
                self::decompress(
                    substr($data, 1),
                    $compressor
                ),
                $packer
            );
            $res = new Response($package, [
                'id'        => $r['id'],
                'error'     => $r['error'],
                'result'    => $r['result'],
                'exception' => null,
            ]);
            $res->version = 3;
            if ($r['exception'] instanceof RemoteException) {
                $res->exception = $r['exception'];
            } elseif (is_array($r['exception'])) {
                $exClass = class_exists($r['exception']['class']) ?
                    $r['exception']['class'] :
                    __NAMESPACE__ . '\\' . $r['exception']['class'];
                $res->exception = new $exClass(...$r['exception']['args']);
            }

            return $res;
        }

        throw new PacketFormatException('Unsupported response format, cannot parse');
    }

    public function stringify(int $version = 3): string {
        if ($version === 2) {
            $e = $this->exception;
            return msgpack_pack([
                'id'        => $this->id,
                'result'    => $this->result,
                'error'     => $this->error,
                'exception' => $e instanceof \Throwable ? [
                    'class' => 'RPCException',
                    'args'  => ['', [
                        'name'  => get_class($e),
                        'file'  => $e->getFile(),
                        'line'  => $e->getLine(),
                        'code'  => $e->getCode(),
                        'msg'   => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]],
                ] : null,
            ]);
        }

        if ($version === 3) {
            $e = $this->exception;
            $data = [
                'id'        => $this->id,
                'result'    => $this->result,
                'error'     => $this->error,
                'exception' => $e instanceof RemoteException ? $e : [
                    'class' => RPCException::class,
                    'args'  => ['', [
                        'name'  => get_class($e),
                        'file'  => $e->getFile(),
                        'line'  => $e->getLine(),
                        'code'  => $e->getCode(),
                        'msg'   => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]],
                ],
            ];
            return '3' . self::compress(
                    self::pack($data, $this->options['packer']),
                    $this->options['compressor']
                );
        }

        throw new PacketFormatException('Unsupported protocol version');
    }
}