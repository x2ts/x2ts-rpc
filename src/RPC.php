<?php
/**
 * Created by IntelliJ IDEA.
 * User: rek
 * Date: 2016/5/27
 * Time: 下午4:04
 */

namespace x2ts\rpc;

use Throwable;
use x2ts\{
    Component, ComponentFactory as X, rpc\driver\AMQP, Toolkit
};
use x2ts\rpc\event\{
    AfterCall, AfterInvoke, BeforeCall, BeforeInvoke
};


/**
 * Class RPC
 *
 * @package x2ts\rpc
 *
 * @property-read Driver $driver
 * @property array       $meta
 */
class RPC extends Component implements IRequestHandler {
    protected static $_conf = [
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
    ];

    /**
     * @var string
     */
    private $package;

    /**
     * @var array
     */
    private $callbacks;

    /**
     * @var string
     */
    private $listenPackage;

    /**
     * @var array
     */
    private $_meta = [];

    public function __construct(string $package = 'common') {
        $this->callbacks = [];
        $this->setPackage($package);
    }

    public function setPackage(string $package): RPC {
        $this->package = $package;
        if ($this->_driver instanceof Driver) {
            $this->_driver->setPackage($package);
        }
        return $this;
    }

    /**
     * @var Driver
     */
    private $_driver;

    public function getDriver() {
        if (!$this->_driver instanceof Driver) {
            $driverClass = $this->conf['driver']['class'];
            $this->_driver = new $driverClass($this->conf['driver']['conf']);
            $this->_driver->setPackage($this->package);
        }
        return $this->_driver;
    }

    public function profile() {
        $this->_meta['profile'] = true;
        return $this;
    }

    public function setMeta(array $meta) {
        $this->_meta = $meta;
        return $this;
    }

    public function addMeta(array $meta) {
        Toolkit::override($this->_meta, $meta);
        return $this;
    }

    public function getMeta() {
        return $this->_meta;
    }

    public function resetMeta() {
        $this->_meta = [];
        return $this;
    }

    private function prepareCall($func, $args, $void) {
        $this->driver->checkPackage();
        X::bus()->dispatch(new BeforeCall([
            'dispatcher' => $this,
            'void'       => $void,
            'package'    => $this->package,
            'func'       => $func,
            'args'       => $args,
        ]));
        $req = new Request($this->package, [
            'func' => $func,
            'args' => $args,
            'void' => $void,
            'meta' => $this->_meta ?? [],
        ], $this->conf['messageOpts']);
        $this->resetMeta();
        return $req;
    }

    private function send(Request $request) {
        $r = $this->driver->send($request);
        if ($this->listenPackage) {
            $this->setPackage($this->listenPackage);
        }
        return $r;
    }

    public function call(string $func, ...$args) {
        return $this->send($this->prepareCall($func, $args, false))->fetchReply();
    }

    public function callVoid(string $func, ...$args) {
        $this->send($req = $this->prepareCall($func, $args, true));
        X::bus()->dispatch(new AfterCall([
            'dispatcher' => $this,
            'package'    => $req->package,
            'func'       => $req->func,
            'args'       => $req->args,
            'void'       => true,
            'meta'       => $req->meta,
            'result'     => null,
            'error'      => null,
            'exception'  => null,
        ]));
    }

    public function asyncCall(string $func, ...$args): Receiver {
        return $this->send($this->prepareCall($func, $args, false));
    }

    /**
     * @param IRemoteCallable $obj
     *
     * @return $this
     */
    public function register(IRemoteCallable $obj) {
        foreach ($obj->getRPCMethods() as $methodName) {
            X::logger()->notice("register $methodName to package {$this->package}");
            $this->callbacks[$methodName] = [$obj, $methodName];
        }
        return $this;
    }

    public function handle(Request $req): Response {
        X::logger()->trace($req);
        try {
            if (array_key_exists($req->func, $this->callbacks)) {
                error_clear_last();
                X::bus()->dispatch(new BeforeInvoke([
                    'dispatcher' => $this,
                    'package'    => $req->package,
                    'void'       => $req->void,
                    'func'       => $req->func,
                    'args'       => $req->args,
                    'meta'       => $req->meta,
                ]));
                /** @var IRemoteCallable $obj */
                $obj = $this->callbacks[$req->func][0];
                $obj->setRPCContext($req);
                $r = call_user_func_array($this->callbacks[$req->func], $req->args);
                $res = new Response($this->package, [
                    'id'        => $req->id,
                    'error'     => error_get_last(),
                    'result'    => $r,
                    'exception' => null,
                    'request'   => $req,
                ], $req->options);

                X::bus()->dispatch(new AfterInvoke([
                    'dispatcher' => $this,
                    'void'       => $req->void,
                    'package'    => $req->package,
                    'func'       => $req->func,
                    'meta'       => $req->meta,
                    'args'       => $req->args,
                    'error'      => $res->error,
                    'result'     => $res->result,
                ]));
                if ($req->void) {
                    goto finish;
                }
            } else {
                $res = new Response($this->package, [
                    'id'        => $req->id,
                    'error'     => 'Specified RPC function not exist',
                    'exception' => [
                        'class' => UnregisteredFunctionException::class,
                        'args'  => ["Specified RPC function {$this->package}.{$req->func} is unregistered."],
                    ],
                    'request'   => $req,
                ], $req->options);
                X::logger()->warn($res->exception['args'][0]);
            }
        } catch (Throwable $e) {
            $res = new Response($this->package, [
                'id'        => $req->id,
                'error'     => error_get_last(),
                'exception' => $e,
                'request'   => $req,
            ], $req->options);
            X::bus()->dispatch(new AfterInvoke([
                'dispatcher' => $this,
                'void'       => $req->void,
                'package'    => $req->package,
                'func'       => $req->func,
                'args'       => $req->args,
                'meta'       => $req->meta,
                'result'     => $res->result,
                'error'      => $res->error,
                'exception'  => $res->exception,
            ]));

            X::logger()->trace(function () use ($e) {
                return get_class($e) . ' thrown in remote file "' . $e->getFile()
                    . '" (line: ' . $e->getLine() . ') with code ' . $e->getCode() .
                    ' message: ' . $e->getMessage() . "\n\nRemote Call stack:\n"
                    . $e->getTraceAsString();
            });
        }
        finish:
        return $res;
    }

    public function listen() {
        $this->listenPackage = $this->package;
        $this->driver->onRequest($this)->listen();
    }

    /** @noinspection MagicMethodsValidityInspection
     * @param string $package
     */
    public function __reconstruct(string $package = 'common') {
        $this->setPackage($package);
    }
}
