<?php
/**
 * Created by IntelliJ IDEA.
 * User: rek
 * Date: 2017/5/12
 * Time: AM10:39
 */

namespace x2ts\rpc\event;


class AfterCall extends RpcEvent {
    public static function name(): string {
        return 'x2ts.rpc.AfterCall';
    }

    /**
     * @var mixed
     */
    public $result;

    /**
     * @var array|null
     */
    public $error;

    /**
     * @var \Throwable|null
     */
    public $exception;

    public function __construct(
        array $props = [
            'dispatcher' => null,
            'void'       => false,
            'package'    => 'common',
            'func'       => '',
            'args'       => [],
            'result'     => null,
            'error'      => null,
            'exception'  => null,
        ]
    ) {
        parent::__construct($props);
    }
}