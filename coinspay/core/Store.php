<?php
/**
 * What the core needs from PrestaShop. The core itself makes no PrestaShop calls, so it runs
 * in unit tests; CoinsPay_PsStore implements this for 1.6 to 9.x.
 */
if (!defined('_PS_VERSION_') && !defined('COINSPAY_TESTS')) {
	exit;
}

interface CoinsPay_Store {

	/**
	 * Runs SQL; rows for SELECT, an empty array otherwise.
	 *
	 * @param string $sql
	 * @return array
	 */
	public function query($sql);

	/** Escapes a value for SQL inside quotes. */
	public function escape($value);

	/** Table prefix (_DB_PREFIX_). */
	public function prefix();

	/**
	 * Shop the plugin works for. Settings, invoices and the key-value store are kept per shop:
	 * with multistore each shop may have its own CoinsPay shop and key.
	 */
	public function shopId();

	/**
	 * A plugin setting by its key without the module prefix ("api_key", not "COINSPAY_API_KEY").
	 *
	 * @param string $key
	 * @return mixed Null when not set.
	 */
	public function setting($key);

	/** Default currency of the shop (PS_CURRENCY_DEFAULT), ISO code. */
	public function defaultCurrency();

	/** Whether an order status (order state) exists and is not deleted. */
	public function statusExists($statusId);

	/**
	 * The order, or null. With split orders (several orders of one cart, PrestaShop 1.6–8)
	 * the totals are of the whole cart: the buyer pays them with one invoice.
	 *
	 * @param int $orderId
	 * @return array|null {id, reference, status_id, total, default_total, currency_code, ours,
	 *                    items: [{name, quantity}]}. total is in the order currency,
	 *                    default_total in the default currency of the shop; ours — the order
	 *                    is paid with this module.
	 */
	public function order($orderId);

	/**
	 * Writes an event to the order: $statusId is the status to set (the current one for a
	 * comment only); the comment goes to the order as a private note for the shop staff.
	 *
	 * @param int    $orderId
	 * @param int    $statusId
	 * @param string $comment
	 * @return int The order status afterwards: other modules may set another one.
	 */
	public function addHistory($orderId, $statusId, $comment);

	/** Link the buyer comes back by from the payment page. */
	public function returnUrl($orderId, $token);

	/** Address of the webhook route. */
	public function webhookUrl();

	/** Address of the keyed polling route. */
	public function cronUrl($key);

	/** Shop address: part of the idempotency key, so a staging copy never gets the live shop's invoices. */
	public function siteUrl();

	/** Language of order comments and admin messages: en or ru. */
	public function adminLanguage();

	/** Language of the payment page for the buyer: en or ru. */
	public function buyerLanguage();

	/** User-Agent of API requests. */
	public function userAgent();

	/**
	 * Writes to the log of the shop.
	 *
	 * @param string $message
	 * @param bool   $error   Errors are logged always, the rest with the debug setting only.
	 */
	public function log($message, $error);
}
