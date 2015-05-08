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
 * Aligent_CacheObserver_Test_Model_Config
 *
 * @category  Aligent
 * @package   Aligent_CacheObserver
 * @author    Luke Mills <luke@aligent.com.au>
 * @copyright 2015 Aligent Consulting.
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 * @link      http://www.aligent.com.au/
 */
class Aligent_CacheObserver_Test_Model_Config extends EcomDev_PHPUnit_Test_Case
{

    protected $_config;

    protected function setUp()
    {
        parent::setUp();
        $this->_config = Mage::getSingleton('cacheobserver/config');
    }

    protected function tearDown()
    {
        parent::tearDown();
        Mage::unregister('_singleton/cacheobserver/config');
    }

    /**
     * @loadFixture  config
     * @loadFixture  config_config
     * @dataProvider dataProvider
     * @loadExpectation
     */
    public function testGetObserversByClassName($className)
    {
        $config    = $this->_config;
        $observers = $config->getObserversByClassName($className);
        $this->assertTrue(is_array($observers), 'Expect observers to be an array.');
        $expectedObservers = $this->expected($className)->getObservers();
        $this->assertSameSize($expectedObservers, $observers);
        // Don't ever expect boolean false values in array. see docs for current().
        for (reset($expectedObservers), reset($observers), $i = 0;
             false !== $expectedObserver = current($expectedObservers),
             false !== $observer = current($observers);
             next($expectedObservers), next($observers), $i++
        ) {
            $this->assertTrue(
                is_callable($observer, true, $observerCallableName),
                'Expect each observer to be callable'
            );
            $this->assertSame($expectedObserver, $observerCallableName);
        }
        $this->assertSame(count($expectedObservers), $i, 'Check loop count in case of real false values in array');
    }
}
