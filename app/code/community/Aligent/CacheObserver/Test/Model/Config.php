<?php
/**
 * Config.php
 *
 * @category  Mage
 * @package   Aligent_CacheObserver
 * @author    Luke Mills <luke@aligent.com.au>
 * @copyright 2015 Aligent Consulting.
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 * @link      http://www.aligent.com.au/
 */

// Set up block class hierarchy for testGetObserversByBlockInstance
class Aligent_CacheObserver_Block_Test_Foo {}
class Aligent_CacheObserver_Block_Test_Bar {}
class Aligent_CacheObserver_Block_Test_Qaz {}
class Aligent_CacheObserver_Block_Test_Qux {}
class Aligent_CacheObserver_Block_Test_Zip extends Aligent_CacheObserver_Block_Test_Foo {}
class Aligent_CacheObserver_Block_Test_Zap extends Aligent_CacheObserver_Block_Test_Bar {}
class Aligent_CacheObserver_Block_Test_Zep extends Aligent_CacheObserver_Block_Test_Zap {}
class Aligent_CacheObserver_Block_Test_Zup extends Aligent_CacheObserver_Block_Test_Zep {}
class Aligent_CacheObserver_Block_Test_Qiz extends Aligent_CacheObserver_Block_Test_Qaz {}
class Aligent_CacheObserver_Block_Test_Qoz extends Aligent_CacheObserver_Block_Test_Qiz {}
class Aligent_CacheObserver_Block_Test_Qix extends Aligent_CacheObserver_Block_Test_Qux {}
class Aligent_CacheObserver_Block_Test_Qax extends Aligent_CacheObserver_Block_Test_Qix {}

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
        /** @var Aligent_CacheObserver_Model_Config $config */
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
            $this->assertSame($expectedObserver['model_alias'], $observer['model_alias'], 'Observer model alias mismatch');
            $this->assertSame($expectedObserver['method'], $observer['method'], 'Observer method mismatch');
        }
        $this->assertSame(count($expectedObservers), $i, 'Check loop count in case of real false values in array');
    }

    /**
     * @loadFixture  config
     * @loadFixture  config_config
     * @dataProvider dataProvider
     * @loadExpectation
     */
    public function testGetObserversByBlockInstance($className)
    {
        $config    = $this->_config;
        $blockInstance = new $className();
        $observers = $config->getObserversByBlockInstance($blockInstance);
        $this->assertTrue(is_array($observers), 'Expect observers to be an array.');
        $expectedObservers = $this->expected($className)->getObservers();
        $this->assertSameSize($expectedObservers, $observers);
        // Don't ever expect boolean false values in array. see docs for current().
        for (reset($expectedObservers), reset($observers), $i = 0;
             false !== $expectedObserver = current($expectedObservers),
             false !== $observer = current($observers);
             next($expectedObservers), next($observers), $i++
        ) {
            $this->assertSame($expectedObserver['model_alias'], $observer['model_alias'], 'Observer model alias mismatch');
            $this->assertSame($expectedObserver['method'], $observer['method'], 'Observer method mismatch');
        }
        $this->assertSame(count($expectedObservers), $i, 'Check loop count in case of real false values in array');
    }
}
