<?php
/**
 * Config.php
 *
 * @category  Aligent
 * @package   Aligent_CacheObserver
 * @author    Luke Mills <luke@aligent.com.au>
 * @copyright 2015 Aligent Consulting.
 * @license   OSL-3.0
 * @link      http://www.aligent.com.au/
 */

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

    /**
     * A simple smoke test to ensure the unit tests are set up correctly.
     */
    public function testSmoke()
    {
        $this->assertModelAlias('cacheobserver/foo', 'Aligent_CacheObserver_Model_Foo');
    }

}
