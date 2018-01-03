<?php
/**
 * Created by IntelliJ IDEA.
 * User: rek
 * Date: 2017/7/27
 * Time: 下午4:32
 */

use x2ts\rpc\driver\AMQP;
use x2ts\rpc\Message;

require_once dirname(__DIR__) . '/vendor/autoload.php';

$rpc = new \x2ts\rpc\RPC('test');
$rpc->saveConf([
    'driver'      => [
        'class' => AMQP::class,
        'conf'  => [
            'host'            => 'localhost',
            'port'            => 5672,
            'login'           => 'guest',
            'password'        => 'guest',
            'vhost'           => '/',
            'read_timeout'    => 6,
            'write_timeout'   => 30,
            'connect_timeout' => 30,
            'persistent'      => false,
            'maxRequest'      => 500,
        ],
    ],
    'messageOpts' => [
        'compressor' => Message::C_NO_COMPRESS,
        'packer'     => Message::P_MSGPACK,
    ],

], 'RPC');

$r = $rpc->call('reverse', 'welcome');
echo $r, "\n";

$r = $rpc->call('sleepFor', 7);
echo $r, "\n";

//$r = $rpc->call('serverSleep', 5);
//echo $r, "\n";
