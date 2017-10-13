<?php
/**
 * Created by IntelliJ IDEA.
 * User: rek
 * Date: 2017/7/17
 * Time: PM2:19
 */

namespace x2ts\rpc;


use x2ts\ILogString;

abstract class RemoteException extends \Exception implements ILogString {
    public $remoteExceptionName;

    public $remoteFile;

    public $remoteLine;

    public $remoteCode;

    public $remoteMessage;

    public $remoteCallStack;

    public function __construct(string $message, int $code) {
        parent::__construct($message, $code, null);
        $this->remoteExceptionName = get_class($this);
        $this->remoteFile = $this->getFile();
        $this->remoteLine = $this->getLine();
        $this->remoteCode = $this->getCode();
        $this->remoteMessage = $this->getMessage();
        $this->remoteCallStack = $this->getTraceAsString();
    }

    /**
     * @return array
     */
    public function __sleep() {
        return [
            'remoteExceptionName',
            'remoteFile',
            'remoteLine',
            'remoteCode',
            'remoteMessage',
            'remoteCallStack',
        ];
    }

    public function toLogString(): string {
        return $logMessage = sprintf(
            "%s is thrown at remote file %s(%d) with message: %s\nRemote call stack:\n%s\n" .
            "Local call stack:\n%s\n",
            get_class($this),
            $this->remoteFile,
            $this->remoteLine,
            $this->remoteMessage,
            $this->remoteCallStack,
            $this->getTraceAsString()
        );
    }
}