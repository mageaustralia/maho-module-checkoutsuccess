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
 * Guest-only "Create an account with one click" CTA. Hidden once the
 * customer is logged in, or if the order was placed against a registered
 * customer account.
 */
class MageAustralia_CheckoutSuccess_Block_Quick_Register extends MageAustralia_CheckoutSuccess_Block_Template
{
    public function shouldShow(): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }
        if (Mage::getSingleton('customer/session')->isLoggedIn()) {
            return false;
        }
        $order = $this->getOrder();
        if (!$order) {
            return false;
        }
        // Don't offer registration if the guest's email already has a
        // customer account (placed via prior guest checkout, etc.).
        $existing = Mage::getModel('customer/customer')
            ->setWebsiteId((int) Mage::app()->getStore()->getWebsiteId())
            ->loadByEmail((string) $order->getCustomerEmail());

        return !$existing->getId();
    }

    public function getRegisterPostUrl(): string
    {
        return $this->getUrl(
            'mageaustralia_checkoutsuccess/quick/register',
            ['_secure' => true],
        );
    }

    public function getFormKey(): string
    {
        return (string) Mage::getSingleton('core/session')->getFormKey();
    }

    public function getCustomerEmail(): string
    {
        return (string) ($this->getOrder()?->getCustomerEmail() ?? '');
    }

    public function getCustomerFirstname(): string
    {
        return (string) ($this->getOrder()?->getCustomerFirstname() ?? '');
    }

    public function getCustomerLastname(): string
    {
        return (string) ($this->getOrder()?->getCustomerLastname() ?? '');
    }

    /** Suppress output when the CTA shouldn't show. */
    #[\Override]
    protected function _toHtml()
    {
        if (!$this->shouldShow()) {
            return '';
        }
        return parent::_toHtml();
    }
}
