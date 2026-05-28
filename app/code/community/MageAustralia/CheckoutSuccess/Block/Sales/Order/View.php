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
 * Order line items + totals on the success page. Subclasses the core
 * Mage_Sales_Block_Order_Items so the existing items/totals renderers
 * work - the only customisation is sourcing the order from checkout/session
 * rather than the customer's order history.
 */
class MageAustralia_CheckoutSuccess_Block_Sales_Order_View extends Mage_Sales_Block_Order_Items
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

    /**
     * Core's `sales/order_items` child block and its item renderers read
     * the order from `Mage::registry('current_order')` (the convention used
     * by the My Account → Order View flow). Without that registry entry,
     * the items child block has nothing to iterate over and renders an
     * empty table. We register here so everything downstream finds it.
     *
     * Also short-circuits the parent's _prepareLayout when there's no
     * order in session - otherwise the parent dereferences null.
     */
    #[Override]
    protected function _prepareLayout()
    {
        $order = $this->getOrder();
        if (!$order) {
            return $this;
        }
        // graceful=true → no-op if already registered (e.g. preview observer
        // could have set it, or another block on the page got there first)
        Mage::register('current_order', $order, true);
        parent::_prepareLayout();
        $this->setItems($order->getItemsCollection());
        return $this;
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
