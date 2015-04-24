<?php

/*
 * Observer class which implements caching for blocks without modifying the blocks 
 * themselves.  Based on code from www.jewelsboutique.com/news/systems/magento-performance-optimization-continued-custom-block-cache-in-magento.htmlcd app
 */
class Aligent_CacheObserver_Model_Observer{
    // TODO: Make this to be configurable at Admin Panel
    const CUSTOM_CACHE_LIFETIME = 14400; // 4 hours
    
    const ENABLE_CMS_BLOCKS = 'system/cacheobserver/enable_cms_blocks';
    const ENABLE_CMS_PAGES = 'system/cacheobserver/enable_cms_pages';
    const ENABLE_CATEGORY_VIEW = 'system/cacheobserver/enable_category_view';
    const ENABLE_LAYER_VIEW = 'system/cacheobserver/enable_layer_view';
    const ENABLE_PRODUCT_VIEW = 'system/cacheobserver/enable_product_view';
    const PAGE_VAR='p';
    const LIMIT_VAR='limit';
   
    
    
    // The non-CMS Block you want to cache
    private $cacheableBlocks = array();

    private $aNeverCacheBlocks = array(
        'Mage_Catalog_Block_Product_Compare_Abstract',
        'Mage_Wishlist_Block_Abstract',
    );

    public function customBlockCache(Varien_Event_Observer $observer) {
        try {
            if(Mage::app()->getRequest()->getActionName() == 'add'){
                return $this;
            }
            $event = $observer->getEvent();
            $block = $event->getBlock();
            foreach($this->aNeverCacheBlocks as $vNeverCacheBlockName){
                if($block instanceof $vNeverCacheBlockName){
                    return $this;
                }
            }
            $class = get_class($block);
            if ($block instanceof Mage_Cms_Block_Block && $block->getBlockId() && Mage::getStoreConfig(self::ENABLE_CMS_BLOCKS)) {
                $block->setData('cache_lifetime', self::CUSTOM_CACHE_LIFETIME);
                $key = 'cms_block_' . $block->getBlockId() . '_store_' . Mage::app()->getStore()->getId();
                if(Mage::app()->getStore()->isCurrentlySecure()){
                    $key = "secure_" . $key;
                }
                $block->setData('cache_key', $key);
                $block->setData('cache_tags', array(Mage_Core_Block_Abstract::CACHE_GROUP, Mage_Core_Model_App::CACHE_TAG, Mage_Core_Model_Store::CACHE_TAG, Mage_Cms_Model_Block::CACHE_TAG . "_" . $block->getBlockId()));
                
            } elseif ($block instanceof Mage_Cms_Block_Page && $block->getPage()->getIdentifier() && Mage::getStoreConfig(self::ENABLE_CMS_PAGES)) {
                $block->setData('cache_lifetime', self::CUSTOM_CACHE_LIFETIME);
                $key = 'cms_page_' . $block->getPage()->getIdentifier() . '_store_' . Mage::app()->getStore()->getId();
                if(Mage::app()->getStore()->isCurrentlySecure()){
                    $key = "secure_" . $key;
                }
                $block->setData('cache_key', $key);
                $block->setData('cache_tags', array(Mage_Core_Block_Abstract::CACHE_GROUP, Mage_Core_Model_App::CACHE_TAG, Mage_Core_Model_Store::CACHE_TAG, Mage_Cms_Model_Page::CACHE_TAG.'_'.$block->getPage()->getId()));
            } elseif ($block instanceof Mage_Review_Block_Product_View_List && Mage::getStoreConfig(self::ENABLE_PRODUCT_VIEW)) {
                $iProductId = Mage::registry('orig_product_id') ? Mage::registry('orig_product_id') : Mage::app()->getRequest()->getParam('id');
                $vAlias = $block->getNameInLayout();
                $vReviewToolBarKey=$this->getReviewToolBarKey();
                $block->setData('cache_lifetime', self::CUSTOM_CACHE_LIFETIME);
                $block->setData('cache_key', 'review_product_view_list_' . $iProductId.(Mage::getSingleton('customer/session')->isLoggedIn() ? '_loggedin' : '_loggedout') . '_store_' . Mage::app()->getStore()->getId() . '_' . Mage::app()->getStore()->getCurrentCurrencyCode().'_'.$vReviewToolBarKey.$vAlias);
                $block->setData('cache_tags', array(Mage_Core_Block_Abstract::CACHE_GROUP, Mage_Core_Model_App::CACHE_TAG, Mage_Core_Model_Store::CACHE_TAG, Mage_Catalog_Model_Product::CACHE_TAG.'_'.$iProductId));
            } elseif ($block instanceof Mage_Review_Block_Product_View && Mage::getStoreConfig(self::ENABLE_PRODUCT_VIEW)) {
                $iProductId = Mage::registry('orig_product_id') ? Mage::registry('orig_product_id') : Mage::app()->getRequest()->getParam('id');
                $vAlias = $block->getNameInLayout();
                $block->setData('cache_lifetime', self::CUSTOM_CACHE_LIFETIME);
                $block->setData('cache_key', 'review_product_view_' . $iProductId.(Mage::getSingleton('customer/session')->isLoggedIn() ? '_loggedin' : '_loggedout') . '_store_' . Mage::app()->getStore()->getId() . '_' . Mage::app()->getStore()->getCurrentCurrencyCode().'_'.$vAlias);
                $block->setData('cache_tags', array(Mage_Core_Block_Abstract::CACHE_GROUP, Mage_Core_Model_App::CACHE_TAG, Mage_Core_Model_Store::CACHE_TAG, Mage_Catalog_Model_Product::CACHE_TAG.'_'.$iProductId));
            } elseif ($block instanceof Mage_Catalog_Block_Product_View && Mage::getStoreConfig(self::ENABLE_PRODUCT_VIEW)) {
                $iProductId = Mage::registry('orig_product_id') ? Mage::registry('orig_product_id') : Mage::app()->getRequest()->getParam('id');
                $vAlias = $block->getNameInLayout();
                $vReviewToolBarKey=$this->getReviewToolBarKey();
                $block->setData('cache_lifetime', self::CUSTOM_CACHE_LIFETIME);
                $block->setData('cache_key', 'catalog_product_page_' . $iProductId.(Mage::getSingleton('customer/session')->isLoggedIn() ? '_loggedin' : '_loggedout') . '_store_' . Mage::app()->getStore()->getId() . '_' . Mage::app()->getStore()->getCurrentCurrencyCode().'_'.$vReviewToolBarKey.$vAlias);
                $block->setData('cache_tags', array(Mage_Core_Block_Abstract::CACHE_GROUP, Mage_Core_Model_App::CACHE_TAG, Mage_Core_Model_Store::CACHE_TAG, Mage_Catalog_Model_Product::CACHE_TAG.'_'.$iProductId));
            } elseif ($block instanceof Mage_Catalog_Block_Product_Price && Mage::getStoreConfig(self::ENABLE_PRODUCT_VIEW)) {
                $iProductId = $block->getProduct() ? $block->getProduct()->getId() : Mage::app()->getRequest()->getParam('id');
                $vAlias = $block->getNameInLayout();
                $vTemplate = $block->getTemplate();
                $block->setData('cache_lifetime', self::CUSTOM_CACHE_LIFETIME);
                $block->setData('cache_key', 'catalog_product_price_id_' . $iProductId.(Mage::getSingleton('customer/session')->isLoggedIn() ? '_loggedin' : '_loggedout') . '_store_' . Mage::app()->getStore()->getId() . '_' . Mage::app()->getStore()->getCurrentCurrencyCode().'_'.$vAlias.'_template_'.$vTemplate);
                $block->setData('cache_tags', array(Mage_Core_Block_Abstract::CACHE_GROUP, Mage_Core_Model_App::CACHE_TAG, Mage_Core_Model_Store::CACHE_TAG, Mage_Catalog_Model_Product::CACHE_TAG.'_'.$iProductId));
            } elseif ($block instanceof Mage_Catalog_Block_Product_List && Mage::getStoreConfig(self::ENABLE_CATEGORY_VIEW)) {
                $sCachekey = $this->_generateCategoryCacheKey($observer, 'catalog_category_view');
                $block->setData('cache_lifetime', self::CUSTOM_CACHE_LIFETIME);
                $block->setData('cache_key', 'catalog_category_list_' . $sCachekey);
                $block->setData('cache_tags', array(Mage_Core_Block_Abstract::CACHE_GROUP, Mage_Core_Model_App::CACHE_TAG, Mage_Core_Model_Store::CACHE_TAG, Mage_Catalog_Model_Product::CACHE_TAG, Mage_Catalog_Model_Category::CACHE_TAG.'_'.Mage::app()->getRequest()->getParam('id')));
            } elseif ($block instanceof Enterprise_TargetRule_Block_Catalog_Product_Item && Mage::getStoreConfig(self::ENABLE_PRODUCT_VIEW)) {
                if ($block->getProduct() !== null) {
                    $iProductId = $block->getProduct()->getId();
                } else {
                    $iProductId = Mage::registry('orig_product_id') ? Mage::registry('orig_product_id') : Mage::app()->getRequest()->getParam('id');
                }
                $block->setData('cache_lifetime', self::CUSTOM_CACHE_LIFETIME);
                $block->setData('cache_tags', array(Mage_Core_Block_Abstract::CACHE_GROUP, Mage_Core_Model_App::CACHE_TAG, Mage_Core_Model_Store::CACHE_TAG, Mage_Catalog_Model_Product::CACHE_TAG.'_'.$iProductId));
            } elseif ($block instanceof Mage_Catalog_Block_Product_Abstract && Mage::getStoreConfig(self::ENABLE_PRODUCT_VIEW)) {
                if ($block->getProduct() !== null) {
                    $iProductId = $block->getProduct()->getId();
                } else {
                    $iProductId = Mage::registry('orig_product_id') ? Mage::registry('orig_product_id') : Mage::app()->getRequest()->getParam('id');
                }
                $vPageParamKey = $this->getParamKey(self::PAGE_VAR);
                $vAlias = $block->getNameInLayout();
                $sCachekey = $this->_generateProductCacheKey($observer,Mage::registry('current_product'),$vPageParamKey);
                $aCacheTags = $this->_generateProductCacheTags($observer,Mage::registry('current_product'));
                $block->setData('cache_lifetime', self::CUSTOM_CACHE_LIFETIME);
                $block->setData('cache_key', $sCachekey);
                $block->setData('cache_tags', $aCacheTags);
            } elseif ($block instanceof Mage_Catalog_Block_Category_View && Mage::getStoreConfig(self::ENABLE_CATEGORY_VIEW)) {
                $sCachekey = $this->_generateCategoryCacheKey($observer, 'catalog_category_view');
                $block->setData('cache_lifetime', self::CUSTOM_CACHE_LIFETIME);
                $block->setData('cache_key', 'catalog_category_view_' . $sCachekey);
                $block->setData('cache_tags', array(Mage_Core_Block_Abstract::CACHE_GROUP, Mage_Core_Model_App::CACHE_TAG, Mage_Core_Model_Store::CACHE_TAG, Mage_Catalog_Model_Product::CACHE_TAG, Mage_Catalog_Model_Category::CACHE_TAG.'_'.Mage::app()->getRequest()->getParam('id')));
            } elseif ($block instanceof Mage_Catalog_Block_Layer_View && Mage::getStoreConfig(self::ENABLE_LAYER_VIEW)) {
                $sCachekey = $this->_generateCategoryCacheKey($observer, 'catalog_category_layered_nav_view');
                $block->setData('cache_lifetime', self::CUSTOM_CACHE_LIFETIME);
                $block->setData('cache_key', 'catalog_category_layered_nav_view_' . $sCachekey);
                $block->setData('cache_tags', array(Mage_Core_Block_Abstract::CACHE_GROUP, Mage_Core_Model_App::CACHE_TAG, Mage_Core_Model_Store::CACHE_TAG, Mage_Catalog_Model_Product::CACHE_TAG, Mage_Catalog_Model_Category::CACHE_TAG.'_'.Mage::app()->getRequest()->getParam('id')));
            } elseif ($block instanceof Mage_Page_Block_Html_Footer) {
                $aCacheKeyInfo = $block->getCacheKeyInfo();
                $aCacheKeyInfo[] = $block->getTemplate();
                $block->setCacheKey(implode('_', array_values($aCacheKeyInfo)));
            } elseif (in_array($class, $this->cacheableBlocks)) {
                $block->setData('cache_lifetime', self::CUSTOM_CACHE_LIFETIME);
                $block->setData('cache_key', 'block_' . $class . '_store_' . Mage::app()->getStore()->getId());
                $block->setData('cache_tags', array(Mage_Core_Block_Abstract::CACHE_GROUP, Mage_Core_Model_App::CACHE_TAG, Mage_Core_Model_Store::CACHE_TAG));
            }
        } catch (Exception $e) {
            Mage::logException($e);
        }
    }

    /**
     * @param $vParam
     * @return string
     * Creates a Key from the request param. This key is used
     * for creating unique Cache Key, and Cache Tag
     */
    private function getParamKey($vParam){
        $vParamValue=Mage::app()->getRequest()->getParam($vParam,false);
        $vParamKey=$vParamValue? $vParam.'_'.$vParamValue.'_':'';
        return $vParamKey;
    }

    /***
     * @return string
     * This creates a Key for the Review List ToolBar based on params Page:P
     * and Review Limit:limit.
     */
    private function getReviewToolBarKey(){
        $vReviewToolBarKey=$this->getParamKey(self::PAGE_VAR).$this->getParamKey(self::LIMIT_VAR);
        return $vReviewToolBarKey;
    }
    private function _generateCategoryCacheKey(Varien_Event_Observer $observer, $sKey) {
        
        $catId = Mage::app()->getRequest()->getParam('id');
        $params = Mage::app()->getRequest()->getParams();
        $logged = Mage::getSingleton('customer/session')->isLoggedIn() ? 'loggedin' : 'loggedout';
        if(!isset($params['limit'])){
                $catalogSession = Mage::getSingleton('catalog/session');

                $sessionParams = array(
                        'limit_page' => 'limit',
                        'display_mode' => 'mode',
                        'sort_order' => 'order',
                        'sort_direction' => 'dir'
                );

                foreach ($sessionParams as $sessionKey => $paramKey) {
                        if ($catalogSession->hasData($sessionKey)) {
                                $params[$paramKey] = $catalogSession->getData($sessionKey);
                        }
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

    /**
     * Create separate cached block for each product, viewed by each customer group (e.g. to cache different tax display rules)
     */
    private function _generateProductCacheKey(Varien_Event_Observer $obsever, $oProduct, $pageParam){
        if(!$oProduct){
            return '';
        }
        $iCustomerGroup = Mage::getSingleton('customer/session')->isLoggedIn() ? Mage::getSingleton('customer/session')->getCustomerGroupId() : 0;
        return 'catalog_product_abstractview_product_' . $oProduct->getEntityId() . '_' . $pageParam . '_' . $iCustomerGroup;
    }

    /**
     * Accommodated grouped products by adding the tag for each associated product.
     */
    private function _generateProductCacheTags(Varien_Event_Observer $observer, $oProduct){
        if(!$oProduct){
            return array();
        }
        $tags = array(Mage_Core_Block_Abstract::CACHE_GROUP,
                      Mage_Core_Model_App::CACHE_TAG,
                      Mage_Core_Model_Store::CACHE_TAG,
                      Mage_Catalog_Model_Product::CACHE_TAG.'_'.$oProduct->getEntityId());

        if($oProduct->getTypeId() == 'grouped'){
            $aChildren = $oProduct->getTypeInstance(true)->getAssociatedProducts($this->getParentBlock()->getProduct());
            foreach($aChildren as $oChild){
                $tags[] = Mage_Catalog_Model_Product::CACHE_TAG.'_'.$oChild->getEntityId();
            }
        }
        return $tags;
    }
}
