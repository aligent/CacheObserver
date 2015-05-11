<?php
/**
 * Config.php
 *
 * @category  Aligent
 * @package   Aligent_CacheObserver
 * @author    Luke Mills <luke@aligent.com.au>
 * @copyright 2015 Aligent Consulting.
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 * @link      http://www.aligent.com.au/
 */

/**
 * Aligent_CacheObserver_Model_Config
 *
 * @category  Aligent
 * @package   Aligent_CacheObserver
 * @author    Luke Mills <luke@aligent.com.au>
 * @copyright 2015 Aligent Consulting.
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 * @link      http://www.aligent.com.au/
 */
class Aligent_CacheObserver_Model_Config
{

    const XML_PATH_CACHEOBSERVER = 'cacheObserver';

    protected $_observersIndexedByClassName = null;

    /**
     * Returns an array of callable observers for the given block instance.
     * Note, this returns all the observers defined for any class or interface in the block instance's ancestry.
     *
     * @param $blockInstance
     *
     * @return array
     */
    public function getObserversByBlockInstance($blockInstance) {
        $lineage = array_merge(
            array(get_class($blockInstance)),
            class_parents($blockInstance),
            class_implements($blockInstance)
        );
        $observers = array();
        foreach ($lineage as $className) {
            $observers = array_merge($observers, $this->getObserversByClassName($className));
        }
        return $observers;
    }

    /**
     * Returns an array of callable observers for the given class name.
     * Note, this returns the observers for the class as defined in the config.xml. This method does not return
     * observers that were defined for any parent class or interface.
     *
     * @param string $className
     *
     * @return array
     *
     * @throws Exception when the config can't be read.
     */
    public function getObserversByClassName($className)
    {
        $observers = $this->_getObserversIndexedByClass();
        if (!array_key_exists($className, $observers)) {
            return array();
        }
        return $observers[$className];
    }

    protected function _getObserversIndexedByClass()
    {
        if (is_null($observers = &$this->_observersIndexedByClass)) {
            $observers           = array();
            $cacheObserverConfig = Mage::getConfig()->getNode(self::XML_PATH_CACHEOBSERVER);
            if (!$cacheObserverConfig) {
                return $observers;
            }
            foreach ($cacheObserverConfig->children() as $node) {
                $observer = $this->_getObserver($node);
                foreach ($observer['classes'] as $class) {
                    if (!array_key_exists($class, $observers)) {
                        $observers[$class] = array($observer['observer']);
                    } else {
                        $observers[$class] = array_merge($observers[$class], array($observer['observer']));
                    }
                }
            }
        }

        return $observers;
    }

    protected function _getObserver(Mage_Core_Model_Config_Element $node)
    {
        $this->_validateObserverNode($node);
        $observer = array(
            'model_alias' => (string) $node->model,
            'method'      => (string) $node->method
        );
        $classes = array();
        foreach ($node->classes->children() as $classNode) {
            $classes[] = $classNode->getName();
        }
        return array('observer' => $observer, 'classes' => $classes);
    }

    protected function _validateObserverNode(Mage_Core_Model_Config_Element $node)
    {
        $this->_assert(!is_null(Mage::getConfig()->getModelClassName((string) $node->model)), 'Invalid model alias');
        $this->_assert(strlen((string)$node->method) > 0, 'Invalid method name');
        $this->_assert(count($node->classes) > 0, 'Expected class names');
    }

    protected function _assert($test, $message = '') {
        if ($test !== true) {
            throw new Exception($message);
        }
    }
}