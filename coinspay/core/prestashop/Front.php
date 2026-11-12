<?php
/**
 * Storefront side of the module, shared by its front controllers and hooks: whether the
 * method is offered, placing the order, the buyer's return, the webhook, polling, the test
 * buyer link and the order status hook.
 */
if (!defined('_PS_VERSION_')) {
	exit;
}

class CoinsPay_Front {

	/** Cookie field of the test buyer: Unix time until which the flag holds. */
	const TEST_BUYER = 'coinspay_test_buyer';

	/** @var CoinsPay_PsStore */
	public $store;

	/** @var CoinsPay_Plugin */
	public $plugin;

	/** @var Context */
	private $context;

	/**
	 * @param int|null $shopId The shop of the context when null.
	 */
	public function __construct($shopId = null) {
		$this->context = Context::getContext();
		$this->store = new CoinsPay_PsStore($shopId);
		$this->plugin = new CoinsPay_Plugin($this->store);
	}

	/** Texts of the storefront: the catalog_ keys of the dictionary in the buyer's language, without the prefix. */
	public function texts() {
		$all = include dirname(dirname(__FILE__)) . '/lang/' . $this->store->buyerLanguage() . '.php';
		$texts = array();
		foreach ($all as $key => $text) {
			if (strpos($key, 'catalog_') === 0) {
				$texts[substr($key, 8)] = $text;
			}
		}
		return $texts;
	}

	public function isTestBuyer() {
		$cookie = $this->context->cookie;
		return isset($cookie->{self::TEST_BUYER}) && (int)$cookie->{self::TEST_BUYER} > time();
	}

	/**
	 * Whether the method is offered for the cart: configured, the minimum total reached, the
	 * test buyer for a test key, a currency CoinsPay takes.
	 *
	 * @param Cart $cart
	 * @return bool
	 */
	public function available($cart) {
		if (!Validate::isLoadedObject($cart)) {
			return false;
		}
		$settings = $this->plugin->settings;
		$minimum = (float)$settings->get('total', 0);
		if ($minimum > 0) {
			$currency = new Currency((int)$cart->id_currency);
			$rate = Validate::isLoadedObject($currency) && (float)$currency->conversion_rate > 0 ? (float)$currency->conversion_rate : 1.0;
			if ((float)$cart->getOrderTotal(true, Cart::BOTH) / $rate < $minimum) {
				return false;
			}
		}
		$currency = new Currency((int)$cart->id_currency);
		return $this->plugin->available(Validate::isLoadedObject($currency) ? (string)$currency->iso_code : $this->store->defaultCurrency(), $this->isTestBuyer());
	}

	/**
	 * Places the order of the context cart with the status of the "created" event, once: a
	 * repeated click finds the order already made.
	 *
	 * @param PaymentModule $module
	 * @return int Order id; 0 when the cart cannot be ordered.
	 */
	public function placeOrder($module) {
		$cart = $this->context->cart;
		$lock = 'coinspay_cart_' . substr(md5(_DB_PREFIX_ . '|' . $this->store->siteUrl()), 0, 12) . '_' . (int)$cart->id;
		$rows = $this->store->query('SELECT GET_LOCK(\'' . $this->store->escape($lock) . '\', 10) AS l');
		if (!$rows || (string)$rows[0]['l'] !== '1') {
			return 0;
		}
		try {
			$orderId = self::orderOfCart((int)$cart->id);
			if (!$orderId) {
				$customer = new Customer((int)$cart->id_customer);
				$state = $this->plugin->settings->statusFor('created');
				if ($state <= 0) {
					$state = CoinsPay_States::awaiting();
					$this->store->log('the order status for "created" is not set; the module\'s "awaiting" state is used', true);
				}
				$module->validateOrder(
					(int)$cart->id,
					$state,
					(float)$cart->getOrderTotal(true, Cart::BOTH),
					$module->displayName,
					null,
					array(),
					(int)$cart->id_currency,
					false,
					$customer->secure_key
				);
				$orderId = (int)$module->currentOrder;
				$this->placed = true;
			}
		} catch (Exception $e) {
			$this->store->query('SELECT RELEASE_LOCK(\'' . $this->store->escape($lock) . '\') AS l');
			throw $e;
		}
		$this->store->query('SELECT RELEASE_LOCK(\'' . $this->store->escape($lock) . '\') AS l');
		return $orderId;
	}

	/** @var bool placeOrder() made a new order in this request. */
	public $placed = false;

	/**
	 * The main order of a cart (the last one when the cart was split), or 0. Plain SQL: the
	 * method is named differently in 1.6 and 9.x.
	 */
	public static function orderOfCart($cartId) {
		return (int)Db::getInstance()->getValue('SELECT MAX(id_order) FROM `' . _DB_PREFIX_ . 'orders` WHERE id_cart = ' . (int)$cartId, false);
	}

	/**
	 * The invoice of an order and its payment page.
	 *
	 * @param int  $orderId
	 * @param bool $placed The order was just placed.
	 * @return array {url} or {error}
	 */
	public function pay($orderId, $placed) {
		$buyer = $this->plugin->buyerText();
		try {
			$invoice = $this->plugin->payment->invoiceFor($orderId, $this->isTestBuyer(), $placed);
		} catch (CoinsPay_ApiError $e) {
			$this->store->log(sprintf('order %d: invoice not created: %s', $orderId, $e->summary()), true);
			return array('error' => CoinsPay_Payment::errorMessage($e, $buyer));
		} catch (Exception $e) {
			$this->store->log(sprintf('order %d: invoice not created: %s', $orderId, $e->getMessage()), true);
			return array('error' => $buyer->t('error_failed'));
		}
		return array('url' => $this->plugin->payUrl($invoice));
	}

	/**
	 * Link to pay an order again (from the return page and the order page of the customer).
	 */
	public function payAgainUrl($orderId) {
		return $this->store->link('redirect', array('order' => (int)$orderId, 'token' => $this->plugin->tokens->returnToken($orderId)));
	}

	/**
	 * The buyer comes back from the payment page: the invoice is applied to the order.
	 *
	 * @param int $orderId
	 * @return array|null The invoice as CoinsPay sees it now (or as last stored), null without one.
	 */
	public function refreshOrder($orderId) {
		$row = $this->plugin->repo->current($orderId);
		if (!$row) {
			return null;
		}
		try {
			$invoice = $this->plugin->settings->client()->invoice($row['invoice_id']);
			$this->plugin->sync->apply($invoice);
			return $invoice;
		} catch (Exception $e) {
			$this->store->log(sprintf('return to order %d: %s', $orderId, $e->getMessage()), true);
			$snapshot = json_decode((string)$row['snapshot'], true);
			return is_array($snapshot) ? $snapshot + array('status' => $row['status']) : null;
		}
	}

	/** Paid, or money on its way: the order is placed for good. */
	public static function hasMoney($invoice) {
		return is_array($invoice) && (CoinsPay_Util::value($invoice, 'status') === 'paid'
			|| CoinsPay_Util::positive(CoinsPay_Util::value($invoice, 'amount_paid'))
			|| CoinsPay_Util::positive(CoinsPay_Util::value($invoice, 'amount_pending')));
	}

	/**
	 * The cart of an unpaid order back in the buyer's session, so they can change it and order
	 * again. Only for the order's own customer: a duplicated cart carries their addresses.
	 *
	 * @return bool
	 */
	public function restoreCart($orderId) {
		$order = $this->store->order($orderId);
		if (!$order || !$this->context->customer || (int)$this->context->customer->id !== (int)$order['customer_id']) {
			return false;
		}
		$old = new Cart((int)$order['cart_id']);
		if (!Validate::isLoadedObject($old)) {
			return false;
		}
		$copy = $old->duplicate();
		if (!is_array($copy) || empty($copy['success']) || !Validate::isLoadedObject($copy['cart'])) {
			return false;
		}
		$this->context->cookie->id_cart = (int)$copy['cart']->id;
		$this->context->cart = $copy['cart'];
		$this->context->cookie->write();
		return true;
	}

	/** Whether the customer of the context may see the order: its customer, or the holder of its secure key. */
	public function ownsOrder($order) {
		return $order && $this->context->customer && (int)$this->context->customer->id === (int)$order['customer_id'];
	}

	/** The order-confirmation page of an order. */
	public function confirmationUrl($order, $moduleId) {
		return $this->context->link->getPageLink('order-confirmation', true, null, array(
			'id_cart' => (int)$order['cart_id'],
			'id_module' => (int)$moduleId,
			'id_order' => (int)$order['id'],
			'key' => $order['secure_key'],
		));
	}

	/**
	 * The webhook: raw body and signature to the core.
	 *
	 * @return array {0: HTTP status, 1: text}
	 */
	public function webhook() {
		$signature = isset($_SERVER['HTTP_X_CPAY_SIGNATURE']) ? (string)$_SERVER['HTTP_X_CPAY_SIGNATURE'] : '';
		// The signature is over the raw bytes; php://input is the only place they are.
		$body = (string)file_get_contents('php://input');
		$answer = $this->plugin->webhook->handle($body, $signature);
		if ($answer[0] !== 200 || $answer[1] !== 'ignored: unknown invoice') {
			return $answer;
		}
		// Shops of a multistore may share one CoinsPay shop, and its webhook comes to one of
		// them: an invoice of another shop goes to that shop, which checks the signature with
		// its own secret and fetches the invoice with its own key.
		$event = json_decode($body, true);
		$invoiceId = is_array($event) ? CoinsPay_Webhook::invoiceId($event) : '';
		if ($invoiceId === '' || !preg_match(CoinsPay_ApiClient::UUID, $invoiceId)) {
			return $answer;
		}
		$shopId = (int)Db::getInstance()->getValue('SELECT shop_id FROM `' . _DB_PREFIX_ . 'coinspay_invoice` WHERE invoice_id = \'' . pSQL($invoiceId) . '\'', false);
		if (!$shopId || $shopId === (int)$this->store->shopId()) {
			return $answer;
		}
		$other = new self($shopId);
		return $other->plugin->webhook->handle($body, $signature);
	}

	/**
	 * Polling, by the system cron or by the lazy poll's own request.
	 *
	 * @return array {0: HTTP status, 1: text}
	 */
	public function cron($key) {
		if (!$this->plugin->tokens->checkCron((string)$key)) {
			return array(403, 'bad key');
		}
		// The lazy poll's request hangs up at once: the batch goes on without it.
		ignore_user_abort(true);
		if (function_exists('set_time_limit')) {
			@set_time_limit(120);
		}
		return array(200, 'checked ' . $this->plugin->poller->run());
	}

	/**
	 * The test buyer link from the settings screen.
	 *
	 * @return bool The link was valid.
	 */
	public function becomeTestBuyer($value) {
		if (!$this->plugin->tokens->checkTestBuyer((string)$value)) {
			return false;
		}
		$this->context->cookie->{self::TEST_BUYER} = time() + CoinsPay_Tokens::TEST_BUYER_TTL;
		$this->context->cookie->write();
		return true;
	}

	/**
	 * A storefront page was built: at most once in five minutes one page fires the polling request.
	 */
	public function onPageLoad() {
		if (!$this->plugin->settings->configured()) {
			return;
		}
		// One cheap query on most pages; the turn itself is taken in the module's table.
		if ($this->plugin->repo->kvGet('lazy_poll', CoinsPay_Poller::LAZY_EVERY) !== null) {
			return;
		}
		if ($this->plugin->poller->lazyTurn()) {
			register_shutdown_function(array($this->plugin, 'loopback'));
		}
	}

	/**
	 * An order changed its status: when the shop put it into a cancel status, its open invoice is cancelled.
	 */
	public static function onStatusChange($orderId) {
		if (CoinsPay_PsStore::$writing || !$orderId) {
			return;
		}
		$shopId = (int)Db::getInstance()->getValue('SELECT id_shop FROM `' . _DB_PREFIX_ . 'orders` WHERE id_order = ' . (int)$orderId . ' AND module = \'coinspay\'', false);
		if (!$shopId) {
			return;
		}
		$front = new self($shopId);
		try {
			$front->plugin->poller->cancelForOrder((int)$orderId);
		} catch (Exception $e) {
			$front->store->log('order status hook: ' . $e->getMessage(), true);
		}
	}
}
