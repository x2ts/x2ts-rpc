<?php
/**
 * Created by IntelliJ IDEA.
 * User: rek
 * Date: 2017/7/19
 * Time: PM12:50
 */

namespace x2ts\rpc\driver;


use AMQPChannel;
use AMQPConnection;
use AMQPEnvelope;
use AMQPExchange;
use AMQPQueue;
use AMQPQueueException;
use x2ts\ComponentFactory as X;
use x2ts\ExtensionNotLoadedException;
use x2ts\rpc\Driver;
use x2ts\rpc\IRequestHandler;
use x2ts\rpc\PackageNotFoundException;
use x2ts\rpc\Receiver;
use x2ts\rpc\Request;
use x2ts\rpc\Response;
use x2ts\Toolkit;

class AMQP extends Driver {
    private $conf = [
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
    ];

    /**
     * @var int
     */
    protected $requestCounter;

    /**
     * @var IRequestHandler
     */
    private $requestHandler;

    /**
     * @var AMQPChannel
     */
    protected $_serverChannel;

    /**
     * @var AMQPChannel
     */
    protected $_clientChannel;

    /**
     * @var AMQPExchange
     */
    private $_serverExchange;

    /**
     * @var AMQPEnvelope
     */
    private $amqpMessage;

    /**
     * @var AMQPQueue
     */
    private $queue;

    /**
     * @var Request
     */
    private $request;

    public function __construct(array $conf = []) {
        if (!extension_loaded('amqp')) {
            throw new ExtensionNotLoadedException('The amqp extension required by the AMQP rpc driver has not been loaded yet');
        }
        $this->requestCounter = 0;
        Toolkit::override($this->conf, $conf);
    }

    public function send(Request $req): Receiver {
        $ex = new AMQPExchange($this->getClientChannel());
        if ($req->void) {
            $ex->publish($req->stringify(3), $this->queueName());
            return new AMQPReceiver($req);
        }

        $replyQueue = new AMQPQueue($this->getClientChannel());
        $replyQueue->setFlags(AMQP_EXCLUSIVE);
        $replyQueue->declareQueue();
        $ex->publish(
            $req->stringify(3),
            $this->queueName(),
            AMQP_NOPARAM,
            [
                'correlation_id' => $req->id,
                'reply_to'       => $replyQueue->getName(),
            ]
        );
        return new AMQPReceiver($req, $replyQueue);
    }

    public function reply(Response $res) {
        $this->getServerExchange()->publish(
            $res->stringify($res->request->version ?? 2),
            $this->amqpMessage->getReplyTo(),
            AMQP_NOPARAM,
            ['correlation_id' => $this->amqpMessage->getCorrelationId()]
        );
    }

    /**
     * @return string
     */
    private function queueName(): string {
        return "rpc.{$this->package}";
    }

    public function listen() {
        X::logger()->notice('listen start');
        $this->queue = $queue = new AMQPQueue($this->getServerChannel());
        $queue->setName($this->queueName());
        $queue->setFlags(AMQP_DURABLE);
        $queue->declareQueue();
        $this->register_rpc_server_shutdown_function();
        $queue->consume([$this, '_onRequest']);
    }

    private function register_rpc_server_shutdown_function() {
        register_shutdown_function(function () {
            $error = error_get_last();
            if (empty($error)) {
                return;
            }
            if ($error['type'] & (
                    E_ALL &
                    ~E_NOTICE &
                    ~E_WARNING &
                    ~E_DEPRECATED &
                    ~E_USER_NOTICE &
                    ~E_USER_WARNING &
                    ~E_USER_DEPRECATED &
                    ~E_STRICT
                )
            ) {
                $res = new Response($this->package, [
                    'request'   => $this->request,
                    'error'     => $error,
                    'exception' => [
                        'class' => 'RPCException',
                        'args'  => [
                            "An error \"{$error['message']}\" in remote file "
                            . "\"{$error['file']}\" (line: {$error['line']}) "
                            . 'cause the rpc worker down. Please report this '
                            . 'issue to the rpc server administrator as soon '
                            . 'as possible.',
                            [
                                'name'  => '',
                                'file'  => $error['file'],
                                'line'  => $error['line'],
                                'code'  => $error['type'],
                                'msg'   => $error['message'],
                                'trace' => (new \Exception())->getTraceAsString(),
                            ],
                        ],
                    ],
                    'result'    => null,
                ]);
                X::logger()->error($res->exception['args'][0]);
                $this->reply($res);
                $this->queue->reject($this->amqpMessage->getDeliveryTag());
            } else {
                X::logger()->notice($error['message']);
            }
        });
    }

    public function _onRequest(AMQPEnvelope $msg, AMQPQueue $q) {
        $this->amqpMessage = $msg;
        error_clear_last();
        $req = Request::parse($msg->getBody(), $this->package);
        $this->request = $req;
        $res = $this->requestHandler->handle($req);
        if (empty($res->request)) {
            $res->request = $req;
        }
        X::logger()->trace($res);
        if (!$req->void) {
            $this->reply($res);
        }

        $q->ack($msg->getDeliveryTag());
        if ($this->conf['maxRequest']) {
            if (++$this->requestCounter >= $this->conf['maxRequest']) {
                X::logger()->notice('Max request limit exceed. Exit the rpc loop');
                error_clear_last();
                return false; // exit consume loop
            }
        }
        return true;
    }

    /**
     * @param IRequestHandler $handler
     *
     * @return $this
     */
    public function onRequest(IRequestHandler $handler) {
        $this->requestHandler = $handler;
        return $this;
    }

    private function getServerExchange() {
        if (!$this->_serverExchange instanceof AMQPExchange) {
            $this->_serverExchange = new AMQPExchange($this->getServerChannel());
        }
        return $this->_serverExchange;
    }

    private $_connection = [
        'connection' => null,
        'time'       => 0,
        'errs'       => 0,
    ];

    /**
     * @return AMQPConnection
     * @throws \AMQPConnectionException
     */
    private function getConnection(): AMQPConnection {
        $container =& $this->_connection;
        $conf = $this->conf;
        while (true) {
            try {
                if (!$container['connection'] instanceof AMQPConnection) {
                    $container['connection'] = new AMQPConnection($conf);
                    if ($conf['persistent'] ?? false) {
                        $container['connection']->pconnect();
                    } else {
                        $container['connection']->connect();
                    }
                }
                if (!$container['connection']->isConnected()) {
                    if ($conf['persistent']) {
                        $container['connection']->preconnect();
                    } else {
                        $container['connection']->reconnect();
                    }
                }
                return $container['connection'];
            } catch (\AMQPConnectionException $ex) {
                if (time() - $container['time'] > 60) {
                    $container['errs'] = 0;
                }
                $container['time'] = time();
                $container['errs']++;
                if ($container['errs'] > 3) {
                    throw $ex;
                }
            }
        }
    }

    private function getClientChannel() {
        if (!$this->_clientChannel instanceof AMQPChannel ||
            !$this->_clientChannel->isConnected()
        ) {
            $this->_clientChannel = new AMQPChannel($this->getConnection());
            $this->_clientChannel->setPrefetchCount(1);
        }
        $this->_clientChannel
            ->getConnection()
            ->setReadTimeout($this->conf['read_timeout']);
        return $this->_clientChannel;
    }

    private function getServerChannel() {
        if (!$this->_serverChannel instanceof AMQPChannel ||
            !$this->_serverChannel->isConnected()
        ) {
            $this->_serverChannel = new AMQPChannel($this->getConnection());
            $this->_serverChannel->setPrefetchCount(1);
        }
        $this->_serverChannel->getConnection()->setReadTimeout(0);
        return $this->_serverChannel;
    }

    public function checkPackage() {
        try {
            $q = new AMQPQueue($this->getClientChannel());
            $q->setFlags(AMQP_DURABLE | AMQP_PASSIVE);
            $q->setName($this->queueName());
            $q->declareQueue();
            return $this;
        } catch (AMQPQueueException $ex) {
            throw new PackageNotFoundException('Package ' . $this->getPackage() . ' cannot be found');
        }
    }
}