<?php
/**
 * The payment button: places the order of the cart and sends the buyer to the CoinsPay
 * payment page. With ?order=…&token=… it opens the payment of an order placed before.
 */
if (!defined('_PS_VERSION_')) {
	exit;
}

require_once dirname(__FILE__) . '/../../core/prestashop/FrontPage.php';

class CoinsPayRedirectModuleFrontController extends CoinsPay_FrontPage {

	public $ssl = true;

	public function postProcess() {
		$front = new CoinsPay_Front();
		$texts = $front->texts();
		$home = array('url' => $this->context->link->getPageLink('index', true), 'label' => $texts['home'], 'primary' => false);

		$orderId = (int)Tools::getValue('order');
		if ($orderId > 0) {
			$order = $front->plugin->tokens->checkReturn($orderId, (string)Tools::getValue('token')) ? $front->store->order($orderId) : null;
			if (!$order || !$order['ours']) {
				$this->leave($this->context->link->getPageLink('index', true));
			}
			$this->go($front, $front->pay($orderId, false), $orderId, $home);
			return;
		}

		$cart = $this->context->cart;
		if (!$this->module->active || !Validate::isLoadedObject($cart) || !$cart->id_customer || !$cart->id_address_invoice || !$cart->nbProducts()) {
			$this->leave($this->context->link->getPageLink('order', true));
		}
		$customer = new Customer((int)$cart->id_customer);
		if (!Validate::isLoadedObject($customer)) {
			$this->leave($this->context->link->getPageLink('order', true));
		}
		// The method may have gone since the checkout page showed it: address, currency, test buyer.
		$authorized = false;
		foreach (Module::getPaymentModules() as $module) {
			if ($module['name'] === $this->module->name) {
				$authorized = true;
				break;
			}
		}
		$existing = CoinsPay_Front::orderOfCart((int)$cart->id);
		if (!$existing && (!$authorized || !$this->module->checkCurrency($cart) || !$front->available($cart))) {
			$this->page($texts['error_heading'], $texts['method_unavailable'], array(
				array('url' => $this->context->link->getPageLink('order', true), 'label' => $texts['home'], 'primary' => true),
			));
			return;
		}

		try {
			$orderId = $front->placeOrder($this->module);
		} catch (Exception $e) {
			$front->store->log(sprintf('cart %d: the order was not placed: %s', (int)$cart->id, $e->getMessage()), true);
			$orderId = 0;
		}
		if (!$orderId) {
			$this->page($texts['error_heading'], $texts['busy'], array($home));
			return;
		}
		$order = $front->store->order($orderId);
		if (!$order || !$order['ours']) {
			// The cart was ordered with another payment method meanwhile.
			$this->leave($this->context->link->getPageLink('history', true));
		}
		$this->go($front, $front->pay($orderId, $front->placed), $orderId, $home);
	}

	/** To the payment page, or a page that says why not and offers to try again. */
	private function go($front, $result, $orderId, $home) {
		if (isset($result['url'])) {
			$this->leave($result['url']);
		}
		$texts = $front->texts();
		$this->page($texts['error_heading'], $result['error'], array(
			array('url' => $front->payAgainUrl($orderId), 'label' => $texts['pay_again'], 'primary' => true),
			$home,
		));
	}
}
