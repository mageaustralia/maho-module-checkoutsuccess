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
 * Seed sensible config defaults so first enable produces a usable layout.
 * Module ships in the "disabled" state; the admin opts in by flipping
 * general/enabled.
 *
 * No schema changes - module is pure config + code.
 *
 * @var Mage_Core_Model_Resource_Setup $this
 */

$this->startSetup();

$defaults = [
    'mageaustralia_checkoutsuccess/general/enabled'         => '0',
    'mageaustralia_checkoutsuccess/general/detailed_info'   => '0',
    'mageaustralia_checkoutsuccess/general/block_top'       => '0',
    'mageaustralia_checkoutsuccess/general/block_bottom'    => '0',
    'mageaustralia_checkoutsuccess/mockup/slot_top'         => '',
    'mageaustralia_checkoutsuccess/mockup/slot_middleleft'  => 'checkoutsuccess.thank.you',
    'mageaustralia_checkoutsuccess/mockup/slot_middleright' => 'checkoutsuccess.quick.register,sales.order.view,sales.order.info,checkoutsuccess.additional',
    'mageaustralia_checkoutsuccess/mockup/slot_bottom'      => 'sales.recurring.profile.schedule',
    'mageaustralia_checkoutsuccess/mockupsets/block1'       => '0',
    'mageaustralia_checkoutsuccess/mockupsets/block2'       => '0',
    'mageaustralia_checkoutsuccess/mockupsets/block3'       => '0',
    'mageaustralia_checkoutsuccess/mockupsets/block4'       => '0',
];

foreach ($defaults as $path => $value) {
    // saveConfig() is inherited from Mage_Core_Model_Config_Base; not declared
    // on the Mage_Core_Model_Config stub PHPStan sees.
    /** @phpstan-ignore method.notFound */
    Mage::getConfig()->saveConfig($path, $value, 'default', 0);
}

$this->endSetup();
