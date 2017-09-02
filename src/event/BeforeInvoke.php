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

    public function __construct(
        array $props = [
            'dispatcher' => null,
            'package'    => 'common',
            'void'       => false,
            'func'       => '',
            'args'       => [],
            'meta'       => [],
        ]
    ) {
        parent::__construct($props);
    }
}