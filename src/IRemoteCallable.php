<?php
/**
 * Created by IntelliJ IDEA.
 * User: rek
 * Date: 2016/8/25
 * Time: 下午12:04
 */

namespace x2ts\rpc;


interface IRemoteCallable {
    /**
     * @return array|\Traversable
     */
    public function getRPCMethods();

    /**
     * @param Request $context
     *
     * @return void
     */
    public function setRPCContext(Request $context);
}