<?php
/**
 * File: app/code/Eticsoft/PaythorClient/registration.php
 *
 * Magento 2 component registration for Eticsoft_PaythorClient.
 * This library module provides the PHP SDK used by Paythor_SanalPosPro.
 */
declare(strict_types=1);

use Magento\Framework\Component\ComponentRegistrar;

ComponentRegistrar::register(
    ComponentRegistrar::MODULE,
    'Eticsoft_PaythorClient',
    __DIR__
);
