<?php
/**
 * Created by IntelliJ IDEA.
 * User: rek
 * Date: 2016/6/2
 * Time: 上午11:40
 */

namespace x2ts\rpc;


use Exception;

/**
 * Class FunctionNotFoundException
 *
 * Throw when the rpc function not exist on the server side
 *
 * @package x2ts\rpc
 */
class UnregisteredFunctionException extends Exception {
}