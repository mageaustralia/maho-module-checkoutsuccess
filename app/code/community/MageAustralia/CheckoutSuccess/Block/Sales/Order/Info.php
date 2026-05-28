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
 * Shipping + billing address summary block on the success page. Subclasses
 * the core Mage_Sales_Block_Order_Info so address renderers + payment-info
 * are available. detailed_info config flag toggles between full address
 * dump and a compact one-line summary in the template.
 */
class MageAustralia_CheckoutSuccess_Block_Sales_Order_Info extends Mage_Sales_Block_Order_Info
{
    protected ?Mage_Sales_Model_Order $_orderCache = null;
    protected bool $_orderResolved = false;

    public function getSuccessHelper(): MageAustralia_CheckoutSuccess_Helper_Data
    {
        /** @var MageAustralia_CheckoutSuccess_Helper_Data $h */
        $h = Mage::helper('mageaustralia_checkoutsuccess');
        return $h;
    }

    /**
     * Parent's declared return is non-null Mage_Sales_Model_Order, but
     * Mage::registry('current_order') is itself effectively nullable in practice,
     * and our success-page flow legitimately has no order yet during early
     * lifecycle calls. _toHtml() / _prepareLayout() below guard on null so the
     * block renders nothing rather than dereferencing a missing order.
     *
     * @phpstan-ignore return.type
     */
    #[Override]
    public function getOrder()
    {
        if ($this->_orderResolved) {
            return $this->_orderCache;
        }
        $this->_orderResolved = true;

        $orderId = (int) Mage::getSingleton('checkout/session')->getLastOrderId();
        if ($orderId === 0) {
            /** @phpstan-ignore return.type */
            return $this->_orderCache = null;
        }
        $order = Mage::getModel('sales/order')->load($orderId);
        $this->_orderCache = $order->getId() ? $order : null;
        /** @phpstan-ignore return.type */
        return $this->_orderCache;
    }

    public function isDetailed(): bool
    {
        return $this->getSuccessHelper()->isDetailedInfo();
    }

    /**
     * Parent block's _prepareLayout() touches getOrder()->getRealOrderId()
     * unconditionally - would fatal when no order is in session. Skip when
     * we have nothing to render.
     *
     * Also registers `current_order` (idempotently) so the payment-info
     * child block, which is created by parent::_prepareLayout via
     * Mage_Payment_Helper_Data::getInfoBlock, can find the order context.
     */
    #[Override]
    protected function _prepareLayout()
    {
        $order = $this->getOrder();
        if (!$order) {
            return $this;
        }
        Mage::register('current_order', $order, true);
        return parent::_prepareLayout();
    }

    #[Override]
    protected function _toHtml()
    {
        if (!$this->getSuccessHelper()->isEnabled() || !$this->getOrder()) {
            return '';
        }
        return parent::_toHtml();
    }
}
