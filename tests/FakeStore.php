<?php
/**
 * A shop for the unit tests: settings in an array, order states in a list, SQL recorded.
 * State ids follow a fresh PrestaShop: 2 Payment accepted, 6 Canceled, 8 Payment error;
 * 14 and 15 stand for the module's own "awaiting" and "review" states.
 */
class FakeStore implements CoinsPay_Store {

	public $settings = array();
	public $statuses = array(2, 6, 8, 14, 15);
	public $queries = array();
	public $logs = array();

	public function query($sql) {
		$this->queries[] = $sql;
		return array();
	}

	public function escape($value) {
		return addslashes($value);
	}

	public function prefix() {
		return 'ps_';
	}

	public function shopId() {
		return 1;
	}

	public function setting($key) {
		return isset($this->settings[$key]) ? $this->settings[$key] : null;
	}

	public function defaultCurrency() {
		return 'USD';
	}

	public function statusExists($statusId) {
		return in_array((int)$statusId, $this->statuses, true);
	}

	public function order($orderId) {
		return null;
	}

	public function addHistory($orderId, $statusId, $comment) {
		return $statusId;
	}

	public function returnUrl($orderId, $token) {
		return 'https://shop.example/module/coinspay/return?order=' . $orderId . '&token=' . $token;
	}

	public function webhookUrl() {
		return 'https://shop.example/module/coinspay/webhook';
	}

	public function cronUrl($key) {
		return 'https://shop.example/module/coinspay/cron?key=' . $key;
	}

	public function siteUrl() {
		return 'https://shop.example/';
	}

	public function adminLanguage() {
		return 'en';
	}

	public function buyerLanguage() {
		return 'en';
	}

	public function userAgent() {
		return 'CoinsPay-PrestaShop/test';
	}

	public function log($message, $error) {
		$this->logs[] = $message;
	}
}
