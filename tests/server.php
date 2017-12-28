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

/**
 * Class T
 * @method static \x2ts\rpc\RPC rpc(string $package = 'common')
 * @method static \x2ts\daemon\Daemon daemon(array $settings = [])
 */
class T extends \x2ts\ComponentFactory {
}

T::conf([
    'component' => [
        'rpc'    => [
            'class'     => \x2ts\rpc\RPC::class,
            'singleton' => true,
            'conf'      => [
                'driver'      => [
                    'class' => AMQP::class,
                    'conf'  => [
                        'host'            => 'localhost',
                        'port'            => 5672,
                        'login'           => 'guest',
                        'password'        => 'guest',
                        'vhost'           => '/',
                        'read_timeout'    => 3,
                        'write_timeout'   => 3,
                        'connect_timeout' => 3,
                        'persistent'      => false,
                        'maxRequest'      => 500,
                    ],
                ],
                'messageOpts' => [
                    'compressor' => Message::C_NO_COMPRESS,
                    'packer'     => Message::P_MSGPACK,
                ],
            ],
        ],
        'daemon' => [
            'class'     => \x2ts\daemon\Daemon::class,
            'singleton' => true,
            'conf'      => [
                'workerNum'     => 4,
                'autoRestart'   => true,
                'daemonize'     => false,
                'name'          => 'rpcserver',
                'onWorkerStart' => null,
                'pidFile'       => '/tmp/daemon.pid',
                'lockFile'      => '/tmp/daemon.lock',
                'user'          => '',
                'group'         => '',
            ],
        ],
    ],
]);

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

    public function sleepFor(int $sleep = 1): int {
        echo "Sleep for {$sleep}s\n";
        for ($i = 1; $i <= $sleep; $i++) {
            sleep(1);
            echo $i, "\n";
        }
        return $sleep;
    }

    public function serverSleep(int $sleep): int {
        return T::rpc('test')->call('sleepFor', $sleep);
    }

    public static function staticMethod() {
        return "This should not appear";
    }

    public function throwRemoteException() {
        throw new RPCRemoteException('throwException', 333);
    }
}

T::daemon()->run(function () {
    T::rpc('test')->register(new TestRPC())->listen();
});
