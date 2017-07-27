<?php
/**
 * Created by IntelliJ IDEA.
 * User: rek
 * Date: 2017/7/25
 * Time: 下午6:46
 */

namespace x2ts\rpc;


interface IRequestHandler {
    public function handle(Request $request): Response;
}