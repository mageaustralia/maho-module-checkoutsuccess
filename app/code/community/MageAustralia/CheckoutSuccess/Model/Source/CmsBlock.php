<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    MageAustralia_CheckoutSuccess
 * @copyright  Copyright (c) 2026 Maho Australia
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Source model that produces a dropdown of every active CMS block in the
 * current store. Used by the top/bottom CMS block selectors and the
 * mockupsets 1..4 selectors.
 */
class MageAustralia_CheckoutSuccess_Model_Source_CmsBlock
{
    /**
     * @return list<array{value: int|string, label: string}>
     */
    public function toOptionArray(): array
    {
        $options = [['value' => 0, 'label' => Mage::helper('mageaustralia_checkoutsuccess')->__('-- None --')]];

        /** @var Mage_Cms_Model_Resource_Block_Collection $collection */
        $collection = Mage::getResourceModel('cms/block_collection')
            ->addFieldToFilter('is_active', 1);

        foreach ($collection as $block) {
            $options[] = [
                'value' => (int) $block->getId(),
                'label' => sprintf('%s (#%d)', (string) $block->getTitle(), (int) $block->getId()),
            ];
        }

        return $options;
    }
}
