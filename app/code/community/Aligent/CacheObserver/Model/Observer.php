<?php

/*
 * Observer class which implements caching for blocks without modifying the blocks 
 * themselves.  Based on code from www.jewelsboutique.com/news/systems/magento-performance-optimization-continued-custom-block-cache-in-magento.htmlcd app
 */
class Aligent_CacheObserver_Model_Observer{
    // You can make this to be configurable at Admin Panel
    const CUSTOM_CACHE_LIFETIME = 3600;
    
    const ENABLE_CMS_BLOCKS = 'system/cacheobserver/enable_cms_blocks';
    const ENABLE_CMS_PAGES = 'system/cacheobserver/enable_cms_pages';
    const ENABLE_CATEGORY_VIEW = 'system/cacheobserver/enable_category_view';
    const ENABLE_PRODUCT_VIEW = 'system/cacheobserver/enable_product_view';
    
    
    // The non-CMS Block you want to cache
    private $cacheableBlocks = array();

    public function customBlockCache(Varien_Event_Observer $observer) {
        try {
            $event = $observer->getEvent();
            $block = $event->getBlock();
            $class = get_class($block);
            if (('Mage_Cms_Block_Block' == $class) && $block->getBlockId() && Mage::getStoreConfig(self::ENABLE_CMS_BLOCKS)) {
                $block->setData('cache_lifetime', self::CUSTOM_CACHE_LIFETIME);
                $block->setData('cache_key', 'cms_block_' . $block->getBlockId() . '_store_' . Mage::app()->getStore()->getId());
                $block->setData('cache_tags', array(Mage_Core_Model_Store::CACHE_TAG, $block->getBlockId()));
                
            } elseif (('Mage_Cms_Block_Page' == $class) && $block->getPage()->getIdentifier() && Mage::getStoreConfig(self::ENABLE_CMS_PAGES)) {
                $block->setData('cache_lifetime', self::CUSTOM_CACHE_LIFETIME);
                $block->setData('cache_key', 'cms_page_' . $block->getPage()->getIdentifier() . '_store_' . Mage::app()->getStore()->getId());
                $block->setData('cache_tags', array(Mage_Core_Model_Store::CACHE_TAG,
                               $block->getPage()->getIdentifier()));
            
            } elseif (('Mage_Catalog_Block_Product_View' == $class && Mage::getStoreConfig(self::ENABLE_PRODUCT_VIEW))) {
                $oProduct = Mage::registry('product');
                $vAlias = $block->getNameInLayout();
                $block->setData('cache_lifetime', self::CUSTOM_CACHE_LIFETIME);
                $block->setData('cache_key', 'catalog_product_page_' . $oProduct->getId().(Mage::getSingleton('customer/session')->isLoggedIn() ? '_loggedin' : '_loggedout') . '_store_' . Mage::app()->getStore()->getId().'_'.$vAlias);
                $block->setData('cache_tags', array(Mage_Core_Model_Store::CACHE_TAG,
                                $oProduct->getId()));
                
            } elseif (('Mage_Catalog_Block_Category_View' == $class && Mage::getStoreConfig(self::ENABLE_CATEGORY_VIEW))) {
                $sCachekey = $this->_generateCategoryCacheKey($observer);
                $block->setData('cache_lifetime', self::CUSTOM_CACHE_LIFETIME);
                $block->setData('cache_key', 'catalog_category_page_' . $sCachekey);
                $block->setData('cache_tags', array(Mage_Core_Model_Store::CACHE_TAG,
                                $sCachekey));
                
            } elseif (in_array($class, $this->cacheableBlocks)) {
                $block->setData('cache_lifetime', self::CUSTOM_CACHE_LIFETIME);
                $block->setData('cache_key', 'block_' . $class . '_store_' . Mage::app()->getStore()->getId());
                $block->setData('cache_tags', array(Mage_Core_Model_Store::CACHE_TAG, $class));
            }
        } catch (Exception $e) {
            Mage::logException(e);
        }
    }
    
    private function _generateCategoryCacheKey(Varien_Event_Observer $observer) {
        
        $catId = Mage::app()->getRequest()->getParam('id');
        $params = Mage::app()->getRequest()->getParams();
        $logged = Mage::getSingleton('customer/session')->isLoggedIn() ? 'loggedin' : 'loggedout';
        if(!isset($params['limit'])){
                if(Mage::getSingleton('catalog/session')->hasData('limit_page')){
                        $params['limit'] = Mage::getSingleton('catalog/session')->getLimitPage();
                }
        }
        unset($params['id']);
        ksort($params);
        $filters = "";
        foreach($params as $key=>$value){
                $filters .= "_" . $key . ":" . $value;
        }
        $sTemplate = $observer->getBlock()->getTemplate();
        $cacheKey = "store_" . Mage::app()->getStore()->getId() . "_catalog_category_view_id_" . $catId . $filters . $sTemplate . '_' . Mage::app()->getStore()->getCurrentCurrencyCode() . '_' . $logged;
        $cacheKey = md5($cacheKey);
        return $cacheKey;
    }
}
