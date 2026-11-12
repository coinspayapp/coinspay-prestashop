<?php
/**
 * Unit tests of the CoinsPay core: no PrestaShop, a fake shop where one is needed.
 */
require dirname(__DIR__) . '/vendor/autoload.php';
define('COINSPAY_TESTS', true);
require dirname(__DIR__) . '/coinspay/core/loader.php';
require __DIR__ . '/FakeStore.php';
