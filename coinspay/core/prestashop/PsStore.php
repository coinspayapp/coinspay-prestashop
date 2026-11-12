<?php
/**
 * CoinsPay_Store on PrestaShop 1.6 to 9.x. Orders are read with plain SQL: ObjectModel caches
 * objects within a request, and the module re-reads an order right after changing it.
 */
if (!defined('_PS_VERSION_')) {
	exit;
}

class CoinsPay_PsStore implements CoinsPay_Store {

	/** Prefix of the module's keys in Configuration. */
	const CONFIG_PREFIX = 'COINSPAY_';

	/** @var bool The module itself is changing an order status: its status hook skips the change. */
	public static $writing = false;

	/** @var int */
	private $shopId;

	/** @var int */
	private $shopGroupId;

	/** @var Context */
	private $context;

	/**
	 * @param int|null $shopId Shop to work for; the shop of the context when null.
	 */
	public function __construct($shopId = null) {
		$this->context = Context::getContext();
		$this->shopId = $shopId ? (int)$shopId : (int)$this->context->shop->id;
		$this->shopGroupId = (int)Shop::getGroupFromShop($this->shopId, true);
	}

	public function query($sql) {
		$db = Db::getInstance();
		if (preg_match('/^\s*(SELECT|SHOW)\b/i', $sql)) {
			$rows = $db->executeS($sql, true, false);
			if ($rows === false) {
				throw new RuntimeException('database error: ' . $db->getMsgError());
			}
			return is_array($rows) ? $rows : array();
		}
		if (!$db->execute($sql, false)) {
			throw new RuntimeException('database error: ' . $db->getMsgError());
		}
		return array();
	}

	public function escape($value) {
		// html_ok: invoice snapshots are JSON and may hold "<"; strip_tags would break them.
		return Db::getInstance()->escape((string)$value, true);
	}

	public function prefix() {
		return _DB_PREFIX_;
	}

	public function shopId() {
		return $this->shopId;
	}

	public function shopGroupId() {
		return $this->shopGroupId;
	}

	public function setting($key) {
		$value = Configuration::get(self::CONFIG_PREFIX . strtoupper($key), null, $this->shopGroupId, $this->shopId);
		return $value === false ? null : $value;
	}

	/**
	 * Saves settings of this shop.
	 *
	 * @param array $values key without the prefix → value
	 */
	public function saveSettings($values) {
		foreach ($values as $key => $value) {
			Configuration::updateValue(self::CONFIG_PREFIX . strtoupper($key), (string)$value, false, $this->shopGroupId, $this->shopId);
		}
	}

	public function defaultCurrency() {
		$currency = new Currency((int)Configuration::get('PS_CURRENCY_DEFAULT', null, $this->shopGroupId, $this->shopId));
		return Validate::isLoadedObject($currency) ? (string)$currency->iso_code : '';
	}

	public function statusExists($statusId) {
		$rows = $this->query('SELECT id_order_state FROM `' . _DB_PREFIX_ . 'order_state` WHERE id_order_state = ' . (int)$statusId . ' AND deleted = 0 LIMIT 1');
		return !empty($rows);
	}

	public function order($orderId) {
		$main = $this->orderRow($orderId);
		if (!$main) {
			return null;
		}
		$total = 0.0;
		$defaultTotal = 0.0;
		$ids = array();
		foreach ($this->siblingRows($main) as $row) {
			$ids[] = (int)$row['id_order'];
			$total += (float)$row['total_paid_tax_incl'];
			$rate = (float)$row['conversion_rate'];
			$defaultTotal += $rate > 0 ? (float)$row['total_paid_tax_incl'] / $rate : (float)$row['total_paid_tax_incl'];
		}
		$items = array();
		foreach ($this->query('SELECT product_name, product_quantity FROM `' . _DB_PREFIX_ . 'order_detail` WHERE id_order IN (' . implode(',', $ids) . ') ORDER BY id_order_detail') as $row) {
			$items[] = array('name' => $row['product_name'], 'quantity' => (string)(int)$row['product_quantity']);
		}
		$currency = new Currency((int)$main['id_currency']);
		return array(
			'id' => (int)$main['id_order'],
			'reference' => (string)$main['reference'],
			'status_id' => (int)$main['current_state'],
			'total' => $total,
			'default_total' => $defaultTotal,
			'currency_code' => Validate::isLoadedObject($currency) ? (string)$currency->iso_code : '',
			'ours' => $main['module'] === 'coinspay',
			'items' => $items,
			'customer_id' => (int)$main['id_customer'],
			'cart_id' => (int)$main['id_cart'],
			'secure_key' => (string)$main['secure_key'],
		);
	}

	/**
	 * Sets the status on the order and the other orders of its cart (split orders share the
	 * payment), then writes the comment as a private message for the shop staff.
	 */
	public function addHistory($orderId, $statusId, $comment) {
		$main = $this->orderRow($orderId);
		if (!$main) {
			throw new RuntimeException('order ' . (int)$orderId . ' not found');
		}
		$statusId = (int)$statusId;
		self::$writing = true;
		try {
			foreach ($this->siblingRows($main) as $row) {
				if ($statusId > 0 && (int)$row['current_state'] !== $statusId) {
					$order = new Order((int)$row['id_order']);
					$history = new OrderHistory();
					$history->id_order = (int)$order->id;
					$history->id_employee = 0;
					$history->changeIdOrderState($statusId, $order);
					$history->addWithemail(true);
				}
			}
		} catch (Exception $e) {
			self::$writing = false;
			throw $e;
		}
		self::$writing = false;
		if ($comment !== '') {
			$this->note($main, $comment);
		}
		$after = $this->orderRow($orderId);
		return $after ? (int)$after['current_state'] : $statusId;
	}

	/**
	 * A private message on the order: the admin order page shows it, the customer does not.
	 * It goes to the order's customer thread, created closed so it does not wait for an answer
	 * in Customer Service.
	 */
	private function note($order, $comment) {
		$customer = new Customer((int)$order['id_customer']);
		$threadId = (int)CustomerThread::getIdCustomerThreadByEmailAndIdOrder((string)$customer->email, (int)$order['id_order']);
		if (!$threadId) {
			$thread = new CustomerThread();
			$thread->id_contact = 0;
			$thread->id_customer = (int)$order['id_customer'];
			$thread->id_shop = (int)$order['id_shop'];
			$thread->id_order = (int)$order['id_order'];
			$thread->id_lang = (int)$order['id_lang'];
			$thread->email = Validate::isEmail($customer->email) ? $customer->email : 'noreply@coinspay.app';
			$thread->status = 'closed';
			$thread->token = Tools::passwdGen(12);
			$thread->add();
			$threadId = (int)$thread->id;
		}
		$message = new CustomerMessage();
		$message->id_customer_thread = $threadId;
		$message->id_employee = 0;
		$message->message = CoinsPay_Util::truncate($comment, 1600);
		$message->private = 1;
		$message->add();
	}

	private function orderRow($orderId) {
		$rows = $this->query('SELECT id_order, reference, id_cart, id_customer, id_currency, id_shop, id_lang, current_state, module, secure_key, total_paid_tax_incl, conversion_rate FROM `'
			. _DB_PREFIX_ . 'orders` WHERE id_order = ' . (int)$orderId . ' LIMIT 1');
		return $rows ? $rows[0] : null;
	}

	/** The order and the other orders split from the same cart (same reference), the order first. */
	private function siblingRows($main) {
		$rows = $this->query('SELECT id_order, current_state, total_paid_tax_incl, conversion_rate FROM `' . _DB_PREFIX_ . 'orders` WHERE id_cart = ' . (int)$main['id_cart']
			. ' AND reference = \'' . $this->escape($main['reference']) . '\' ORDER BY id_order = ' . (int)$main['id_order'] . ' DESC, id_order');
		return $rows ? $rows : array($main);
	}

	public function returnUrl($orderId, $token) {
		return $this->link('return', array('order' => (int)$orderId, 'token' => $token));
	}

	public function webhookUrl() {
		return $this->link('webhook', array());
	}

	public function cronUrl($key) {
		return $this->link('cron', array('key' => $key));
	}

	/** Link to a front controller of the module in this shop. */
	public function link($controller, $params) {
		$link = $this->context->link ? $this->context->link : new Link();
		return $link->getModuleLink('coinspay', $controller, $params, true, null, $this->shopId);
	}

	public function siteUrl() {
		$shop = new Shop($this->shopId);
		return (string)$shop->getBaseURL(true);
	}

	public function adminLanguage() {
		if (isset($this->context->employee) && Validate::isLoadedObject($this->context->employee)) {
			return self::lang(Language::getIsoById((int)$this->context->employee->id_lang));
		}
		return self::lang(Language::getIsoById((int)Configuration::get('PS_LANG_DEFAULT', null, $this->shopGroupId, $this->shopId)));
	}

	public function buyerLanguage() {
		return self::lang(isset($this->context->language) ? (string)$this->context->language->iso_code : '');
	}

	private static function lang($iso) {
		return strtolower((string)$iso) === 'ru' ? 'ru' : 'en';
	}

	public function userAgent() {
		return 'CoinsPay-PrestaShop/' . CoinsPay_Plugin::VERSION . ' (PrestaShop ' . _PS_VERSION_ . '; PHP ' . PHP_VERSION . ')';
	}

	public function log($message, $error) {
		if (!$error && !$this->setting('debug')) {
			return;
		}
		PrestaShopLogger::addLog('CoinsPay: ' . CoinsPay_Util::truncate((string)$message, 1000), $error ? 3 : 1, null, 'CoinsPay', 0, true);
	}
}
