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
 * Preview-mode hook. When the success page is requested with both
 * ?previewObjectId=<increment_id> AND ?previewSig=<hmac>, and the signature
 * verifies against the helper's crypt-key-derived secret, prime the
 * checkout session with that historical order's IDs so the success
 * template renders against it.
 *
 * Why HMAC instead of an admin session check: Maho splits sessions by
 * area (frontend / adminhtml are separate Symfony sessions). The admin's
 * login state is invisible to frontend requests, so a session-based
 * gate would always fail. The HMAC is signed by the admin field at URL-
 * generation time with the install's crypt key (which only the server
 * holds), so a leaked URL is the only attack surface — and that surface
 * only exposes one specific order, not arbitrary ones.
 */
class MageAustralia_CheckoutSuccess_Model_Observer
{
    #[Maho\Config\Observer('controller_action_predispatch_checkout_onepage_success')]
    public function loadPreviewOrderIntoSession(\Maho\Event\Observer $observer): void
    {
        /** @var Mage_Core_Controller_Front_Action $action */
        $action = $observer->getEvent()->getControllerAction();
        $request = $action->getRequest();

        $incrementId = trim((string) $request->getParam('previewObjectId', ''));
        $signature = trim((string) $request->getParam('previewSig', ''));
        if ($incrementId === '' || $signature === '') {
            return;
        }

        /** @var MageAustralia_CheckoutSuccess_Helper_Data $helper */
        $helper = Mage::helper('mageaustralia_checkoutsuccess');
        if (!$helper->verifyPreviewSignature($incrementId, $signature)) {
            return;
        }

        $order = Mage::getModel('sales/order')->loadByIncrementId($incrementId);
        if (!$order->getId()) {
            return;
        }

        // DataSync-imported orders have no quote_id; the success controller's
        // gate requires both lastQuoteId and lastSuccessQuoteId to be non-zero,
        // so for preview we synthesize a placeholder from the order's own
        // entity_id when no real quote exists. Blocks only read from the
        // order, so the fake quote id is never dereferenced.
        $quoteId = (int) $order->getQuoteId();
        if ($quoteId === 0) {
            $quoteId = (int) $order->getId();
        }

        $checkoutSession = Mage::getSingleton('checkout/session');
        $checkoutSession->setLastOrderId((int) $order->getId());
        $checkoutSession->setLastRealOrderId((string) $order->getIncrementId());
        $checkoutSession->setLastQuoteId($quoteId);
        $checkoutSession->setLastSuccessQuoteId($quoteId);

        // Register the order globally now (before any block is constructed),
        // so the core sales/order_items child block — which reads from
        // Mage::registry('current_order') during its own _prepareLayout —
        // can see the order in time. Setting it later from our parent
        // block's _prepareLayout is too late for the child's init phase.
        Mage::register('current_order', $order, true);
    }

    /**
     * Mirror of the preview hook for genuine post-checkout requests. After
     * a real checkout, lastOrderId is in session but `current_order` is
     * not registered until late in the controller flow — too late for the
     * items child block's getOrder() to resolve. Registering predispatch
     * keeps the rendering pipeline consistent between preview and real
     * success-page loads.
     */
    #[Maho\Config\Observer('controller_action_predispatch_checkout_onepage_success')]
    public function registerCurrentOrderForRealCheckout(\Maho\Event\Observer $observer): void
    {
        if (Mage::registry('current_order')) {
            return;
        }
        $orderId = (int) Mage::getSingleton('checkout/session')->getLastOrderId();
        if ($orderId === 0) {
            return;
        }
        $order = Mage::getModel('sales/order')->load($orderId);
        if ($order->getId()) {
            Mage::register('current_order', $order, true);
        }
    }
}
