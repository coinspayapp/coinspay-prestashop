<?php
/**
 * The buyer comes back from the payment page: a paid order goes to the order confirmation,
 * an unpaid one gets a page to pay it or to put the items back in the cart.
 */
if (!defined('_PS_VERSION_')) {
	exit;
}

require_once dirname(__FILE__) . '/../../core/prestashop/FrontPage.php';

class CoinsPayReturnModuleFrontController extends CoinsPay_FrontPage {

	public $ssl = true;

	public function postProcess() {
		$front = new CoinsPay_Front();
		$orderId = (int)Tools::getValue('order');
		$token = (string)Tools::getValue('token');
		$order = $orderId > 0 && $front->plugin->tokens->checkReturn($orderId, $token) ? $front->store->order($orderId) : null;
		if (!$order || !$order['ours']) {
			$this->leave($this->context->link->getPageLink('index', true));
		}
		$texts = $front->texts();
		$home = array('url' => $this->context->link->getPageLink('index', true), 'label' => $texts['home'], 'primary' => false);

		if (Tools::getValue('restore')) {
			if ($front->restoreCart($orderId)) {
				$this->leave($this->context->link->getPageLink(version_compare(_PS_VERSION_, '1.7.0.0', '<') ? 'order' : 'cart', true, null, version_compare(_PS_VERSION_, '1.7.0.0', '<') ? array() : array('action' => 'show')));
			}
			$this->page($texts['return_heading'], $texts['restore_failed'], array($home));
			return;
		}

		$invoice = $front->refreshOrder($orderId);
		if (CoinsPay_Front::hasMoney($invoice)) {
			$this->leave($front->confirmationUrl($order, $this->module->id));
		}

		$buttons = array();
		$open = is_array($invoice) && CoinsPay_Util::value($invoice, 'status') === 'open';
		if ($open) {
			$buttons[] = array('url' => $front->payAgainUrl($orderId), 'label' => $texts['pay_again'], 'primary' => true);
		}
		if ($front->ownsOrder($order)) {
			$buttons[] = array('url' => $front->store->returnUrl($orderId, $token) . '&restore=1', 'label' => $texts['restore'], 'primary' => !$open);
		}
		$buttons[] = $home;
		$this->page($texts['return_heading'], sprintf($open ? $texts['return_open'] : $texts['return_closed'], $order['reference']), $buttons);
	}
}
