<?php
/**
 * Helper for the CacheObserver observer model
 *
 * @category   Mage
 * @package    Aligent_CacheObserver
 * @author     ModuleCreator
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class Aligent_CacheObserver_Helper_Data extends Mage_Core_Helper_Abstract
{
    /**
     * Return first found child block of specified type, return false if not found
     * @param  Mage_Core_Block_Abstract $oParentBlock
     * @param  object $vType
     * @return bool
     */
    public function getChildByType(Mage_Core_Block_Abstract $oParentBlock, $vType)
    {
        $aChildrenBlocks = $oParentBlock->getChild();
        if (count($aChildrenBlocks)) {
            foreach ($aChildrenBlocks as $oBlock) {
                if ($oBlock instanceof $vType) {
                    return $oBlock;
                } elseif ($oGrandChild = $this->getChildByType($oBlock, $vType)) {
                    return $oGrandChild;
                }
            }
        }
        return false;
    }
}
