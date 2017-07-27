<?php
/**
 * Created by IntelliJ IDEA.
 * User: rek
 * Date: 2016/5/27
 * Time: 下午4:04
 */

namespace x2ts\rpc;

use AMQPEnvelope;
use AMQPQueue;
use Throwable;
use x2ts\{
    Component, ComponentFactory as X, ExtensionNotLoadedException
};
use x2ts\rpc\event\{
    AfterCall, AfterInvoke, BeforeCall, BeforeInvoke
};
use x2ts\Toolkit;


/**
 * Class RPC
 *
 * @package x2ts\rpc
 *
 * @property-read Driver $driver
 */
class RPC extends Component {
    protected static $_conf = [
        'connection' => [
            'host'            => 'localhost',
            'port'            => 5672,
            'login'           => 'guest',
            'password'        => 'guest',
            'vhost'           => '/',
            'read_timeout'    => 30,
            'write_timeout'   => 30,
            'connect_timeout' => 30,
        ],
        'persistent' => false,
        'maxRequest' => 500,
    ];

    private $package;

    private $callbacks;

    public function __construct(string $package = 'common') {
        if (!extension_loaded('msgpack')) {
            throw new ExtensionNotLoadedException('The x2ts\rpc\RPC required extension msgpack has not been loaded yet');
        }
        $this->callbacks = [];
        $this->setPackage($package);
    }

    public function setPackage(string $package): RPC {
        $this->package = "rpc.$package";
        return $this;
    }

    private $profile = false;

    public function profile() {
        $this->profile = true;
        return $this;
    }

    public function call(string $name, ...$args) {
        X::bus()->dispatch(new BeforeCall([
            'dispatcher' => $this,
            'void'       => false,
            'package'    => $this->package,
            'func'       => $name,
            'args'       => $args,
        ]));
        $this->checkPackage();
        $profile = $this->profile;
        $this->profile = false;
        return (new Request(
            $this->clientChannel,
            $this->package,
            $name,
            $args,
            false,
            $profile
        ))->send()->getResponse();
    }

    public function callVoid(string $name, ...$args) {
        X::bus()->dispatch(new BeforeCall([
            'dispatcher' => $this,
            'void'       => false,
            'package'    => $this->package,
            'func'       => $name,
            'args'       => $args,
        ]));
        $this->checkPackage();
        (new Request($this->clientChannel, $this->package, $name, $args, true))->send();
        X::bus()->dispatch(new AfterCall([
            'dispatcher' => $this,
            'package'    => null,
            'func'       => $this->callInfo['name'],
            'args'       => $this->callInfo['args'],
            'void'       => true,
            'result'     => null,
            'error'      => null,
            'exception'  => null,
        ]));
        $this->profile = false;
    }

    public function asyncCall(string $name, ...$args): Response {
        X::bus()->dispatch(new BeforeCall([
            'dispatcher' => $this,
            'void'       => false,
            'package'    => $this->package,
            'func'       => $name,
            'args'       => $args,
        ]));
        $this->checkPackage();
        return (new Request($this->clientChannel, $this->package, $name, $args))->send();
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

    public function handleRequest(Request $req): Response {
        X::logger()->trace($req);
        try {
            if (array_key_exists($req->func, $this->callbacks)) {
                error_clear_last();
                X::bus()->dispatch(new BeforeInvoke([
                    'dispatcher' => $this,
                    'package'    => $req->package,
                    'void'       => $req->void,
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
                X::logger()->warn($res->exception->args[0]);
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

    /**
     * @param AMQPEnvelope $msg
     * @param AMQPQueue    $q
     *
     * @return bool
     * @throws \AMQPChannelException
     * @throws \AMQPConnectionException
     * @throws \AMQPExchangeException
     */
    public function _onRequest(AMQPEnvelope $msg, AMQPQueue $q) {
        $GLOBALS['_rpc_server_shutdown'] = [$this->getServerExchange(), $msg, $q];
        error_clear_last();
        $req = Request::parse($msg->getBody(), $this->package);

        $error = error_get_last();
        if (!empty($error)) {
            $payload = [
                'error'     => $error,
                'exception' => [
                    'class'  => PacketFormatException::class,
                    'args'   => [
                        'Request packet format error: ' . $error['message'],
                        PacketFormatException::REQUEST,
                    ],
                    'result' => null,
                ],
            ];
            X::logger()->warn($payload['exception']['args'][0]);
            goto reply;
        }
        X::logger()->trace($req);

        try {
            if (array_key_exists($req['func'], $this->callbacks)) {
                error_clear_last();
                X::bus()->dispatch(new BeforeInvoke(array_merge([
                    'dispatcher' => $this,
                ], $req)));
                /** @var IRemoteCallable $obj */
                $obj = $this->callbacks[$req['func']][0];
                $obj->setRPCContext($req);
                $r = call_user_func_array($this->callbacks[$req['func']], $req['args']);
                $payload = [
                    'error'     => error_get_last(),
                    'exception' => null,
                    'result'    => $r,
                ];
                X::bus()->dispatch(new AfterInvoke(array_merge([
                    'dispatcher' => $this,
                    'error'      => $payload['error'],
                    'result'     => $r,
                ], $req)));
                if ($req['void']) {
                    goto finish;
                }
            } else {
                $payload = [
                    'error'     => 'Specified RPC function not exist',
                    'exception' => [
                        'class' => UnregisteredFunctionException::class,
                        'args'  => ["Specified RPC function {$this->package}.{$req['func']} is unregistered."],
                    ],
                    'result'    => null,
                ];
                X::logger()->warn($payload['exception']['args'][0]);
            }
        } catch (Throwable $e) {
            if ($e instanceof RemoteException) {
                $payload = [
                    'error'     => error_get_last(),
                    'exception' => $e,
                ];
            } else {
                $payload = [
                    'error'     => error_get_last(),
                    'exception' => [
                        'class' => 'RPCException',
                        'args'  => ['', [
                            'name'  => get_class($e),
                            'file'  => $e->getFile(),
                            'line'  => $e->getLine(),
                            'code'  => $e->getCode(),
                            'msg'   => $e->getMessage(),
                            'trace' => $e->getTraceAsString(),
                        ]],
                    ],
                    'result'    => null,
                ];
            }
            X::bus()->dispatch(new AfterInvoke(array_merge([
                'dispatcher' => $this,
                'error'      => $payload['error'],
                'exception'  => $e,
            ], $req)));

            X::logger()->trace(function () use ($e) {
                return get_class($e) . ' thrown in remote file "' . $e->getFile()
                    . '" (line: ' . $e->getLine() . ') with code ' . $e->getCode() .
                    ' message: ' . $e->getMessage() . "\n\nRemote Call stack:\n"
                    . $e->getTraceAsString();
            });
        }

        reply:
        $this->getServerExchange()->publish(
            msgpack_pack($payload),
            $msg->getReplyTo(),
            AMQP_NOPARAM,
            ['correlation_id' => $msg->getCorrelationId()]
        );

        finish:
        $q->ack($msg->getDeliveryTag());
        if ($this->conf['maxRequest']) {
            if (++$this->requestCounter >= $this->conf['maxRequest']) {
                Toolkit::log(
                    'Max request limit exceed. Exit the rpc loop',
                    X_LOG_NOTICE
                );
                error_clear_last();
                return false; // exit consume loop
            }
        }
        return true;
    }

    public function listen() {
        Toolkit::trace('listen start');
        $queue = new AMQPQueue($this->serverChannel);
        $queue->setName($this->package);
        $queue->setFlags(AMQP_DURABLE);
        $queue->declareQueue();
        $this->register_rpc_server_shutdown_function();
        $queue->consume([$this, '_onRequest']);
    }

    /** @noinspection MagicMethodsValidityInspection
     * @param string $package
     */
    public function __reconstruct(string $package = 'common') {
        $this->setPackage($package);
    }

}
