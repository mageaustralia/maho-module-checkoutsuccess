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
 * Base block for every success-page slot block. Provides the shared `isEnabled()`
 * gate and an `getOrder()` accessor that resolves from checkout/session.
 *
 * Preview mode: when the admin preview observer has primed the session with
 * a specific order, getOrder() picks it up automatically — no special-case
 * logic in subclasses.
 */
class MageAustralia_CheckoutSuccess_Block_Template extends Mage_Core_Block_Template
{
    protected ?Mage_Sales_Model_Order $_orderCache = null;
    protected bool $_orderResolved = false;

    public function getSuccessHelper(): MageAustralia_CheckoutSuccess_Helper_Data
    {
        /** @var MageAustralia_CheckoutSuccess_Helper_Data $h */
        $h = Mage::helper('mageaustralia_checkoutsuccess');
        return $h;
    }

    public function isEnabled(): bool
    {
        return $this->getSuccessHelper()->isEnabled();
    }

    public function getOrder(): ?Mage_Sales_Model_Order
    {
        if ($this->_orderResolved) {
            return $this->_orderCache;
        }
        $this->_orderResolved = true;

        $orderId = (int) Mage::getSingleton('checkout/session')->getLastOrderId();
        if ($orderId === 0) {
            return $this->_orderCache = null;
        }

        $order = Mage::getModel('sales/order')->load($orderId);
        $this->_orderCache = $order->getId() ? $order : null;
        return $this->_orderCache;
    }

    /**
     * Short-circuit rendering when the module is disabled. Subclasses that
     * need different gating can override.
     */
    #[\Override]
    protected function _toHtml()
    {
        if (!$this->isEnabled()) {
            return '';
        }
        return parent::_toHtml();
    }
}
