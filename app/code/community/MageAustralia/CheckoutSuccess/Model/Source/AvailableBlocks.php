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
 * Source model for the per-slot block picker. The set is intentionally
 * hardcoded - these are the block codes wired up in our frontend layout XML.
 * Adding a block here without also registering it in the layout would leave
 * admins able to "pick" a block that renders nothing on the success page.
 */
class MageAustralia_CheckoutSuccess_Model_Source_AvailableBlocks
{
    /**
     * @return list<array{value: string, label: string}>
     */
    public function toOptionArray(): array
    {
        $helper = Mage::helper('mageaustralia_checkoutsuccess');
        return [
            ['value' => 'checkoutsuccess.thank.you',
                'label' => $helper->__('Thank You message')],
            ['value' => 'checkoutsuccess.quick.register',
                'label' => $helper->__('Quick Register CTA (guests only)')],
            ['value' => 'sales.order.view',
                'label' => $helper->__('Order line items + totals')],
            ['value' => 'sales.order.info',
                'label' => $helper->__('Shipping + billing addresses')],
            ['value' => 'checkoutsuccess.additional',
                'label' => $helper->__('Additional CMS blocks (slots 1-4)')],
            ['value' => 'sales.recurring.profile.schedule',
                'label' => $helper->__('Recurring profile schedule')],
        ];
    }
}
