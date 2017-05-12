<?php
/**
 * Created by IntelliJ IDEA.
 * User: rek
 * Date: 2016/6/2
 * Time: 上午11:43
 */

namespace x2ts\rpc;


use Exception;

/**
 * Class RPCException
 *
 * Throw when rpc server occurred errors.
 *
 * @package x2ts\rpc
 */
class RPCException extends Exception {
    public $remoteExceptionName;

    public $remoteFile;

    public $remoteLine;

    public $remoteCode;

    public $remoteMessage;

    public $remoteCallStack;

    public function __construct($message = '', $remoteInfo = []) {
        $this->remoteExceptionName = $remoteInfo['name'] ?? '';
        $this->remoteFile = $remoteInfo['file'] ?? '';
        $this->remoteLine = $remoteInfo['line'] ?? 0;
        $this->remoteCode = $remoteInfo['code'] ?? 0;
        $this->remoteMessage = $remoteInfo['msg'] ?? '';
        $this->remoteCallStack = $remoteInfo['trace'] ?? '';
        if (empty($message)) {
            $message = $this->remoteExceptionName . ' thrown in remote file "' . $this->remoteFile
                . '" (line: ' . $this->remoteLine . ') with code ' . $this->remoteCode .
                ' message: ' . $this->remoteMessage . "\n\nRemote Call stack:\n"
                . $this->remoteCallStack;
        }
        parent::__construct($message, 0);
    }
}