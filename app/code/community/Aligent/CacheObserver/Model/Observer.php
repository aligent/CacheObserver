<?php
/**
 * Observer class which implements caching for blocks without modifying the blocks
 * themselves.  Based on code from www.jewelsboutique.com/news/systems/magento-performance-optimization-continued-custom-block-cache-in-magento.html
 *
 * @category   Mage
 * @package    Aligent_CacheObserver
 * @author     ModuleCreator
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class Aligent_CacheObserver_Model_Observer
{
    /**
     * Cache lifetime
     * @var int
     */
    protected $iCacheLifetime;
    protected $iCacheLifetimeFactors = [];
    protected $config = [];
    /**
     * Sets the cache lifetime from store configuration
     */
    public function __construct()
    {
        $this->_initConfig();
        $this->iCacheLifetime = $this->_getConfig(self::CACHE_LIFETIME_CONFIG);
        $this->iCacheLifetimeFactors = json_decode($this->_getConfig(self::CACHE_LIFETIMEFACTORS_CONFIG), true);
    }

    /**
     * XML configuration paths
     * @var string
     */
    const CONFIG                       = 'system/cacheobserver';
    const CACHE_LIFETIME_CONFIG        = 'cache_lifetime';
    const CACHE_LIFETIMEFACTORS_CONFIG = 'cache_lifetime_factor_lookup';
    const CACHE_FORMKEYPLACEHOLDER     = 'cache_formkey_placeholder';
    const CACHE_NEVERBLOCKS            = 'cache_neverblocks';
    const ENABLE_CMS_BLOCKS            = 'enable_cms_blocks';
    const ENABLE_CMS_PAGES             = 'enable_cms_pages';
    const ENABLE_CATEGORY_VIEW         = 'enable_category_view';
    const ENABLE_LAYER_VIEW            = 'enable_layer_view';
    const ENABLE_PRODUCT_VIEW          = 'enable_product_view';
    const ENABLE_CUSTOMER_GROUP        = 'enable_customer_group';
    const DEFAULT_FORM_KEY_PLACEHOLDER = '{{cache_form_key_placeholder}}'; // or Phoenix_VarnishCache_Model_Observer::FORM_KEY_PLACEHOLDER

    /**
     * Pager settings
     * @var string
     */
    const PAGE_VAR  = 'p';
    const LIMIT_VAR = 'limit';

    /**
     * The non-CMS Block you want to cache
     * @var array
     */
    private $cacheableBlocks = array();

    /**
     * Blocks that should never be cached
     * @var array
     */
    protected $_aNeverCacheBlocks = array(
        'Mage_Catalog_Block_Product_Compare_Abstract',
        'Mage_Wishlist_Block_Abstract',
        'Mage_Checkout_Block_Cart_Crosssell'
    );

    /**
     * Just load config in one go into a var
     */
    protected function _initConfig()
    {
        $this->config = Mage::getStoreConfig(self::CONFIG);
        //some defaults:
        if(! $this->_getConfig(self::CACHE_FORMKEYPLACEHOLDER)){
            $this->config[self::CACHE_FORMKEYPLACEHOLDER] = self::DEFAULT_FORM_KEY_PLACEHOLDER;
        }
        $neverCacheBlocks = $this->_getConfig(self::CACHE_NEVERBLOCKS);
        if($neverCacheBlocks) {
            $blocks = explode(',', $neverCacheBlocks);
            if($blocks) {
                $blocks = array_map('trim', $blocks);
                $this->_aNeverCacheBlocks = array_merge($this->_aNeverCacheBlocks, $blocks);
            }
        }
    }

    /**
     * @param $key string
     *
     * @return mixed|null
     */
    protected function _getConfig($key)
    {
        if(isset($this->config[$key])) {
            return $this->config[$key];
        }

        return null;
    }
    /**
     * @param  Varien_Event_Observer $observer
     * @return self
     */
    public function customBlockCache(Varien_Event_Observer $observer)
    {
        if ('add' == Mage::app()->getRequest()->getActionName()) {
            return $this;
        }

        try {
            $event = $observer->getEvent();
            $block = $event->getBlock();
            foreach ($this->_aNeverCacheBlocks as $vNeverCacheBlockName) {
                if ($block instanceof $vNeverCacheBlockName) {
                    return $this;
                }
            }

            $class = get_class($block);
            if ($block instanceof Mage_Cms_Block_Block && $block->getBlockId() && $this->_getConfig(self::ENABLE_CMS_BLOCKS)) {
                $block->setData('cache_lifetime', $this->iCacheLifetime * $this->_getLifeTimeFactorForClass($class));
                $key = 'cms_block_' . $block->getBlockId() . '_store_' . Mage::app()->getStore()->getId();
                if(Mage::app()->getStore()->isCurrentlySecure()){
                    $key = "secure_" . $key;
                }
                $block->setData('cache_key', $key);
                $block->setData(
                    'cache_tags',
                    array(
                        Mage_Core_Block_Abstract::CACHE_GROUP,
                        Mage_Core_Model_App::CACHE_TAG,
                        Mage_Core_Model_Store::CACHE_TAG,
                        Mage_Cms_Model_Block::CACHE_TAG . '_' . $block->getBlockId()
                    )
                );

            } elseif ($block instanceof Mage_Catalog_Block_Product_List_Toolbar) {
                return $this;

            } elseif ($block instanceof Mage_Cms_Block_Page && $block->getPage()->getIdentifier() && $this->_getConfig(self::ENABLE_CMS_PAGES)) {
                $block->setData('cache_lifetime', $this->iCacheLifetime * $this->_getLifeTimeFactorForClass($class));
                $key = 'cms_page_' . $block->getPage()->getIdentifier() . '_store_' . Mage::app()->getStore()->getId();
                if(Mage::app()->getStore()->isCurrentlySecure()){
                    $key = 'secure_' . $key;
                }

                $block->setData('cache_key', $key);
                $block->setData(
                    'cache_tags',
                    array(
                        Mage_Core_Block_Abstract::CACHE_GROUP,
                        Mage_Core_Model_App::CACHE_TAG,
                        Mage_Core_Model_Store::CACHE_TAG,
                        Mage_Cms_Model_Page::CACHE_TAG . '_' . $block->getPage()->getId()
                    )
                );
            } elseif (($block instanceof Mage_Review_Block_Product_View_List)
                      && (Mage::$this->_getConfig(self::ENABLE_PRODUCT_VIEW))
            ) {
                $iProductId = $this->_getProductId();
                $vAlias = $block->getNameInLayout();
                $vReviewToolBarKey=$this->_getReviewToolBarKey();
                $block->setData('cache_lifetime', $this->iCacheLifetime * $this->_getLifeTimeFactorForClass($class));
                $block->setData(
                    'cache_key',
                    'review_product_view_list_' . $iProductId . (Mage::getSingleton('customer/session')->isLoggedIn() ? '_loggedin' : '_loggedout')
                    . '_store_' . Mage::app()->getStore()->getId() . '_' . Mage::app()->getStore()->getCurrentCurrencyCode() . '_' . $vReviewToolBarKey . $vAlias
                );
                $block->setData(
                    'cache_tags',
                    array(
                        Mage_Core_Block_Abstract::CACHE_GROUP,
                        Mage_Core_Model_App::CACHE_TAG,
                        Mage_Core_Model_Store::CACHE_TAG,
                        Mage_Catalog_Model_Product::CACHE_TAG . '_' . $iProductId
                    )
                );
            } elseif ($block instanceof Mage_Review_Block_Product_View && $this->_getConfig(self::ENABLE_PRODUCT_VIEW)) {
                $iProductId = Mage::registry('orig_product_id') ? Mage::registry('orig_product_id') : Mage::app()->getRequest()->getParam('id');
                $vAlias = $block->getNameInLayout();
                $block->setData('cache_lifetime', $this->iCacheLifetime * $this->_getLifeTimeFactorForClass($class));
                $block->setData(
                    'cache_key',
                    'review_product_view_' . $iProductId . (Mage::getSingleton('customer/session')->isLoggedIn() ? '_loggedin' : '_loggedout')
                    . '_store_' . Mage::app()->getStore()->getId() . '_' . Mage::app()->getStore()->getCurrentCurrencyCode() . '_' . $vAlias
                );
                $block->setData(
                    'cache_tags',
                    array(
                        Mage_Core_Block_Abstract::CACHE_GROUP,
                        Mage_Core_Model_App::CACHE_TAG,
                        Mage_Core_Model_Store::CACHE_TAG,
                        Mage_Catalog_Model_Product::CACHE_TAG . '_' . $iProductId
                    )
                );
            } elseif ($block instanceof Mage_Catalog_Block_Product_View && $this->_getConfig(self::ENABLE_PRODUCT_VIEW)) {
                $vAlias = $block->getNameInLayout();
                $vReviewToolBarKey=$this->_getReviewToolBarKey();
                $vTemplate = $block->getTemplate();
                $sCachekey = $this->_generateProductCacheKey($observer,Mage::registry('current_product'),$vReviewToolBarKey,$vAlias,$vTemplate);
                $aCacheTags = $this->_generateProductCacheTags($observer,Mage::registry('current_product'));
                $block->setData('cache_lifetime', $this->iCacheLifetime * $this->_getLifeTimeFactorForClass($class));
                $block->setData('cache_key', $sCachekey);
                $block->setData('cache_tags', $aCacheTags);
            } elseif ($block instanceof Mage_Catalog_Block_Product_Price && $this->_getConfig(self::ENABLE_PRODUCT_VIEW)) {
                $iProductId = $block->getProduct() ? $block->getProduct()->getId() : Mage::app()->getRequest()->getParam('id');
                $vAlias = $block->getNameInLayout();
                $vTemplate = $block->getTemplate();
                $block->setData('cache_lifetime', $this->iCacheLifetime * $this->_getLifeTimeFactorForClass($class));
                $iLoggedIn = (int) Mage::getSingleton('customer/session')->isLoggedIn();
                if ($iLoggedIn && $this->_getConfig(self::ENABLE_CUSTOMER_GROUP)) {
                    $iLoggedIn = Mage::getSingleton('customer/session')->getCustomerGroupId();
                }
                $block->setData(
                    'cache_key',
                    'catalog_product_price_id_' . $iProductId . '_' . $iLoggedIn . '_store_' . Mage::app()->getStore()->getId()
                    . '_' . Mage::app()->getStore()->getCurrentCurrencyCode() . '_' . $vAlias . '_template_' . $vTemplate
                );
                $block->setData(
                    'cache_tags',
                    array(
                        Mage_Core_Block_Abstract::CACHE_GROUP,
                        Mage_Core_Model_App::CACHE_TAG,
                        Mage_Core_Model_Store::CACHE_TAG,
                        Mage_Catalog_Model_Product::CACHE_TAG . '_' . $iProductId
                    )
                );
            } elseif ($block instanceof Mage_Catalog_Block_Product_List && $this->_getConfig(self::ENABLE_CATEGORY_VIEW)) {
                $sCachekey = $this->_generateCategoryCacheKey($observer, 'catalog_category_view');
                $block->setData('cache_lifetime', $this->iCacheLifetime * $this->_getLifeTimeFactorForClass($class));
                $block->setData('cache_key', 'catalog_category_list_' . $sCachekey);
                $block->setData(
                    'cache_tags',
                    array(
                        Mage_Core_Block_Abstract::CACHE_GROUP,
                        Mage_Core_Model_App::CACHE_TAG,
                        Mage_Core_Model_Store::CACHE_TAG,
                        Mage_Catalog_Model_Product::CACHE_TAG,
                        Mage_Catalog_Model_Category::CACHE_TAG . '_' . Mage::app()->getRequest()->getParam('id')
                    )
                );
            } elseif (($block instanceof Enterprise_TargetRule_Block_Catalog_Product_Item)
                      && ($this->_getConfig(self::ENABLE_PRODUCT_VIEW))
            ) {
                if (null !== $block->getProduct()) {
                    $iProductId = $block->getProduct()->getId();
                } else {
                    $iProductId = $this->_getProductId();
                }

                $block->setData('cache_lifetime', $this->iCacheLifetime * $this->_getLifeTimeFactorForClass($class));
                $block->setData(
                    'cache_tags',
                    array(
                        Mage_Core_Block_Abstract::CACHE_GROUP,
                        Mage_Core_Model_App::CACHE_TAG,
                        Mage_Core_Model_Store::CACHE_TAG,
                        Mage_Catalog_Model_Product::CACHE_TAG . '_' . $iProductId
                    )
                );
            } elseif ($block instanceof Mage_Catalog_Block_Product_Abstract &&$this->_getConfig(self::ENABLE_PRODUCT_VIEW)) {
                if ($block->getProduct() !== null) {
                    $iProductId = $block->getProduct()->getId();
                } else {
                    $iProductId = $this->_getProductId();
                }

                $vPageParamKey = $this->_getParamKey(self::PAGE_VAR);
                $vAlias = $block->getNameInLayout();
                $block->setData('cache_lifetime', $this->iCacheLifetime * $this->_getLifeTimeFactorForClass($class));
                $block->setData(
                    'cache_key',
                    'catalog_product_abstractview_product_' . $iProductId . '_' . $vPageParamKey
                    . (Mage::getSingleton('customer/session')->isLoggedIn() ? Mage::getSingleton('customer/session')->getCustomerGroupId() : '0')
                    . '_store_' . Mage::app()->getStore()->getId() . '_' . Mage::app()->getStore()->getCurrentCurrencyCode() . '_' . $vAlias
                );
            } elseif ($block instanceof Mage_Catalog_Block_Category_View && $this->_getConfig(self::ENABLE_CATEGORY_VIEW)) {
                $sCachekey = $this->_generateCategoryCacheKey($observer, 'catalog_category_view');
                $block->setData('cache_lifetime', $this->iCacheLifetime * $this->_getLifeTimeFactorForClass($class));
                $block->setData('cache_key', 'catalog_category_view_' . $sCachekey);
                $block->setData(
                    'cache_tags',
                    array(
                        Mage_Core_Block_Abstract::CACHE_GROUP,
                        Mage_Core_Model_App::CACHE_TAG,
                        Mage_Core_Model_Store::CACHE_TAG,
                        Mage_Catalog_Model_Product::CACHE_TAG,
                        Mage_Catalog_Model_Category::CACHE_TAG . '_' . Mage::app()->getRequest()->getParam('id')
                    )
                );
            } elseif (($block instanceof Mage_Catalog_Block_Layer_View)
                      && ($this->_getConfig(self::ENABLE_LAYER_VIEW))
            ) {
                $sCachekey = $this->_generateCategoryCacheKey($observer, 'catalog_category_layered_nav_view');
                $block->setData('cache_lifetime', $this->iCacheLifetime * $this->_getLifeTimeFactorForClass($class));
                $block->setData('cache_key', 'catalog_category_layered_nav_view_' . $sCachekey);
                $block->setData(
                    'cache_tags',
                    array(
                        Mage_Core_Block_Abstract::CACHE_GROUP,
                        Mage_Core_Model_App::CACHE_TAG,
                        Mage_Core_Model_Store::CACHE_TAG,
                        Mage_Catalog_Model_Product::CACHE_TAG,
                        Mage_Catalog_Model_Category::CACHE_TAG . '_' . Mage::app()->getRequest()->getParam('id')
                    )
                );
            } elseif ($block instanceof Mage_Page_Block_Html_Footer) {
                $aCacheKeyInfo = $block->getCacheKeyInfo();
                $aCacheKeyInfo[] = $block->getTemplate();

                $block->setCacheKey(implode('_', array_values($aCacheKeyInfo)));
            } elseif (in_array($class, $this->cacheableBlocks)) {
                $block->setData('cache_lifetime', $this->iCacheLifetime * $this->_getLifeTimeFactorForClass($class));
                $block->setData('cache_key', 'block_' . $class . '_store_' . Mage::app()->getStore()->getId());
                $block->setData(
                    'cache_tags',
                    array(
                        Mage_Core_Block_Abstract::CACHE_GROUP,
                        Mage_Core_Model_App::CACHE_TAG,
                        Mage_Core_Model_Store::CACHE_TAG
                    )
                );
            }
        } catch (Exception $e) {
            Mage::logException($e);
        }
    }

    /**
     * Replace the form key
     * @param  Varien_Event_Observer $observer
     * @return self
     */
    public function replaceFormKey(Varien_Event_Observer $observer)
    {
        $block = $observer->getBlock();
        $cacheKey = $block->getCacheKey();
        /** @var $session Mage_Core_Model_Session */
        $session = Mage::getSingleton('core/session');
        $cacheData = Mage::app()->loadCache($cacheKey);
        if ($cacheData) {
            $sessionKey = $session->getFormKey();
            if (strpos($cacheData,$sessionKey) !== false) {
                $cacheData = str_replace($sessionKey, $this->_getConfig(self::CACHE_FORMKEYPLACEHOLDER), $cacheData);
                $tags = $block->getCacheTags();
                Mage::app()->saveCache($cacheData, $cacheKey, $tags, $block->getCacheLifetime());
            }
        }
        return $this;
    }


    /**
     * Fetches the current product id from two possible registry keys.
     *
     * @return mixed
     */
    protected function _getProductId() {
        if (Mage::registry('orig_product_id')) {
            return Mage::registry('orig_product_id');
        }
        return Mage::app()->getRequest()->getParam('id');
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
     * Generate a category cache key
     * @param  Varien_Event_Observer $observer
     * @param  string                $sKey
     * @return string
     */
    protected function _generateCategoryCacheKey(Varien_Event_Observer $observer, $sKey)
    {

        $catId  = Mage::app()->getRequest()->getParam('id');
        $params = Mage::app()->getRequest()->getParams();
        $logged = Mage::getSingleton('customer/session')->isLoggedIn() ? 'loggedin' : 'loggedout';
        $filters = '';
        // Use tool-bar attribute (if found) instead of session params
        if ($oToolbar = Mage::helper('cacheobserver')->getChildByType($observer->getBlock(), 'Mage_Catalog_Block_Product_List_Toolbar')) {
            $filters .= 'limit_' . $oToolbar->getLimit()
                .'curOrder_' . $oToolbar->getCurrentOrder()
                .'curPage_' . $oToolbar->getCurrentPage()
                .'curDir_' . $oToolbar->getCurrentDirection()
                .'curMode_' . $oToolbar->getCurrentMode();
        } elseif(!isset($params['limit'])) {
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
        $cacheKey = 'store_' . Mage::app()->getStore()->getId() . "_{$sKey}_id_" . $catId . '_' . $filters
                  . '_' . $sTemplate . '_' . Mage::app()->getStore()->getCurrentCurrencyCode() . '_' . $logged;
        $cacheKey = md5($cacheKey);

        return $cacheKey;
    }

    /**
     * Returns configured lifetime modifier for class
     * @param $class string
     *
     * @return float
     */
    protected  function _getLifeTimeFactorForClass($class)
    {
        if(isset($this->iCacheLifetimeFactors[$class])) {
            return (float) $this->iCacheLifetimeFactors[$class];
        }

        return 1.0;
    }

    /**
     * Create separate cached block for each product, viewed by each customer group (e.g. to cache different tax display rules)
     *
     * @param  Varien_Event_Observer      $observer
     * @param  Mage_Catalog_Model_Product $oProduct
     * @param  string                     $reviewKey
     * @param  string                     $alias
     * @param  string                     $vTemplate
     * @return string
     */
    private function _generateProductCacheKey(Varien_Event_Observer $observer, $oProduct, $reviewKey, $alias, $vTemplate)
    {
        if (!$oProduct) {
            return '';
        }
        $iLoggedIn = (int) Mage::getSingleton('customer/session')->isLoggedIn();
        if ($iLoggedIn && $this->_getConfig(self::ENABLE_CUSTOMER_GROUP)) {
            $iLoggedIn = Mage::getSingleton('customer/session')->getCustomerGroupId();
        }
        return 'catalog_product_page_' . $oProduct->getEntityId() . '_' . $iLoggedIn . '_store_' . Mage::app()->getStore()->getId()
            . '_' . Mage::app()->getStore()->getCurrentCurrencyCode() . '_' . $reviewKey . '_' . $alias . '_template_' . $vTemplate;
    }

    /**
     * Accommodated grouped products by adding the tag for each associated product.
     * @param  Varien_Event_Observer      $observer
     * @param  Mage_Catalog_Model_Product $oProduct
     * @return array
     */
    private function _generateProductCacheTags(Varien_Event_Observer $observer, $oProduct)
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
                $tags[] = Mage_Catalog_Model_Product::CACHE_TAG . '_' . $oChild->getEntityId();
            }
        }
        return $tags;
    }
}
