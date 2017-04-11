<?php
/**
 * Created by IntelliJ IDEA.
 * User: rek
 * Date: 2016/6/2
 * Time: 上午11:41
 */

namespace x2ts\rpc;


use Exception;

/**
 * Class PacketFormatException
 * Throw when request or response data packet format error
 *
 * @package x2ts\rpc
 */
class PacketFormatException extends Exception {
    const REQUEST = 0;
    const RESPONSE = 1;
}