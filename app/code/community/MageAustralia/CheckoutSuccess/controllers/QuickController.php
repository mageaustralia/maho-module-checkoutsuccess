<?php

declare(strict_types=1);

use Maho\Config\Route;

/**
 * Maho
 *
 * @package    MageAustralia_CheckoutSuccess
 * @copyright  Copyright (c) 2026 Maho Australia
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * "Create account for next time" flow on the success page.
 *
 * registerAction creates the account and links ONLY the order in the current
 * checkout session (which the registrant just placed - safe, it is theirs).
 * It deliberately does NOT bulk-associate every guest order sharing the email:
 * a guest checkout accepts any email, so sweeping by email would let someone
 * who entered a victim's address pull the victim's past order history into a
 * fresh account. Instead, when other guest orders share the email we email a
 * signed claim link; clicking it (claimAction) proves inbox ownership and only
 * then links the rest.
 *
 * Login/activation honours the store's "require email confirmation" setting.
 */
class MageAustralia_CheckoutSuccess_QuickController extends Mage_Core_Controller_Front_Action
{
    #[Route('/mageaustralia_checkoutsuccess/quick/register', methods: ['POST'])]
    public function registerAction(): void
    {
        $request = $this->getRequest();
        $checkoutSession = Mage::getSingleton('checkout/session');
        $customerSession = Mage::getSingleton('customer/session');
        $helper = Mage::helper('mageaustralia_checkoutsuccess');

        // CSRF + form key
        if (!$this->_validateFormKey()) {
            $checkoutSession->addError($helper->__('Invalid form key. Please try again.'));
            $this->_redirectToSuccess();
            return;
        }

        if ($customerSession->isLoggedIn()) {
            $this->_redirectToSuccess();
            return;
        }

        $orderId = (int) $checkoutSession->getLastOrderId();
        if ($orderId === 0) {
            $this->_redirectToSuccess();
            return;
        }

        $order = Mage::getModel('sales/order')->load($orderId);
        $email = trim((string) $order->getCustomerEmail());
        if ($email === '') {
            $checkoutSession->addError($helper->__('Order is missing a customer email; cannot register.'));
            $this->_redirectToSuccess();
            return;
        }

        $password = (string) $request->getPost('password', '');
        $confirmation = (string) $request->getPost('confirmation', '');

        if ($password === '' || $password !== $confirmation) {
            $checkoutSession->addError($helper->__('Passwords did not match.'));
            $this->_redirectToSuccess();
            return;
        }
        if (strlen($password) < 8) {
            $checkoutSession->addError($helper->__('Password must be at least 8 characters.'));
            $this->_redirectToSuccess();
            return;
        }

        $websiteId = (int) Mage::app()->getStore()->getWebsiteId();
        $storeId = (int) Mage::app()->getStore()->getId();

        // Reject if email is already a registered customer (defence in depth -
        // the success-page block already hides the CTA in that case).
        $existing = Mage::getModel('customer/customer')->setWebsiteId($websiteId)->loadByEmail($email);
        if ($existing->getId()) {
            $checkoutSession->addError($helper->__('An account already exists for this email - please log in instead.'));
            $this->_redirectToSuccess();
            return;
        }

        try {
            /** @var Mage_Customer_Model_Customer $customer */
            $customer = Mage::getModel('customer/customer');
            $customer->setWebsiteId($websiteId)
                ->setStoreId($storeId)
                ->setEmail($email)
                ->setFirstname((string) $order->getCustomerFirstname())
                ->setLastname((string) $order->getCustomerLastname())
                ->setPassword($password)
                ->setData('is_active', 1);

            // Resource _beforeSave auto-sets a confirmation key when the store
            // requires email confirmation, which blocks login until confirmed.
            $customer->save();
            $customerId = (int) $customer->getId();

            // Link ONLY the current session order - it belongs to this checkout.
            $order->setCustomerId($customerId)
                ->setCustomerIsGuest(0)
                ->setCustomerGroupId((int) $customer->getGroupId())
                ->save();

            // Activation / login honours the store's confirmation setting.
            if ($customer->isConfirmationRequired()) {
                $this->_trySendAccountEmail($customer, 'confirmation', $storeId);
                $customerSession->addSuccess($helper->__(
                    'Your account has been created. Please check your email and click the confirmation link to activate it and sign in.',
                ));
            } else {
                $customerSession->setCustomerAsLoggedIn($customer);
                $this->_trySendAccountEmail($customer, 'registered', $storeId);
                $customerSession->addSuccess($helper->__('Your account has been created.'));
            }

            // Other guest orders sharing this email (the current order is now
            // linked, so it is excluded). Email a signed claim link rather than
            // linking them here - clicking it proves the registrant controls
            // the inbox.
            $otherCount = (int) Mage::getResourceModel('sales/order_collection')
                ->addFieldToFilter('customer_email', $email)
                ->addFieldToFilter('customer_id', ['null' => true])
                ->getSize();

            if ($otherCount > 0 && $this->_trySendClaimEmail($customer, $email, $otherCount, $storeId)) {
                $customerSession->addNotice($helper->__(
                    'We found %d earlier order(s) placed with this email. We have sent a link to %s - click it to add them to your account.',
                    $otherCount,
                    $email,
                ));
            }

            // Logged-in customers land on the order; unconfirmed accounts go to
            // the (gated) success page, since order view requires a session.
            if ($customerSession->isLoggedIn()) {
                $this->_redirect('sales/order/view', ['order_id' => $orderId]);
            } else {
                $this->_redirectToSuccess();
            }
            return;
        } catch (Throwable $e) {
            // Log the real reason for support; surface only a generic message
            // to avoid leaking internal exception text to customers.
            Mage::logException($e);
            $checkoutSession->addError($helper->__('Could not create your account. Please try again or contact support.'));
            $this->_redirectToSuccess();
            return;
        }
    }

    /**
     * Confirms inbox ownership via a signed link and links every remaining
     * guest order sharing the customer's email. Stateless: the signature binds
     * customer id + current email + expiry, so a forged, expired, or
     * stale-email link is rejected. No login required - the link was emailed to
     * the address being claimed, and the orders only ever attach to the account
     * created for that exact email. Idempotent: already-linked orders are not
     * re-matched (filtered on customer_id IS NULL).
     */
    #[Route('/mageaustralia_checkoutsuccess/quick/claim')]
    public function claimAction(): void
    {
        $request = $this->getRequest();
        $customerSession = Mage::getSingleton('customer/session');
        $helper = Mage::helper('mageaustralia_checkoutsuccess');

        $customerId = (int) $request->getParam('cid');
        $expiry = (int) $request->getParam('exp');
        $signature = trim((string) $request->getParam('sig', ''));

        $customer = $customerId > 0 ? Mage::getModel('customer/customer')->load($customerId) : null;
        $email = $customer && $customer->getId() ? (string) $customer->getEmail() : '';

        if ($email === '' || !$helper->verifyOrderClaim($customerId, $email, $expiry, $signature)) {
            $customerSession->addError($helper->__('This link is invalid or has expired.'));
            $this->_redirect('customer/account');
            return;
        }

        $linked = 0;
        try {
            $groupId = (int) $customer->getGroupId();
            $orders = Mage::getResourceModel('sales/order_collection')
                ->addFieldToFilter('customer_email', $email)
                ->addFieldToFilter('customer_id', ['null' => true]);
            foreach ($orders as $o) {
                $o->setCustomerId($customerId)
                    ->setCustomerIsGuest(0)
                    ->setCustomerGroupId($groupId)
                    ->save();
                $linked++;
            }
        } catch (Throwable $e) {
            Mage::logException($e);
            $customerSession->addError($helper->__('Something went wrong adding your previous orders. Please contact support.'));
            $this->_redirect('customer/account');
            return;
        }

        if ($linked > 0) {
            $customerSession->addSuccess($helper->__('%d previous order(s) have been added to your account.', $linked));
        } else {
            $customerSession->addNotice($helper->__('There were no further orders to add to your account.'));
        }
        $this->_redirect('customer/account');
    }

    protected function _redirectToSuccess(): void
    {
        $this->_redirect('checkout/onepage/success');
    }

    /**
     * Send a standard new-account email without letting a mail failure roll
     * back the (already-committed) account creation.
     */
    protected function _trySendAccountEmail(Mage_Customer_Model_Customer $customer, string $type, int $storeId): void
    {
        try {
            $customer->sendNewAccountEmail($type, '', (string) $storeId);
        } catch (Throwable $e) {
            Mage::logException($e);
        }
    }

    /**
     * Build the signed claim link and email it. Returns true on success.
     */
    protected function _trySendClaimEmail(
        Mage_Customer_Model_Customer $customer,
        string $email,
        int $orderCount,
        int $storeId,
    ): bool {
        try {
            $helper = Mage::helper('mageaustralia_checkoutsuccess');
            $customerId = (int) $customer->getId();
            $expiry = time() + MageAustralia_CheckoutSuccess_Helper_Data::ORDER_CLAIM_TTL;
            $signature = $helper->signOrderClaim($customerId, $email, $expiry);

            $claimUrl = Mage::getUrl('mageaustralia_checkoutsuccess/quick/claim', [
                '_secure' => true,
                '_query'  => ['cid' => $customerId, 'exp' => $expiry, 'sig' => $signature],
            ]);

            /** @var Mage_Core_Model_Email_Template $mail */
            $mail = Mage::getModel('core/email_template');
            $mail->setDesignConfig(['area' => 'frontend', 'store' => $storeId]);
            $mail->loadDefault(MageAustralia_CheckoutSuccess_Helper_Data::CLAIM_EMAIL_TEMPLATE);
            $mail->setSenderName((string) Mage::getStoreConfig('trans_email/ident_general/name', $storeId));
            $mail->setSenderEmail((string) Mage::getStoreConfig('trans_email/ident_general/email', $storeId));

            $name = trim((string) $customer->getFirstname() . ' ' . (string) $customer->getLastname());
            $mail->send($email, $name !== '' ? $name : $email, [
                'customer_name' => (string) $customer->getFirstname(),
                'claim_url'     => $claimUrl,
                'order_count'   => $orderCount,
                'store'         => Mage::app()->getStore($storeId),
            ]);
            return true;
        } catch (Throwable $e) {
            Mage::logException($e);
            return false;
        }
    }
}
