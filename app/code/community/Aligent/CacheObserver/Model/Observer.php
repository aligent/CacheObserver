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
    const ENABLE_LAYER_VIEW = 'system/cacheobserver/enable_layer_view';
    const ENABLE_PRODUCT_VIEW = 'system/cacheobserver/enable_product_view';
    
    
    // The non-CMS Block you want to cache
    private $cacheableBlocks = array();

    public function customBlockCache(Varien_Event_Observer $observer) {
        try {
            $event = $observer->getEvent();
            $block = $event->getBlock();
            $class = get_class($block);
            if ($block instanceof Mage_Cms_Block_Block && $block->getBlockId() && Mage::getStoreConfig(self::ENABLE_CMS_BLOCKS)) {
                $block->setData('cache_lifetime', self::CUSTOM_CACHE_LIFETIME);
                $key = 'cms_block_' . $block->getBlockId() . '_store_' . Mage::app()->getStore()->getId();
                if(Mage::app()->getStore()->isCurrentlySecure()){
                    $key = "secure_" . $key;
                }
                $block->setData('cache_key', $key);
                $block->setData('cache_tags', array('BLOCK_HTML', Mage_Core_Model_Store::CACHE_TAG, Mage_Cms_Model_Block::CACHE_TAG . "_" . $block->getBlockId()));
                
            } elseif ($block instanceof Mage_Cms_Block_Page && $block->getPage()->getIdentifier() && Mage::getStoreConfig(self::ENABLE_CMS_PAGES)) {
                $block->setData('cache_lifetime', self::CUSTOM_CACHE_LIFETIME);
                $key = 'cms_page_' . $block->getPage()->getIdentifier() . '_store_' . Mage::app()->getStore()->getId();
                if(Mage::app()->getStore()->isCurrentlySecure()){
                    $key = "secure_" . $key;
                }
                $block->setData('cache_key', $key);
                $block->setData('cache_tags', array('BLOCK_HTML', Mage_Core_Model_Store::CACHE_TAG, Mage_Cms_Model_Page::CACHE_TAG.'_'.$block->getPage()->getId()));
            
            } elseif ($block instanceof Mage_Catalog_Block_Product_View && Mage::getStoreConfig(self::ENABLE_PRODUCT_VIEW)) {
                $iProductId = Mage::registry('orig_product_id') ? Mage::registry('orig_product_id') : Mage::app()->getRequest()->getParam('id');
                $vAlias = $block->getNameInLayout();
                $block->setData('cache_lifetime', self::CUSTOM_CACHE_LIFETIME);
                $block->setData('cache_key', 'catalog_product_page_' . $iProductId.(Mage::getSingleton('customer/session')->isLoggedIn() ? '_loggedin' : '_loggedout') . '_store_' . Mage::app()->getStore()->getId() . '_' . Mage::app()->getStore()->getCurrentCurrencyCode().'_'.$vAlias);
                $block->setData('cache_tags', array('BLOCK_HTML', Mage_Core_Model_Store::CACHE_TAG, Mage_Catalog_Model_Product::CACHE_TAG.'_'.$iProductId));
            } elseif ($block instanceof Mage_Catalog_Block_Category_View && Mage::getStoreConfig(self::ENABLE_CATEGORY_VIEW)) {
                $sCachekey = $this->_generateCategoryCacheKey($observer, 'catalog_category_view');
                $block->setData('cache_lifetime', self::CUSTOM_CACHE_LIFETIME);
                $block->setData('cache_key', 'catalog_category_view_' . $sCachekey);
                $block->setData('cache_tags', array('BLOCK_HTML', Mage_Core_Model_Store::CACHE_TAG, Mage_Catalog_Model_Product::CACHE_TAG, Mage_Catalog_Model_Category::CACHE_TAG.'_'.Mage::app()->getRequest()->getParam('id')));
            } elseif ($block instanceof Mage_Catalog_Block_Layer_View && Mage::getStoreConfig(self::ENABLE_LAYER_VIEW)) {
                $sCachekey = $this->_generateCategoryCacheKey($observer, 'catalog_category_layered_nav_view');
                $block->setData('cache_lifetime', self::CUSTOM_CACHE_LIFETIME);
                $block->setData('cache_key', 'catalog_category_layered_nav_view_' . $sCachekey);
                $block->setData('cache_tags', array('BLOCK_HTML', Mage_Core_Model_Store::CACHE_TAG, Mage_Catalog_Model_Product::CACHE_TAG, Mage_Catalog_Model_Category::CACHE_TAG.'_'.Mage::app()->getRequest()->getParam('id')));
            } elseif ($block instanceof Mage_Page_Block_Html_Footer) {
                $aCacheKeyInfo = $block->getCacheKeyInfo();
                $aCacheKeyInfo[] = $block->getTemplate();
                $block->setCacheKey(implode('_', array_values($aCacheKeyInfo)));
            } elseif (in_array($class, $this->cacheableBlocks)) {
                $block->setData('cache_lifetime', self::CUSTOM_CACHE_LIFETIME);
                $block->setData('cache_key', 'block_' . $class . '_store_' . Mage::app()->getStore()->getId());
                $block->setData('cache_tags', array('BLOCK_HTML', Mage_Core_Model_Store::CACHE_TAG));
            }
        } catch (Exception $e) {
            Mage::logException(e);
        }
    }
    
    private function _generateCategoryCacheKey(Varien_Event_Observer $observer, $sKey) {
        
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
        $cacheKey = "store_" . Mage::app()->getStore()->getId() . "_{$sKey}_id_" . $catId .'_'.$filters.'_'.$sTemplate . '_' . Mage::app()->getStore()->getCurrentCurrencyCode() . '_' . $logged;
        $cacheKey = md5($cacheKey);
        return $cacheKey;
    }
    
}
