<?php
/**
 * Observer2.php
 *
 * @category  Aligent
 * @package   Aligent_CacheObserver
 * @author    Luke Mills <luke@aligent.com.au>
 * @copyright 2015 Aligent Consulting.
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 * @link      http://www.aligent.com.au/
 */

/**
 * Aligent_CacheObserver_Model_Observer2
 *
 * @category  Aligent
 * @package   Aligent_CacheObserver
 * @author    Luke Mills <luke@aligent.com.au>
 * @copyright 2015 Aligent Consulting.
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 * @link      http://www.aligent.com.au/
 */
class Aligent_CacheObserver_Model_Observer2
{

    public function customBlockCache(Varien_Event_Observer $eventObserver)
    {
        /** @var Mage_Core_Block_Abstract $block */
        $block = $eventObserver->getBlock();

        $cacheObserverConfig = Mage::getSingleton('cacheobserver/config');

        $observers = $cacheObserverConfig->getObserversByBlockInstance($block);

        foreach ($observers as $observer) {
            Mage::getModel($observer['model_alias'])->$observer['method']($block);
        }

    }
}
