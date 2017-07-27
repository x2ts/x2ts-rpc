<?php
/**
 * Created by IntelliJ IDEA.
 * User: rek
 * Date: 2017/7/20
 * Time: PM2:25
 */

namespace x2ts\rpc\driver;


use AMQPQueue;
use x2ts\rpc\event\AfterCall;
use x2ts\rpc\PacketFormatException;
use x2ts\rpc\Receiver;
use x2ts\rpc\Request;
use x2ts\rpc\Response;

class AMQPReceiver extends Receiver {
    private $request;
    private $q;
    public function __construct(Request $request, AMQPQueue $q = null) {
        $this->request = $request;
        $this->q = $q;
    }

    public function getResponse(): Response {
        if ($this->q === null) {
            return new Response();
        }
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
                        $class = class_exists($returnInfo['exception']['class']) ?
                            $returnInfo['exception']['class'] :
                            __NAMESPACE__ . '\\' . $returnInfo['exception']['class'];
                        $args = $returnInfo['exception']['args'];
                        $throw = new $class(...$args);
                    }
                    $result = $returnInfo['result'];
                }
                X::bus()->dispatch(new AfterCall([
                    'dispatcher' => $this,
                    'package'    => null,
                    'func'       => $this->request['name'],
                    'args'       => $this->request['args'],
                    'void'       => false,
                    'result'     => $result,
                    'error'      => $returnInfo['error'],
                    'exception'  => $throw,
                ]));
            }
            $q->ack($msg->getDeliveryTag());
            $q->delete();
            return false;
        });
        if ($throw instanceof Throwable) {
            throw $throw;
        }
        return $result;
    }
}