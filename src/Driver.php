<?php
/**
 * Created by IntelliJ IDEA.
 * User: rek
 * Date: 2017/7/19
 * Time: PM12:46
 */

namespace x2ts\rpc;


abstract class Driver {
    /**
     * @var string
     */
    protected $package;

    /**
     * @param string $package
     *
     * @return $this
     */
    public function setPackage(string $package) {
        $this->package = $package;
        return $this;
    }

    /**
     * @return string
     */
    public function getPackage(): string {
        return $this->package;
    }

    /**
     * @throws PackageNotFoundException
     * @return $this
     */
    abstract public function checkPackage();

    /**
     * @param Request $req
     *
     * @return Receiver
     */
    abstract public function send(Request $req): Receiver;

    /**
     * @param IRequestHandler $handler
     *
     * @return $this
     */
    abstract public function onRequest(IRequestHandler $handler);

    /**
     * @return void
     */
    abstract public function listen();
}