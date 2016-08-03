<?php
/**
 * Rewrite to add caching to Catalog Model objects
 * Based on code from http://www.johannreinke.com/en/2012/04/13/magento-how-to-cache-product-loading/
 *
 * @category   Mage
 * @package    Aligent_CacheObserver
 * @author     ModuleCreator
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class Aligent_CacheObserver_Model_Resource_Catalog_Category extends Mage_Catalog_Model_Resource_Category
{
    /**
     * Loads the category, implementing specific rules for caching
     * @param  Mage_Catalog_Model_Category $oCategory
     * @param  string|int                  $id
     * @param  array                       $attributes
     * @return self
     */
    public function load($oCategory, $id, $attributes = array())
    {
        if (null !== $attributes || !Mage::app()->useCache('catalog_models')) {
            return parent::load($oCategory, $id, $attributes);
        }

        // Caching product data
        Varien_Profiler::start(__METHOD__);
        $storeId = (int) $oCategory->getStoreId();
        $cacheId = "category-$id-$storeId";
        if ($cacheContent = Mage::app()->loadCache($cacheId)) {
            $data = unserialize($cacheContent);
            if (!empty($data)) {
                $oCategory->setData($data);
            }
        } else {
            parent::load($oCategory, $id, $attributes);

            // You can call some heavy methods here
            try {
                $cacheContent = serialize($oCategory->getData());
                $tags = array(
                    Mage_Catalog_Model_Category::CACHE_TAG,
                    Mage_Catalog_Model_Category::CACHE_TAG . '_' . $id
                );
                $lifetime = Mage::getStoreConfig('core/cache/lifetime');
                Mage::app()->saveCache($cacheContent, $cacheId, $tags, $lifetime);
            } catch (Exception $e) {
                // Exception = no caching
                Mage::logException($e);
            }
        }
        Varien_Profiler::stop(__METHOD__);

        return $this;
    }
}
