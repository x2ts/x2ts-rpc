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
use x2ts\ComponentFactory as X;
use x2ts\rpc\event\AfterCall;

class Response {
    private $id;

    private $q;

    private $callInfo;

    public function __construct(string $id, AMQPQueue $q, $callInfo) {
        $this->id = $id;
        $this->q = $q;
        $this->callInfo = $callInfo;
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
                X::bus()->dispatch(new AfterCall([
                    'dispatcher' => $this,
                    'package'    => null,
                    'func'       => $this->callInfo['name'],
                    'args'       => $this->callInfo['args'],
                    'void'       => false,
                    'result'     => $result,
                    'error'      => $returnInfo['error'],
                    'exception'  => $throw,
                ]));
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