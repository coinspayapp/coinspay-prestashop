<?php
/**
 * The test buyer link from the settings screen: with a test key the payment method shows
 * only in the browser that opened it, for a day.
 */
if (!defined('_PS_VERSION_')) {
	exit;
}

require_once dirname(__FILE__) . '/../../core/prestashop/FrontPage.php';

class CoinsPayTestModuleFrontController extends CoinsPay_FrontPage {

	public $ssl = true;

	public function postProcess() {
		$front = new CoinsPay_Front();
		$texts = $front->texts();
		$ok = $front->becomeTestBuyer((string)Tools::getValue('t'));
		$this->page($texts['test_heading'], $ok ? $texts['test_on'] : $texts['test_bad'], array(
			array('url' => $this->context->link->getPageLink('index', true), 'label' => $texts['home'], 'primary' => true),
		), $ok ? 'success' : 'warning');
	}
}
