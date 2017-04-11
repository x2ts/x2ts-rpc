<?php
/**
 * Created by IntelliJ IDEA.
 * User: rek
 * Date: 2016/8/25
 * Time: 下午12:14
 */

namespace x2ts\rpc;


use ReflectionObject;

trait TPublicRemoteCallable {
    public function getRPCMethods() {
        $rf = new ReflectionObject($this);
        $publicMethods = $rf->getMethods(\ReflectionMethod::IS_PUBLIC);
        foreach ($publicMethods as $method) {
            if ($method->name !== __FUNCTION__) {
                yield $method->name;
            }
        }
    }
}