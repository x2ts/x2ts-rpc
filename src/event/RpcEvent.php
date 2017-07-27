<?php
/**
 * Created by IntelliJ IDEA.
 * User: rek
 * Date: 2017/5/12
 * Time: AM11:11
 */

namespace x2ts\rpc\event;


use x2ts\event\Event;

abstract class RpcEvent extends Event {
    /**
     * @var bool
     */
    public $void;

    /**
     * @var string
     */
    public $package;

    /**
     * @var string
     */
    public $func;

    /**
     * @var array
     */
    public $args;

    /**
     * @var array
     */
    public $meta;

    public function __construct(
        array $props = [
            'dispatcher' => null,
            'void'       => false,
            'package'    => 'common',
            'func'       => '',
            'args'       => [],
            'meta'       => [],
        ]
    ) {
        parent::__construct($props);
    }
}