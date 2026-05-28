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
 * Config + rendering helpers for MageAustralia_CheckoutSuccess.
 *
 * All config keys live under the `mageaustralia_checkoutsuccess` section.
 */
class MageAustralia_CheckoutSuccess_Helper_Data extends Mage_Core_Helper_Abstract
{
    protected $_moduleName = 'MageAustralia_CheckoutSuccess';

    public const XML_PATH_ENABLED         = 'mageaustralia_checkoutsuccess/general/enabled';
    public const XML_PATH_DETAILED_INFO   = 'mageaustralia_checkoutsuccess/general/detailed_info';
    public const XML_PATH_SHOW_THUMBNAILS = 'mageaustralia_checkoutsuccess/general/show_thumbnails';
    public const XML_PATH_BLOCK_TOP       = 'mageaustralia_checkoutsuccess/general/block_top';
    public const XML_PATH_BLOCK_BOTTOM    = 'mageaustralia_checkoutsuccess/general/block_bottom';
    public const XML_PATH_BOTTOM_HTML     = 'mageaustralia_checkoutsuccess/general/bottom_html';

    public const XML_PATH_SLOT_PREFIX     = 'mageaustralia_checkoutsuccess/mockup/slot_';
    public const XML_PATH_MOCKUPSET_PREF  = 'mageaustralia_checkoutsuccess/mockupsets/block';

    /** @var list<string> */
    public const SLOTS = ['top', 'middleleft', 'middleright', 'bottom'];

    public function isEnabled(?int $storeId = null): bool
    {
        return (bool) Mage::getStoreConfig(self::XML_PATH_ENABLED, $storeId);
    }

    public function isDetailedInfo(?int $storeId = null): bool
    {
        return (bool) Mage::getStoreConfig(self::XML_PATH_DETAILED_INFO, $storeId);
    }

    public function showThumbnails(?int $storeId = null): bool
    {
        return (bool) Mage::getStoreConfig(self::XML_PATH_SHOW_THUMBNAILS, $storeId);
    }

    public function getTopCmsBlockId(?int $storeId = null): int
    {
        return (int) Mage::getStoreConfig(self::XML_PATH_BLOCK_TOP, $storeId);
    }

    public function getBottomCmsBlockId(?int $storeId = null): int
    {
        return (int) Mage::getStoreConfig(self::XML_PATH_BLOCK_BOTTOM, $storeId);
    }

    /**
     * Read a slot's block codes in display order.
     *
     * @return list<string>
     */
    public function getSlotBlocks(string $slot, ?int $storeId = null): array
    {
        if (!in_array($slot, self::SLOTS, true)) {
            return [];
        }
        $csv = trim((string) Mage::getStoreConfig(self::XML_PATH_SLOT_PREFIX . $slot, $storeId));
        if ($csv === '') {
            return [];
        }
        return array_values(array_filter(array_map(trim(...), explode(',', $csv))));
    }

    /**
     * Read one of the mockupsets CMS block selectors (1..4).
     */
    public function getMockupSetBlockId(int $index, ?int $storeId = null): int
    {
        if ($index < 1 || $index > 4) {
            return 0;
        }
        return (int) Mage::getStoreConfig(self::XML_PATH_MOCKUPSET_PREF . $index, $storeId);
    }

    /**
     * Sign a preview order increment_id so the frontend success-page
     * observer can verify the admin intentionally generated the URL,
     * without needing access to the admin session (sessions are
     * area-scoped - admin login is not visible on frontend requests).
     *
     * 8 hex chars of HMAC-SHA256 is plenty for a non-replayable
     * authorisation check on a single increment_id - the secret is
     * Mage's crypt key, which never leaves the server.
     */
    public function signPreviewId(string $incrementId): string
    {
        return substr(
            hash_hmac('sha256', $incrementId, $this->_getSigningSecret()),
            0,
            16,
        );
    }

    public function verifyPreviewSignature(string $incrementId, string $signature): bool
    {
        if ($incrementId === '' || $signature === '') {
            return false;
        }
        return hash_equals($this->signPreviewId($incrementId), $signature);
    }

    protected function _getSigningSecret(): string
    {
        // Crypt key is unique per install, persisted in app/etc/local.xml,
        // and already used by Maho for all symmetric-encryption needs.
        // getNode() is inherited from Mage_Core_Model_Config_Base; not declared
        // on the Mage_Core_Model_Config stub PHPStan sees.
        /** @phpstan-ignore method.notFound */
        return (string) Mage::getConfig()->getNode('global/crypt/key');
    }

    /**
     * Render the bottom_html config value, substituting order placeholders.
     *
     * Values are HTML-escaped before substitution so a customer-controlled
     * field like the order's email address can never break out of the
     * surrounding admin-authored markup (stored-XSS guard). Admins who need
     * an unescaped numeric value (e.g. for a JS `value: {{orderAmount}}`
     * inside a tracking snippet) can rely on `orderId` / `orderAmount` /
     * `orderIncrementId` being numeric or hyphen-separated and therefore
     * unaffected by htmlspecialchars.
     *
     * Returns empty string if no template configured.
     */
    public function renderBottomHtml(?Mage_Sales_Model_Order $order, ?int $storeId = null): string
    {
        $template = (string) Mage::getStoreConfig(self::XML_PATH_BOTTOM_HTML, $storeId);
        if ($template === '') {
            return '';
        }
        if ($order === null || !$order->getId()) {
            // Variables won't resolve without an order. Strip them out
            // rather than leaving raw {{tokens}} in customer-facing HTML.
            return preg_replace('/\{\{[a-zA-Z]+\}\}/', '', $template) ?? '';
        }

        $vars = [
            'orderId'          => htmlspecialchars((string) $order->getId(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            'orderIncrementId' => htmlspecialchars((string) $order->getIncrementId(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            'orderAmount'      => htmlspecialchars((string) $order->getGrandTotal(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            'customerEmail'    => htmlspecialchars((string) $order->getCustomerEmail(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
        ];

        return preg_replace_callback(
            '/\{\{([a-zA-Z]+)\}\}/',
            static fn(array $m): string => $vars[$m[1]] ?? '',
            $template,
        ) ?? '';
    }
}
