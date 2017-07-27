<?php
/**
 * Created by IntelliJ IDEA.
 * User: rek
 * Date: 2017/7/19
 * Time: PM12:47
 */

namespace x2ts\rpc;


abstract class Receiver {
    abstract public function getResponse(): Response;
}