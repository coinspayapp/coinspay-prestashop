<?php
/**
 * Loads the CoinsPay core, and the PrestaShop layer when PrestaShop runs (unit tests load the
 * core alone). Plain require_once: PrestaShop 9 has no autoloader for module classes.
 */
if (!defined('_PS_VERSION_') && !defined('COINSPAY_TESTS')) {
	exit;
}

if (!class_exists('CoinsPay_Plugin', false)) {
	$coinspay_dir = dirname(__FILE__);
	require_once $coinspay_dir . '/Util.php';
	require_once $coinspay_dir . '/ApiError.php';
	require_once $coinspay_dir . '/ApiClient.php';
	require_once $coinspay_dir . '/Signature.php';
	require_once $coinspay_dir . '/Store.php';
	require_once $coinspay_dir . '/Settings.php';
	require_once $coinspay_dir . '/Text.php';
	require_once $coinspay_dir . '/InvoiceRequest.php';
	require_once $coinspay_dir . '/Decision.php';
	require_once $coinspay_dir . '/Repo.php';
	require_once $coinspay_dir . '/Currencies.php';
	require_once $coinspay_dir . '/Tokens.php';
	require_once $coinspay_dir . '/Sync.php';
	require_once $coinspay_dir . '/Payment.php';
	require_once $coinspay_dir . '/Poller.php';
	require_once $coinspay_dir . '/Webhook.php';
	require_once $coinspay_dir . '/Plugin.php';
	if (defined('_PS_VERSION_')) {
		require_once $coinspay_dir . '/prestashop/PsStore.php';
		require_once $coinspay_dir . '/prestashop/States.php';
		require_once $coinspay_dir . '/prestashop/Admin.php';
		require_once $coinspay_dir . '/prestashop/Front.php';
	}
	unset($coinspay_dir);
}
