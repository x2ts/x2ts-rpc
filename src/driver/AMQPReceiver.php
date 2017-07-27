<?php
/**
 * Created by IntelliJ IDEA.
 * User: rek
 * Date: 2017/7/20
 * Time: PM2:25
 */

namespace x2ts\rpc\driver;


use AMQPEnvelope;
use AMQPQueue;
use x2ts\ComponentFactory as X;
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

    /**
     * @return mixed
     * @throws \Throwable
     */
    public function fetchReply() {
        if ($this->q === null || $this->request->void) {
            return null;
        }
        $result = null;
        $throw = null;
        $this->q->consume(function (AMQPEnvelope $msg, AMQPQueue $q) use (&$result, &$throw) {
            error_clear_last();
            $res = Response::parse($msg->getBody(), $this->request->package);
//            X::logger()->trace($res);
            $error = error_get_last();
            if (!empty($error)) {
                $throw = new PacketFormatException(
                    'Response packet format error: ' . $error['message'] .
                    ' in file ' . $error['file'] . ' (line: ' . $error['line'] . ')',
                    PacketFormatException::RESPONSE
                );
            } else {
                $throw = $res->exception;
                $result = $res->result;
            }
            X::bus()->dispatch(new AfterCall([
                'dispatcher' => $this,
                'package'    => null,
                'func'       => $this->request->func,
                'args'       => $this->request->args,
                'void'       => $this->request->void,
                'meta'       => $this->request->meta,
                'result'     => $result,
                'error'      => $res->error,
                'exception'  => $throw,
            ]));
            $q->ack($msg->getDeliveryTag());
            $q->delete();
            return false;
        });
        if ($throw instanceof \Throwable) {
            throw $throw;
        }
        return $result;
    }
}