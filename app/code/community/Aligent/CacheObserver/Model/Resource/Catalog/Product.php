<?php
/*
 * Rewrite to add caching to Catalog Model objects
 * Based on code from http://www.johannreinke.com/en/2012/04/13/magento-how-to-cache-product-loading/
 */
class Aligent_CacheObserver_Model_Resource_Catalog_Product extends Mage_Catalog_Model_Resource_Product {

    public function load($oProduct, $id, $attributes = array()){

        if (null !== $attributes || !Mage::app()->useCache('catalog_models')) {
            return parent::load($oProduct, $id, $attributes);
        }

        // Caching product data
        Varien_Profiler::start(__METHOD__);
        $storeId = (int) $oProduct->getStoreId();
        $cacheId = "product-$id-$storeId";
        if ($cacheContent = Mage::app()->loadCache($cacheId)) {
            $data = unserialize($cacheContent);
            if (!empty($data)) {
                $oProduct->setData($data);
            }
        } else {
            parent::load($oProduct, $id, $attributes);

            // You can call some heavy methods here

            try {
                $cacheContent = serialize($oProduct->getData());
                $tags = array(
                    Mage_Catalog_Model_Product::CACHE_TAG,
                    Mage_Catalog_Model_Product::CACHE_TAG . '_' . $id
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

    protected function _getLoadAttributesSelect($object, $table)
    {
        try{
            return parent::_getLoadAttributesSelect($object, $table);
        }
        catch(Exception $e){
            Mage::logException($e);
        }
    }

}