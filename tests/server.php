<?php
/**
 * Created by IntelliJ IDEA.
 * User: rek
 * Date: 2017/7/27
 * Time: 下午4:23
 */

use x2ts\rpc\driver\AMQP;
use x2ts\rpc\Message;
use x2ts\rpc\tests\RPCRemoteException;

require_once dirname(__DIR__) . '/vendor/autoload.php';

class TestRPC implements \x2ts\rpc\IRemoteCallable {
    public $context;

    use \x2ts\rpc\TPublicRemoteCallable;

    /**
     * @param \x2ts\rpc\Request $context
     *
     * @return void
     */
    public function setRPCContext(\x2ts\rpc\Request $context) {
        $this->context = $context;
    }

    public function reverse(string $str): string {
        return implode(array_reverse(str_split($str)));
    }

    public static function staticMethod() {
        return "This should not appear";
    }

    public function throwRemoteException() {
        throw new RPCRemoteException('throwException', 333);
    }
}

$rpc = new \x2ts\rpc\RPC('test');
$rpc->saveConf(
    [
        'driver'      => [
            'class' => AMQP::class,
            'conf'  => [
                'host'            => 'localhost',
                'port'            => 5672,
                'login'           => 'guest',
                'password'        => 'guest',
                'vhost'           => '/',
                'read_timeout'    => 30,
                'write_timeout'   => 30,
                'connect_timeout' => 30,
                'persistent'      => false,
                'maxRequest'      => 500,
            ],
        ],
        'messageOpts' => [
            'compressor' => Message::C_NO_COMPRESS,
            'packer'     => Message::P_MSGPACK,
        ],
    ],
    'RPC'
);

$rpc->register(new TestRPC())->listen();