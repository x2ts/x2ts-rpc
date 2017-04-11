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

    /**
     * @return bool|string
     */
    private function findRpcComponentId() {
        /** @var array $componentArr */
        $componentArr = X::conf('component');
        foreach ($componentArr as $name => $conf) {
            if ($conf['class'] === RPC::class) {
                return $name;
            }
        }
        return false;
    }

    /**
     * @param string $name
     * @param array $arguments
     *
     * @return mixed
     */
    public function __call($name, $arguments) {
        Toolkit::trace("Package: {$this->package}");
        $rpc = $this->findRpcComponentId();
        return X::$rpc($this->package)->call($name, ...$arguments);
    }
}