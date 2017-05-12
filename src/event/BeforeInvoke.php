<?php
/**
 * Created by IntelliJ IDEA.
 * User: rek
 * Date: 2017/5/12
 * Time: AM11:10
 */

namespace x2ts\rpc\event;


class BeforeInvoke extends RpcEvent {
    public static function name(): string {
        return 'x2ts.rpc.BeforeInvoke';
    }
}