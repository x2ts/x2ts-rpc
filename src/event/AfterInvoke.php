<?php
/**
 * Created by IntelliJ IDEA.
 * User: rek
 * Date: 2017/5/12
 * Time: AM11:12
 */

namespace x2ts\rpc\event;


class AfterInvoke extends RpcEvent {
    public static function name(): string {
        return 'x2ts.rpc.AfterInvoke';
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

    /**
     * @var bool
     */
    public $profileEnabled;

    public function __construct(
        array $props = [
            'dispatcher'     => null,
            'void'           => false,
            'package'        => 'common',
            'func'           => '',
            'profileEnabled' => false,
            'result'         => null,
            'error'          => null,
            'exception'      => null,
        ]
    ) {
        parent::__construct($props);
    }
}