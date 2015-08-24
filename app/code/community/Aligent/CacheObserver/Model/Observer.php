<?php
/**
 * Observer class which implements caching for blocks without modifying the blocks 
 * themselves.  Based on code from:
 * www.jewelsboutique.com/news/systems/magento-performance-optimization-continued-custom-block-cache-in-magento.html
 * 
 * @category   Aligent
 * @package    Aligent_CacheObserver
 * @author     ModuleCreator
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class Aligent_CacheObserver_Model_Observer
{
    /**
     * Container for the cache lifetime (seconds)
     * @var int
     */
    protected $_iCacheLifetime;

    /**
     * Define the XML configuration paths
     * @var string
     */
    const CACHE_LIFETIME_CONFIG = 'system/cacheobserver/cache_lifetime';
    const ENABLE_CMS_BLOCKS     = 'system/cacheobserver/enable_cms_blocks';
    const ENABLE_CMS_PAGES      = 'system/cacheobserver/enable_cms_pages';
    const ENABLE_CATEGORY_VIEW  = 'system/cacheobserver/enable_category_view';
    const ENABLE_LAYER_VIEW     = 'system/cacheobserver/enable_layer_view';
    const ENABLE_PRODUCT_VIEW   = 'system/cacheobserver/enable_product_view';
    const ENABLE_CUSTOMER_GROUP = 'system/cacheobserver/enable_customer_group';

    /**
     * Define usability parameters
     * @var string
     */
    const PAGE_VAR              = 'p';
    const LIMIT_VAR             = 'limit';

    /**
     * The non-CMS Block you want to cache
     * @var array
     */
    protected $_cacheableBlocks = array();

    protected $_aNeverCacheBlocks = array(
        'Mage_Catalog_Block_Product_Compare_Abstract',
        'Mage_Wishlist_Block_Abstract',
        'Mage_Checkout_Block_Cart_Crosssell'
    );

    /**
     * Instantiate the cache lifetime flag from configuration
     */
    public function __construct()
    {
        $this->_iCacheLifetime = Mage::getStoreConfig(self::CACHE_LIFETIME_CONFIG);
    }

    /**
     * @param  Varien_Event_Observer $observer
     */
    public function customBlockCache(Varien_Event_Observer $observer)
    {
        try {
            if ('add' == Mage::app()->getRequest()->getActionName()) {
                return $this;
            }
            $event = $observer->getEvent();
            $block = $event->getBlock();
            foreach ($this->_aNeverCacheBlocks as $vNeverCacheBlockName) {
                if ($block instanceof $vNeverCacheBlockName) {
                    return $this;
                }
            }

            $class = get_class($block);
            if ($block instanceof Mage_Cms_Block_Block && $block->getBlockId() && Mage::getStoreConfig(self::ENABLE_CMS_BLOCKS)) {
                $block->setData('cache_lifetime', $this->_iCacheLifetime);
                $key = $this->_getSecure(
                    'cms_block_' . $block->getBlockId() . '_store_' . Mage::app()->getStore()->getId()
                );
                
                $block->setData('cache_key', $key);
                $block->setData('cache_tags', array(Mage_Core_Block_Abstract::CACHE_GROUP, Mage_Core_Model_App::CACHE_TAG, Mage_Core_Model_Store::CACHE_TAG, Mage_Cms_Model_Block::CACHE_TAG . '_' . $block->getBlockId()));
                
            } elseif ($block instanceof Mage_Catalog_Block_Product_List_Toolbar) {
                return $this;

            } elseif ($block instanceof Mage_Cms_Block_Page && $block->getPage()->getIdentifier() && Mage::getStoreConfig(self::ENABLE_CMS_PAGES)) {
                $block->setData('cache_lifetime', $this->_iCacheLifetime);
                $key = $this->_getSecure(
                    'cms_page_' . $block->getPage()->getIdentifier() . '_store_' . Mage::app()->getStore()->getId()
                );
                
                $block->setData('cache_key', $key);
                $block->setData('cache_tags', array(Mage_Core_Block_Abstract::CACHE_GROUP, Mage_Core_Model_App::CACHE_TAG, Mage_Core_Model_Store::CACHE_TAG, Mage_Cms_Model_Page::CACHE_TAG . '_' . $block->getPage()->getId()));
            } elseif ($block instanceof Mage_Review_Block_Product_View_List && Mage::getStoreConfig(self::ENABLE_PRODUCT_VIEW)) {
                $iProductId = Mage::registry('orig_product_id') ? Mage::registry('orig_product_id') : Mage::app()->getRequest()->getParam('id');
                $vAlias = $block->getNameInLayout();
                $vReviewToolBarKey=$this->_getReviewToolBarKey();
                $block->setData('cache_lifetime', $this->_iCacheLifetime);
                $block->setData('cache_key', 'review_product_view_list_' . $iProductId . '_' . $this->_getLoggedInKey() . '_store_' . Mage::app()->getStore()->getId() . '_' . Mage::app()->getStore()->getCurrentCurrencyCode() . '_' . $vReviewToolBarKey . $vAlias);
                $block->setData('cache_tags', array(Mage_Core_Block_Abstract::CACHE_GROUP, Mage_Core_Model_App::CACHE_TAG, Mage_Core_Model_Store::CACHE_TAG, Mage_Catalog_Model_Product::CACHE_TAG . '_' . $iProductId));
            } elseif ($block instanceof Mage_Review_Block_Product_View && Mage::getStoreConfig(self::ENABLE_PRODUCT_VIEW)) {
                $iProductId = Mage::registry('orig_product_id') ? Mage::registry('orig_product_id') : Mage::app()->getRequest()->getParam('id');
                $vAlias = $block->getNameInLayout();
                $block->setData('cache_lifetime', $this->_iCacheLifetime);
                $block->setData('cache_key', 'review_product_view_' . $iProductId . '_' . $this->_getLoggedInKey() . '_store_' . Mage::app()->getStore()->getId() . '_' . Mage::app()->getStore()->getCurrentCurrencyCode() . '_' . $vAlias);
                $block->setData('cache_tags', array(Mage_Core_Block_Abstract::CACHE_GROUP, Mage_Core_Model_App::CACHE_TAG, Mage_Core_Model_Store::CACHE_TAG, Mage_Catalog_Model_Product::CACHE_TAG . '_' . $iProductId));
            } elseif ($block instanceof Mage_Catalog_Block_Product_View && Mage::getStoreConfig(self::ENABLE_PRODUCT_VIEW)) {
                $vAlias = $block->getNameInLayout();
                $vReviewToolBarKey = $this->_getReviewToolBarKey();
                $vTemplate = $block->getTemplate();
                $sCachekey = $this->_generateProductCacheKey($observer, Mage::registry('current_product'), $vReviewToolBarKey, $vAlias, $vTemplate);
                $aCacheTags = $this->_generateProductCacheTags($observer, Mage::registry('current_product'));
                $block->setData('cache_lifetime', $this->_iCacheLifetime);
                $block->setData('cache_key', $sCachekey);
                $block->setData('cache_tags', $aCacheTags);
            } elseif ($block instanceof Mage_Catalog_Block_Product_Price && Mage::getStoreConfig(self::ENABLE_PRODUCT_VIEW)) {
                $iProductId = $block->getProduct() ? $block->getProduct()->getId() : Mage::app()->getRequest()->getParam('id');
                $vAlias = $block->getNameInLayout();
                $vTemplate = $block->getTemplate();
                $block->setData('cache_lifetime', $this->_iCacheLifetime);
                $iLoggedIn = (int)Mage::getSingleton('customer/session')->isLoggedIn();
                if ($iLoggedIn && Mage::getStoreConfig(self::ENABLE_CUSTOMER_GROUP)) {
                    $iLoggedIn = Mage::getSingleton('customer/session')->getCustomerGroupId();
                }
                $block->setData('cache_key', 'catalog_product_price_id_' . $iProductId . '_' . $iLoggedIn . '_store_' . Mage::app()->getStore()->getId() . '_' . Mage::app()->getStore()->getCurrentCurrencyCode() . '_' . $vAlias . '_template_' . $vTemplate);
                $block->setData('cache_tags', array(Mage_Core_Block_Abstract::CACHE_GROUP, Mage_Core_Model_App::CACHE_TAG, Mage_Core_Model_Store::CACHE_TAG, Mage_Catalog_Model_Product::CACHE_TAG . '_' . $iProductId));
            } elseif ($block instanceof Mage_Catalog_Block_Product_List && Mage::getStoreConfig(self::ENABLE_CATEGORY_VIEW)) {
                $sCachekey = $this->_generateCategoryCacheKey($observer, 'catalog_category_view');
                $block->setData('cache_lifetime', $this->_iCacheLifetime);
                $block->setData('cache_key', 'catalog_category_list_' . $sCachekey);
                $block->setData('cache_tags', array(Mage_Core_Block_Abstract::CACHE_GROUP, Mage_Core_Model_App::CACHE_TAG, Mage_Core_Model_Store::CACHE_TAG, Mage_Catalog_Model_Product::CACHE_TAG, Mage_Catalog_Model_Category::CACHE_TAG . '_' . Mage::app()->getRequest()->getParam('id')));
            } elseif ($block instanceof Enterprise_TargetRule_Block_Catalog_Product_Item && Mage::getStoreConfig(self::ENABLE_PRODUCT_VIEW)) {
                if ($block->getProduct() !== null) {
                    $iProductId = $block->getProduct()->getId();
                } else {
                    $iProductId = Mage::registry('orig_product_id') ? Mage::registry('orig_product_id') : Mage::app()->getRequest()->getParam('id');
                }
                $block->setData('cache_lifetime', $this->_iCacheLifetime);
                $block->setData('cache_tags', array(Mage_Core_Block_Abstract::CACHE_GROUP, Mage_Core_Model_App::CACHE_TAG, Mage_Core_Model_Store::CACHE_TAG, Mage_Catalog_Model_Product::CACHE_TAG . '_' . $iProductId));
            } elseif ($block instanceof Mage_Catalog_Block_Product_Abstract && Mage::getStoreConfig(self::ENABLE_PRODUCT_VIEW)) {
                if ($block->getProduct() !== null) {
                    $iProductId = $block->getProduct()->getId();
                } else {
                    $iProductId = Mage::registry('orig_product_id') ? Mage::registry('orig_product_id') : Mage::app()->getRequest()->getParam('id');
                }
                $vPageParamKey = $this->_getParamKey(self::PAGE_VAR);
                $vAlias = $block->getNameInLayout();
                $block->setData('cache_lifetime', $this->_iCacheLifetime);
                $block->setData('cache_key', 'catalog_product_abstractview_product_' . $iProductId . '_' . $vPageParamKey . (Mage::getSingleton('customer/session')->isLoggedIn() ? Mage::getSingleton('customer/session')->getCustomerGroupId() : '0') . '_store_' . Mage::app()->getStore()->getId() . '_' . Mage::app()->getStore()->getCurrentCurrencyCode() . '_' . $vAlias);
            } elseif ($block instanceof Mage_Catalog_Block_Category_View && Mage::getStoreConfig(self::ENABLE_CATEGORY_VIEW)) {
                $sCachekey = $this->_generateCategoryCacheKey($observer, 'catalog_category_view');
                $block->setData('cache_lifetime', $this->_iCacheLifetime);
                $block->setData('cache_key', 'catalog_category_view_' . $sCachekey);
                $block->setData('cache_tags', array(Mage_Core_Block_Abstract::CACHE_GROUP, Mage_Core_Model_App::CACHE_TAG, Mage_Core_Model_Store::CACHE_TAG, Mage_Catalog_Model_Product::CACHE_TAG, Mage_Catalog_Model_Category::CACHE_TAG . '_' . Mage::app()->getRequest()->getParam('id')));
            } elseif ($block instanceof Mage_Catalog_Block_Layer_View && Mage::getStoreConfig(self::ENABLE_LAYER_VIEW)) {
                $sCachekey = $this->_generateCategoryCacheKey($observer, 'catalog_category_layered_nav_view');
                $block->setData('cache_lifetime', $this->_iCacheLifetime);
                $block->setData('cache_key', 'catalog_category_layered_nav_view_' . $sCachekey);
                $block->setData('cache_tags', array(Mage_Core_Block_Abstract::CACHE_GROUP, Mage_Core_Model_App::CACHE_TAG, Mage_Core_Model_Store::CACHE_TAG, Mage_Catalog_Model_Product::CACHE_TAG, Mage_Catalog_Model_Category::CACHE_TAG . '_' . Mage::app()->getRequest()->getParam('id')));
            } elseif ($block instanceof Mage_Page_Block_Html_Footer) {
                $aCacheKeyInfo = $block->getCacheKeyInfo();
                $aCacheKeyInfo[] = $block->getTemplate();
                $block->setCacheKey(implode('_', array_values($aCacheKeyInfo)));
            } elseif (in_array($class, $this->_cacheableBlocks)) {
                $block->setData('cache_lifetime', $this->_iCacheLifetime);
                $block->setData('cache_key', 'block_' . $class . '_store_' . Mage::app()->getStore()->getId());
                $block->setData('cache_tags', array(Mage_Core_Block_Abstract::CACHE_GROUP, Mage_Core_Model_App::CACHE_TAG, Mage_Core_Model_Store::CACHE_TAG));
            }
        } catch (Exception $e) {
            Mage::logException($e);
        }
    }

    public function replaceFormKey(Varien_Event_Observer $observer)
    {
        $block = $observer->getBlock();
        $cacheKey = $block->getCacheKey();
        /** @var $session Mage_Core_Model_Session */
        $session = Mage::getSingleton('core/session');
        $cacheData = Mage::app()->loadCache($cacheKey);
        if ($cacheData) {
            $sessionKey = $session->getFormKey();
            if (strpos($cacheData, $sessionKey) !== false) {
                $cacheData = str_replace(
                    $sessionKey,
                    Phoenix_VarnishCache_Model_Observer::FORM_KEY_PLACEHOLDER,
                    $cacheData
                );
                $tags = $block->getCacheTags();
                Mage::app()->saveCache($cacheData, $cacheKey, $tags, $block->getCacheLifetime());
            }
        }
    }

    /**
     * Creates a Key from the request param. This key is used for creating unique Cache Key, and Cache Tag
     * @param  string $vParam
     * @return string
     */
    protected function _getParamKey($vParam)
    {
        $vParamValue = Mage::app()->getRequest()->getParam($vParam, false);
        $vParamKey = ($vParamValue) ? $vParam . '_' . $vParamValue . '_' : '';
        return $vParamKey;
    }

    /**
     * This creates a Key for the Review List ToolBar based on params Page:P and Review Limit:limit.
     * @return string
     */
    protected function _getReviewToolBarKey()
    {
        return $this->_getParamKey(self::PAGE_VAR) . $this->_getParamKey(self::LIMIT_VAR);
    }

    /**
     * Generates the cache key for a category
     * @param  Varien_Event_Observer $observer
     * @param  string                $sKey
     * @return string
     */
    protected function _generateCategoryCacheKey(Varien_Event_Observer $observer, $sKey)
    {
        
        $catId   = Mage::app()->getRequest()->getParam('id');
        $params  = Mage::app()->getRequest()->getParams();
        $logged  = Mage::getSingleton('customer/session')->isLoggedIn() ? 'loggedin' : 'loggedout';
        $filters = '';

        // Use tool-bar attribute (if found) instead of session params
        if ($oToolbar = Mage::helper('cacheobserver')->getChildByType($observer->getBlock(), 'Mage_Catalog_Block_Product_List_Toolbar')) {
            $filters .= 'limit_' . $oToolbar->getLimit()
                      . 'curOrder_' . $oToolbar->getCurrentOrder()
                      . 'curPage_' . $oToolbar->getCurrentPage()
                      . 'curDir_' . $oToolbar->getCurrentDirection()
                      . 'curMode_' . $oToolbar->getCurrentMode();
        } elseif (!isset($params['limit'])) {
            $catalogSession = Mage::getSingleton('catalog/session');

            $sessionParams = array(
                'limit_page'     => 'limit',
                'display_mode'   => 'mode',
                'sort_order'     => 'order',
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

        foreach ($params as $key => $value) {
            $filters .= '_' . $key . ':' . $value;
        }

        $sTemplate = $observer->getBlock()->getTemplate();
        $cacheKey = 'store_' . Mage::app()->getStore()->getId() . "_{$sKey}_id_" . $catId  . '_' . $filters . '_'
                  . $sTemplate . '_' . Mage::app()->getStore()->getCurrentCurrencyCode() . '_' . $logged;
        $cacheKey = md5($cacheKey);

        return $cacheKey;
    }

    /**
     * Create separate cached block for each product, viewed by each customer group (e.g. to cache different
     * tax display rules)
     * @param  Varien_Event_Observer      $obsever
     * @param  Mage_Catalog_Model_Product $oProduct
     * @param  string                     $reviewKey
     * @param  string                     $alias
     * @param  string                     $vTemplate
     * @return string
     */
    protected function _generateProductCacheKey(Varien_Event_Observer $obsever, $oProduct, $reviewKey, $alias, $vTemplate)
    {
        if (!$oProduct) {
            return '';
        }

        $iLoggedIn = (int)Mage::getSingleton('customer/session')->isLoggedIn();
        if ($iLoggedIn && Mage::getStoreConfig(self::ENABLE_CUSTOMER_GROUP)) {
            $iLoggedIn = Mage::getSingleton('customer/session')->getCustomerGroupId();
        }
        return 'catalog_product_page_' . $oProduct->getEntityId() . '_' . $iLoggedIn . '_store_'
               . Mage::app()->getStore()->getId() . '_' . Mage::app()->getStore()->getCurrentCurrencyCode()
               . '_' . $reviewKey . '_' . $alias . '_template_' . $vTemplate;
    }

    /**
     * Accommodated grouped products by adding the tag for each associated product.
     * @param  Varien_Event_Observer      $observer
     * @param  Mage_Catalog_Model_Product $oProduct
     * @return array
     */
    protected function _generateProductCacheTags(Varien_Event_Observer $observer, $oProduct)
    {
        if (!$oProduct) {
            return array();
        }

        $tags = array(
            Mage_Core_Block_Abstract::CACHE_GROUP,
            Mage_Core_Model_App::CACHE_TAG,
            Mage_Core_Model_Store::CACHE_TAG,
            Mage_Catalog_Model_Product::CACHE_TAG . '_' . $oProduct->getEntityId()
        );

        if ($oProduct->getTypeId() == Mage_Catalog_Model_Product_Type::TYPE_GROUPED) {
            $aChildren = $oProduct->getTypeInstance(true)->getAssociatedProducts($oProduct);
            foreach ($aChildren as $oChild) {
                $tags[] = Mage_Catalog_Model_Product::CACHE_TAG.'_'.$oChild->getEntityId();
            }
        }
        
        return $tags;
    }

    /**
     * If Magento is currently secure, prefix the cache key with "secure_"
     * @param  string $key
     * @return string
     */
    protected function _getSecure($key = '')
    {
        if (Mage::app()->getStore()->isCurrentlySecure()) {
            $key = sprintf('secure_%s', $key);
        }
        return $key;
    }

    /**
     * Return a component of the cache key for when a customer is logged in or out
     * @return string
     */
    protected function _getLoggedInKey()
    {
        if (Mage::getSingleton('customer/session')->isLoggedIn()) {
            return 'loggedin';
        }
        return 'loggedout';
    }
}
