<?php
/**
 * Created by IntelliJ IDEA.
 * User: rek
 * Date: 2016/6/17
 * Time: 下午2:32
 */

namespace x2ts\rpc;


use ReflectionObject;
use x2ts\Component;
use x2ts\ComponentFactory as X;
use x2ts\Toolkit;

/**
 * Class RPCWrapper
 *
 * @package x2ts\rpc
 */
abstract class RPCWrapper extends Component {
    protected $package = null;

    public function __construct() {
        if ($this->package === null) {
            $this->package = Toolkit::to_snake_case(
                (new ReflectionObject($this))->getShortName()
            );
        }
    }

    public function profile() {
        self::rpc($this->package)->profile();
        return $this;
    }

    protected static $rpcId = '';

    /**
     * @param string $package
     *
     * @return RPC
     * @throws \x2ts\ComponentNotFoundException
     */
    public static function rpc(string $package = 'common') {
        if (empty(self::$rpcId)) {
            /** @var array $componentArr */
            $componentArr = X::conf('component');
            foreach ($componentArr as $name => $conf) {
                if ($conf['class'] === RPC::class) {
                    self::$rpcId = $name;
                    break;
                }
            }
        }

        if (empty($rpc = self::$rpcId)) {
            $rpc = 'rpc';
        }
        return X::$rpc($package);
    }

    /**
     * @param string $name
     * @param array  $arguments
     *
     * @return mixed
     * @throws \x2ts\ComponentNotFoundException
     */
    public function __call($name, $arguments) {
        X::logger()->trace("Package: {$this->package}");
        return static::rpc($this->package)->call($name, ...$arguments);
    }
}