<?php
/**
 * Plugin settings and the map "invoice event → order status" that the store owner sets up:
 * stores have their own order statuses, and the standard ones may be renamed or deleted.
 */
if (!defined('_PS_VERSION_') && !defined('COINSPAY_TESTS')) {
	exit;
}

class CoinsPay_Settings {

	const MIN_LIFETIME = 15;
	const MAX_LIFETIME = 1440;
	const DEFAULT_LIFETIME = 60;

	/** The order status is left as it is; a comment is still written. */
	const NO_CHANGE = 0;

	/** The status of the event is not set or no longer exists. */
	const INVALID = -1;

	/** Stored value "same as Paid". */
	const SAME_AS_PAID = 'paid';

	/** @var array Invoice events in the order of the settings screen. */
	public static $events = array(
		'created', 'pending', 'partial', 'paid', 'paid_late', 'paid_over', 'paid_manual',
		'mismatch', 'paid_cancelled', 'test_foreign', 'expired', 'expired_partial', 'cancelled', 'cancelled_partial',
	);

	/** @var array Events that must change the status ("no change" is not offered). */
	public static $required = array('created', 'paid');

	/** @var array Events that may be "same as Paid". */
	public static $paidLike = array('paid_late', 'paid_over', 'paid_manual');

	/**
	 * @var array Kind of default status of each event: "awaiting" and "review" are the module's
	 *            own order states, "payment" and "canceled" PrestaShop's PS_OS_PAYMENT and
	 *            PS_OS_CANCELED, 0 — no change, "paid" — same as Paid. The PrestaShop layer turns
	 *            the kinds into state ids on install.
	 */
	public static $defaults = array(
		'created' => 'awaiting',
		'pending' => 0,
		'partial' => 0,
		'paid' => 'payment',
		'paid_late' => 'paid',
		'paid_over' => 'paid',
		'paid_manual' => 'paid',
		'mismatch' => 'review',
		'paid_cancelled' => 'review',
		'test_foreign' => 'review',
		'expired' => 'canceled',
		'expired_partial' => 'review',
		'cancelled' => 'canceled',
		'cancelled_partial' => 'review',
	);

	/** @var CoinsPay_Store */
	private $store;

	/** @var array status id → exists, per request */
	private $existing = array();

	public function __construct(CoinsPay_Store $store) {
		$this->store = $store;
	}

	/**
	 * Settings to save on install: the defaults with the kinds turned into state ids; a kind
	 * the shop does not have leaves the event empty, and the settings screen asks for it.
	 *
	 * @param array $states        kind → order state id: awaiting, review, payment, canceled.
	 * @param array $cancelStates  Order states that mean "the shop cancelled the order".
	 * @return array setting key → value
	 */
	public static function installDefaults($states, $cancelStates) {
		$values = array(
			'lifetime' => self::DEFAULT_LIFETIME,
			'auto_return' => 1,
			'test_buyer_only' => 1,
			'stablecoins_only' => 0,
			'buyer_pays_fee' => 0,
			'buyer_pays_network' => 0,
			'debug' => 0,
			'secret' => CoinsPay_Util::randomHex(32),
		);
		foreach (self::$defaults as $event => $value) {
			if (is_string($value) && $value !== self::SAME_AS_PAID) {
				$value = isset($states[$value]) && (int)$states[$value] > 0 ? (int)$states[$value] : '';
			}
			$values['status_' . $event] = $value;
		}
		$cancel = array();
		foreach ($cancelStates as $id) {
			if ((int)$id > 0) {
				$cancel[] = (int)$id;
			}
		}
		$values['cancel_statuses'] = implode(',', array_unique($cancel));
		return $values;
	}

	public function get($key, $default = '') {
		$value = $this->store->setting($key);
		return $value === null || $value === '' ? $default : $value;
	}

	public function flag($key) {
		$value = $this->store->setting($key);
		return !empty($value) && $value !== '0' && $value !== 'off';
	}

	/** The API address when the admin leaves the field empty. */
	const DEFAULT_API_URL = 'https://coinspay.app/api/v1';

	public function apiUrl() {
		$url = rtrim(trim((string)$this->get('api_url')), '/');
		return $url === '' ? self::DEFAULT_API_URL : $url;
	}

	public function apiKey() {
		return trim((string)$this->get('api_key'));
	}

	public function webhookSecret() {
		return trim((string)$this->get('webhook_secret'));
	}

	/** A cpk_test_ key: invoices are paid with a button on the payment page, no coins move. */
	public function isTestMode() {
		return strpos($this->apiKey(), 'cpk_test_') === 0;
	}

	public function configured() {
		return $this->apiUrl() !== '' && $this->apiKey() !== '';
	}

	public function lifetime() {
		return self::clampLifetime($this->get('lifetime', self::DEFAULT_LIFETIME));
	}

	public static function clampLifetime($minutes) {
		$minutes = (int)$minutes;
		if ($minutes <= 0) {
			return self::DEFAULT_LIFETIME;
		}
		return max(self::MIN_LIFETIME, min(self::MAX_LIFETIME, $minutes));
	}

	/** Secret of the plugin's own links (return, cron, test buyer); made on install. */
	public function secret() {
		return (string)$this->get('secret');
	}

	/**
	 * Order status of an invoice event.
	 *
	 * @param string $event
	 * @return int Status id, NO_CHANGE, or INVALID when it is not set or was deleted.
	 */
	public function statusFor($event) {
		$value = (string)$this->get('status_' . $event, '');
		if ($value === self::SAME_AS_PAID && in_array($event, self::$paidLike, true)) {
			return $this->statusFor('paid');
		}
		if ($value === '' || !ctype_digit($value)) {
			return self::INVALID;
		}
		$id = (int)$value;
		if ($id === self::NO_CHANGE) {
			return in_array($event, self::$required, true) ? self::INVALID : self::NO_CHANGE;
		}
		return $this->statusExists($id) ? $id : self::INVALID;
	}

	/**
	 * Order statuses that mean "the store cancelled the order".
	 *
	 * @return array
	 */
	public function cancelStatuses() {
		$ids = array();
		foreach (explode(',', (string)$this->get('cancel_statuses', '')) as $id) {
			if (ctype_digit(trim($id)) && (int)$id > 0) {
				$ids[] = (int)$id;
			}
		}
		return $ids;
	}

	/**
	 * Events whose status is not set or was deleted: shown on the settings screen.
	 *
	 * @return array
	 */
	public function brokenEvents() {
		$broken = array();
		foreach (self::$events as $event) {
			if ($this->statusFor($event) === self::INVALID) {
				$broken[] = $event;
			}
		}
		return $broken;
	}

	public function statusExists($id) {
		$id = (int)$id;
		if (!isset($this->existing[$id])) {
			$this->existing[$id] = $id > 0 && $this->store->statusExists($id);
		}
		return $this->existing[$id];
	}

	public function client() {
		return new CoinsPay_ApiClient($this->apiUrl(), $this->apiKey(), $this->store->userAgent());
	}
}
