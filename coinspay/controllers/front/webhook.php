<?php
/**
 * The webhook of CoinsPay: a signed event says which invoice changed; its state is fetched
 * from CoinsPay with the shop's own key and applied to the order.
 */
if (!defined('_PS_VERSION_')) {
	exit;
}

require_once dirname(__FILE__) . '/../../core/prestashop/FrontPage.php';

class CoinsPayWebhookModuleFrontController extends CoinsPay_FrontPage {

	public function postProcess() {
		$front = new CoinsPay_Front();
		if (!$this->module->active) {
			$this->respond(503, 'the module is disabled');
		}
		try {
			$answer = $front->webhook();
		} catch (Exception $e) {
			$front->store->log('webhook failed: ' . $e->getMessage(), true);
			$answer = array(500, 'processing failed, retry later');
		}
		$this->respond($answer[0], $answer[1]);
	}
}
