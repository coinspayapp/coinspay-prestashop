<?php
/**
 * CoinsPay for PrestaShop: cryptocurrency payments on the CoinsPay payment page, with the
 * money going on to the merchant's own wallets. One module for PrestaShop 1.6.1 to 9.x.
 *
 * @author    CoinsPay
 * @copyright CoinsPay
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */
if (!defined('_PS_VERSION_')) {
	exit;
}

require_once dirname(__FILE__) . '/core/loader.php';

class CoinsPay extends PaymentModule {

	public function __construct() {
		$this->name = 'coinspay';
		$this->tab = 'payments_gateways';
		$this->version = '0.1.0';
		$this->author = 'CoinsPay';
		$this->need_instance = 0;
		$this->ps_versions_compliancy = array('min' => '1.6.1.0', 'max' => _PS_VERSION_);
		$this->bootstrap = true;
		$this->controllers = array('redirect', 'return', 'webhook', 'cron', 'test');
		$this->currencies = true;
		$this->currencies_mode = 'checkbox';
		parent::__construct();

		$this->displayName = $this->l('CoinsPay: cryptocurrency payments');
		$this->description = $this->l('Accept USDT, BTC, ETH and 30+ other coins. Payments go straight to your own wallets.');
		$this->confirmUninstall = $this->l('Uninstall CoinsPay? Invoices of past orders are kept.');
	}

	/** PrestaShop 1.7 and newer: payment options with PaymentOption; 1.6: the payment hook with HTML. */
	public static function isLegacy() {
		return version_compare(_PS_VERSION_, '1.7.0.0', '<');
	}

	public function install() {
		$hooks = array('displayPaymentReturn', 'displayOrderDetail', 'displayFooter', 'actionOrderStatusPostUpdate', 'actionObjectLanguageAddAfter', 'actionShopDataDuplication');
		$hooks[] = self::isLegacy() ? 'payment' : 'paymentOptions';
		if (!parent::install()) {
			return false;
		}
		foreach ($hooks as $hook) {
			if (!$this->registerHook($hook)) {
				return false;
			}
		}
		$admin = new CoinsPay_Admin($this);
		return $admin->install();
	}

	public function uninstall() {
		$admin = new CoinsPay_Admin($this);
		$admin->uninstall();
		return parent::uninstall();
	}

	/** The settings screen. */
	public function getContent() {
		$admin = new CoinsPay_Admin($this);
		return $admin->content();
	}

	/**
	 * PrestaShop 1.7+: the payment option at checkout.
	 *
	 * @return array
	 */
	public function hookPaymentOptions($params) {
		$cart = isset($params['cart']) ? $params['cart'] : $this->context->cart;
		if (!$this->active || !$this->checkCurrency($cart)) {
			return array();
		}
		$front = new CoinsPay_Front();
		if (!$front->available($cart)) {
			return array();
		}
		$texts = $front->texts();
		$this->smarty->assign(array(
			'coinspay_description' => $texts['description'],
			'coinspay_test' => $front->plugin->settings->isTestMode() ? $texts['test'] : '',
		));
		$class = 'PrestaShop\\PrestaShop\\Core\\Payment\\PaymentOption';
		$option = new $class();
		$option->setModuleName($this->name)
			->setCallToActionText($texts['title'])
			->setAction($this->context->link->getModuleLink($this->name, 'redirect', array(), true))
			->setAdditionalInformation($this->display(__FILE__, 'views/templates/hook/option.tpl'));
		return array($option);
	}

	/** PrestaShop 1.6: the payment method block at checkout. */
	public function hookPayment($params) {
		$cart = isset($params['cart']) ? $params['cart'] : $this->context->cart;
		if (!$this->active || !$this->checkCurrency($cart)) {
			return '';
		}
		$front = new CoinsPay_Front();
		if (!$front->available($cart)) {
			return '';
		}
		$texts = $front->texts();
		$this->smarty->assign(array(
			'coinspay_url' => $this->context->link->getModuleLink($this->name, 'redirect', array(), true),
			'coinspay_title' => $texts['title'],
			'coinspay_description' => $texts['description'],
			'coinspay_test' => $front->plugin->settings->isTestMode() ? $texts['test'] : '',
			'coinspay_logo' => $this->_path . 'views/img/coins.png',
		));
		return $this->display(__FILE__, 'views/templates/hook/payment16.tpl');
	}

	/** The order confirmation page: what happens with the payment. */
	public function hookDisplayPaymentReturn($params) {
		$order = isset($params['order']) ? $params['order'] : (isset($params['objOrder']) ? $params['objOrder'] : null);
		if (!$this->active || !Validate::isLoadedObject($order) || $order->module !== $this->name) {
			return '';
		}
		$front = new CoinsPay_Front((int)$order->id_shop);
		$row = $front->plugin->repo->current((int)$order->id);
		if (!$row) {
			return '';
		}
		$texts = $front->texts();
		$snapshot = json_decode((string)$row['snapshot'], true);
		if ($row['status'] === 'paid') {
			$message = $texts['return_paid'];
		} elseif (CoinsPay_Front::hasMoney($snapshot)) {
			$message = $texts['return_pending'];
		} else {
			$message = $texts['return_waiting'];
		}
		$this->smarty->assign(array(
			'coinspay_message' => $message,
			'coinspay_pay_url' => $row['status'] === 'open' && !CoinsPay_Front::hasMoney($snapshot) ? $front->payAgainUrl((int)$order->id) : '',
			'coinspay_pay' => $texts['pay_again'],
		));
		return $this->display(__FILE__, 'views/templates/hook/payment_return.tpl');
	}

	/** PrestaShop 1.6 calls the same hook by its old name too. */
	public function hookPaymentReturn($params) {
		return $this->hookDisplayPaymentReturn($params);
	}

	/** The order page in the customer account: a link to pay while the invoice is open. */
	public function hookDisplayOrderDetail($params) {
		$order = isset($params['order']) ? $params['order'] : null;
		if (!$this->active || !Validate::isLoadedObject($order) || $order->module !== $this->name) {
			return '';
		}
		$front = new CoinsPay_Front((int)$order->id_shop);
		$row = $front->plugin->repo->current((int)$order->id);
		$snapshot = $row ? json_decode((string)$row['snapshot'], true) : null;
		if (!$row || $row['status'] !== 'open' || CoinsPay_Front::hasMoney($snapshot)) {
			return '';
		}
		$texts = $front->texts();
		$this->smarty->assign(array(
			'coinspay_message' => $texts['order_open'],
			'coinspay_pay_url' => $front->payAgainUrl((int)$order->id),
			'coinspay_pay' => $texts['pay_again'],
		));
		return $this->display(__FILE__, 'views/templates/hook/order_detail.tpl');
	}

	/** Every storefront page: the lazy poll, when webhooks do not arrive. */
	public function hookDisplayFooter($params) {
		if ($this->active) {
			try {
				$front = new CoinsPay_Front();
				$front->onPageLoad();
			} catch (Exception $e) {
				// A storefront page never fails because of the module's poll.
			}
		}
		return '';
	}

	/** The shop changed an order status: a cancel status cancels the open invoice. */
	public function hookActionOrderStatusPostUpdate($params) {
		if (isset($params['id_order'])) {
			CoinsPay_Front::onStatusChange((int)$params['id_order']);
		}
	}

	/** A language was added: the module's order states get their names in it. */
	public function hookActionObjectLanguageAddAfter($params) {
		if (isset($params['object']) && $params['object'] instanceof Language) {
			CoinsPay_States::addLanguage($params['object']);
		}
	}

	/** A shop was created as a copy of another one: it gets the module settings without the keys. */
	public function hookActionShopDataDuplication($params) {
		if (isset($params['old_id_shop'], $params['new_id_shop'])) {
			CoinsPay_Admin::copyShop((int)$params['old_id_shop'], (int)$params['new_id_shop']);
		}
	}

	/** The currencies the module is enabled for (Payment → Preferences) include the cart's. */
	public function checkCurrency($cart) {
		if (!Validate::isLoadedObject($cart)) {
			return false;
		}
		$currencies = $this->getCurrency((int)$cart->id_currency);
		if (is_array($currencies)) {
			foreach ($currencies as $currency) {
				if ((int)$currency['id_currency'] === (int)$cart->id_currency) {
					return true;
				}
			}
		}
		return false;
	}
}
