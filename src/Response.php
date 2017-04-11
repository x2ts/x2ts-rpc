<?php
/**
 * Created by IntelliJ IDEA.
 * User: rek
 * Date: 2016/5/27
 * Time: 下午4:25
 */

namespace x2ts\rpc;


use AMQPEnvelope;
use AMQPQueue;
use Throwable;

class Response {
    private $id;

    private $q;

    public function __construct(string $id, AMQPQueue $q) {
        $this->id = $id;
        $this->q = $q;
    }

    public function getResponse() {
        $result = null;
        $throw = null;
        $this->q->consume(function (AMQPEnvelope $msg, AMQPQueue $q) use (&$result, &$throw) {
            if ($msg->getCorrelationId() === $this->id) {
                error_clear_last();
                $returnInfo = msgpack_unpack($msg->getBody());
                $error = error_get_last();
                if (!empty($error)) {
                    $throw = new PacketFormatException(
                        'Response packet format error: ' . $error['message'] .
                        ' in file ' . $error['file'] . ' (line: ' . $error['line'] . ')',
                        PacketFormatException::RESPONSE
                    );
                } else {
                    if (isset($returnInfo['exception']['class'])) {
                        $class = __NAMESPACE__ . '\\' . $returnInfo['exception']['class'];
                        $args = $returnInfo['exception']['args'];
                        $throw = new $class(...$args);
                    }
                    $result = $returnInfo['result'];
                }
            }
            $q->ack($msg->getDeliveryTag());
            return false;
        });
        if ($throw instanceof Throwable) {
            throw $throw;
        }
        return $result;
    }
}