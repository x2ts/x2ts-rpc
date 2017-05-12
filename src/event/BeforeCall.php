<?php
/**
 * Created by IntelliJ IDEA.
 * User: rek
 * Date: 2017/5/11
 * Time: PM9:23
 */

namespace x2ts\rpc\event;


class BeforeCall extends RpcEvent {
    public static function name(): string {
        return 'x2ts.rpc.BeforeCall';
    }
}