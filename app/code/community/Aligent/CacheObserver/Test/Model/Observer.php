<?php
/**
 * Aligent_CacheObserver_Test_Model_Observer
 *
 * @category  Aligent
 * @package   Aligent_CacheObserver
 * @author    Luke Mills <luke@aligent.com.au>
 * @copyright 2015 Aligent Consulting.
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 * @link      http://www.aligent.com.au/
 */
class Aligent_CacheObserver_Test_Model_Observer extends EcomDev_PHPUnit_Test_Case
{
    /**
     * Initialize the session mock
     */
    protected function setUp()
    {
        parent::setUp();
        $sessionMock = $this->getModelMockBuilder('core/session')
                            ->disableOriginalConstructor() // This one removes session_start and other methods usage
                            ->setMethods(null) // Enables original methods usage, default overrides all methods
                            ->getMock();
        $this->replaceByMock('singleton', 'core/session', $sessionMock);
    }

    /**
     * @loadFixture config
     * @loadFixture observer_config
     */
    public function testObserverCallsCacheObserverMethodWhenBlockMatches()
    {
        /** @var Mage_Core_Block_Abstract $testBlock */
        $testBlock = $this->getBlockMock('core/abstract', array(), true, array(), 'cacheobserver_test/foo');

        $testObserver = $this->getModelMock(
            'StdClass',
            array('testFoo'),
            array(),
            array(),
            'cacheobserver_test/observer_foo'
        );

        $testObserver->expects($this->once())
            ->method('testFoo')
            ->with(new PHPUnit_Framework_Constraint_IsInstanceOf('Aligent_CacheObserver_Test_Block_Foo'));

        $this->replaceByMock('model', 'cacheobserver_test/observer_foo', $testObserver);

        $testBlock->toHtml();
    }

    /**
     * @loadFixture config
     * @loadFixture observer_config
     */
    public function testObserverDoesntCallCacheObserverMethodWhenBlockDoesntMatch()
    {
        /** @var Mage_Core_Block_Abstract $testBlock */
        $testBlock = $this->getBlockMock('core/abstract', array(), true, array(), 'cacheobserver_test/baz');

        $testObserver = $this->getMock(
            'StdClass',
            array('testFoo'),
            array(),
            'Aligent_CacheObserver_Test_Model_Observer_Foo'
        );
        
        $testObserver->expects($this->never())
                     ->method('testFoo');

        $this->replaceByMock('model', 'cacheobserver_test/observer_foo', $testObserver);

        $testBlock->toHtml();
    }
}
