<?php
/**
 * Polling of open invoices, by the system cron (the address from the settings) or by the
 * module's own lazy poll. The key in the address is required.
 */
if (!defined('_PS_VERSION_')) {
	exit;
}

require_once dirname(__FILE__) . '/../../core/prestashop/FrontPage.php';

class CoinsPayCronModuleFrontController extends CoinsPay_FrontPage {

	public function postProcess() {
		$front = new CoinsPay_Front();
		if (!$this->module->active) {
			$this->respond(503, 'the module is disabled');
		}
		try {
			$answer = $front->cron((string)Tools::getValue('key'));
		} catch (Exception $e) {
			$front->store->log('poll failed: ' . $e->getMessage(), true);
			$answer = array(500, 'poll failed');
		}
		$this->respond($answer[0], $answer[1]);
	}
}
