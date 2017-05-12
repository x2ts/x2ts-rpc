<?php
/**
 * Created by IntelliJ IDEA.
 * User: rek
 * Date: 2016/5/27
 * Time: 下午4:28
 */

namespace x2ts\rpc;


use AMQPChannel;
use AMQPExchange;
use AMQPQueue;

class Request {
    private $id;

    private $name;

    private $args;

    private $channel;

    private $package;

    private $void;

    public function __construct(AMQPChannel $channel, string $package, string $name, array $args, bool $void = false) {
        $this->channel = $channel;
        $this->package = $package;
        $this->name = $name;
        $this->args = $args;
        $this->void = $void;
        $this->id = uniqid('', false);
    }

    public function send() {
        $ex = new AMQPExchange($this->channel);
        $payload = msgpack_pack(
            [
                'id'   => $this->id,
                'name' => $this->name,
                'args' => $this->args,
                'void' => $this->void,
            ]
        );
        if ($this->void) {
            $ex->publish($payload, $this->package);
            return null;
        }

        $replyQueue = new AMQPQueue($this->channel);
        $replyQueue->setFlags(AMQP_EXCLUSIVE);
        $replyQueue->declareQueue();
        $ex->publish(
            $payload, $this->package, AMQP_NOPARAM, [
                'correlation_id' => $this->id,
                'reply_to'       => $replyQueue->getName(),
            ]
        );
        return new Response($this->id, $replyQueue, [
            'name' => $this->name,
            'args' => $this->args,
            'void' => $this->void,
        ]);
    }
}