<?php
/**
 * Aligent_VaryCookie_Test_Config_Config
 *
 * @category  Aligent
 * @package   Aligent_CacheObserver
 * @author    Luke Mills <luke@aligent.com.au>
 * @copyright 2015 Aligent Consulting.
 * @license   OSL-3.0
 * @link      http://www.aligent.com.au/
 */
class Aligent_CacheObserver_Test_Config_Config extends EcomDev_PHPUnit_Test_Case_Config
{

    /** @var Mage_Core_Model_Config_Element */
    protected static $_origConfigElement;

    /**
     * Instiates the configuration elements
     */
    public static function setUpBeforeClass()
    {
        parent::setUpBeforeClass();
        /** @var Mage_Core_Model_Config_Element $configElement */
        static::$_origConfigElement = Mage::getConfig()
            ->getNode(Aligent_CacheObserver_Model_Config::XML_PATH_CACHEOBSERVER)
            ->asXML();
        Mage::getConfig()->setNode(Aligent_CacheObserver_Model_Config::XML_PATH_CACHEOBSERVER, '', true);
    }

    /**
     * Removes the configuration elements after the unit tests are complete
     */
    public static function tearDownAfterClass()
    {
        $origConfigElement = self::$_origConfigElement;
        if (false !== $origConfigElement) {
            Mage::getConfig()->setNode(
                Aligent_CacheObserver_Model_Config::XML_PATH_CACHEOBSERVER,
                $origConfigElement,
                true
            );
        }
        parent::tearDownAfterClass();
    }

    /**
     * A simple smoke test to ensure the unit tests are set up correctly.
     */
    public function testSmoke()
    {
        $this->assertModelAlias('cacheobserver/foo', 'Aligent_CacheObserver_Model_Foo');
    }

    /**
     * @loadFixture config_config
     */
    public function testCanReadConfigFixture()
    {
        $this->assertConfigNodeValue('cacheObserver/cacheobserver_default/model', 'cacheobserver/default');
        $this->assertConfigNodeSimpleXml(
            'cacheObserver',
            new SimpleXMLElement(<<<'XML'
      <cacheObserver>
          <cacheobserver_default>
              <model>cacheobserver/default</model>
              <method>default</method>
              <classes>
                  <Aligent_CacheObserver_Block_Test_Foo/>
                  <Aligent_CacheObserver_Block_Test_Bar/>
              </classes>
          </cacheobserver_default>
      </cacheObserver>
XML
)
        );
    }
}
