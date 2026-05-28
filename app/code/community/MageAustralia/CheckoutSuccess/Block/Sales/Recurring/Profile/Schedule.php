<?php

declare(strict_types=1);

use Maho\DataObject;

/**
 * Maho
 *
 * @package    MageAustralia_CheckoutSuccess
 * @copyright  Copyright (c) 2026 Maho Australia
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Recurring profile schedule summary on the success page. Shown only when
 * the just-placed checkout produced at least one recurring profile (e.g.
 * subscription products). Reads the profile list from checkout/session
 * exactly as Mage_Checkout_Block_Onepage_Success does.
 */
class MageAustralia_CheckoutSuccess_Block_Sales_Recurring_Profile_Schedule extends MageAustralia_CheckoutSuccess_Block_Template
{
    /**
     * @return list<Mage_Sales_Model_Recurring_Profile>
     */
    public function getRecurringProfiles(): array
    {
        $ids = (array) Mage::getSingleton('checkout/session')->getLastRecurringProfileIds();
        if (empty($ids)) {
            return [];
        }
        $collection = Mage::getResourceModel('sales/recurring_profile_collection')
            ->addFieldToFilter('profile_id', ['in' => array_values(array_filter(array_map(intval(...), $ids)))]);
        return iterator_to_array($collection);
    }

    public function canViewProfiles(): bool
    {
        return Mage::getSingleton('customer/session')->isLoggedIn();
    }

    public function getProfileUrl(Mage_Sales_Model_Recurring_Profile $profile): string
    {
        return $this->getUrl('sales/recurring_profile/view', ['profile' => $profile->getId()]);
    }

    /**
     * Signature mirrors Mage_Core_Block_Template::getObjectData() for
     * inheritance compatibility - accepts any Maho\DataObject and returns
     * a string-ish value.
     */
    #[Override]
    public function getObjectData(DataObject $object, $key)
    {
        return (string) $object->getData($key);
    }

    #[Override]
    protected function _toHtml()
    {
        if (!$this->isEnabled() || empty($this->getRecurringProfiles())) {
            return '';
        }
        return parent::_toHtml();
    }
}
