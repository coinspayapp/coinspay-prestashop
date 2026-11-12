<?php
/**
 * Base of the module's front controllers: a simple message page (heading, text, buttons) in
 * the layout of the shop, and plain-text answers for the webhook and cron.
 */
if (!defined('_PS_VERSION_')) {
	exit;
}

abstract class CoinsPay_FrontPage extends ModuleFrontController {

	/** @var array|null The message page to show: {heading, message, tone, buttons: [{url, label, primary}]}. */
	protected $coinspayPage = null;

	/**
	 * Shows a message page instead of a redirect.
	 */
	protected function page($heading, $message, $buttons, $tone = 'warning') {
		$this->coinspayPage = array(
			'heading' => $heading,
			'message' => $message,
			'tone' => $tone,
			'buttons' => $buttons,
		);
	}

	public function initContent() {
		parent::initContent();
		if ($this->coinspayPage === null) {
			return;
		}
		$this->context->smarty->assign(array('coinspay_page' => $this->coinspayPage));
		if (version_compare(_PS_VERSION_, '1.7.0.0', '<')) {
			$this->setTemplate('message16.tpl');
		} else {
			$this->setTemplate('module:coinspay/views/templates/front/message.tpl');
		}
	}

	/**
	 * A plain-text answer with its HTTP status, for CoinsPay and cron.
	 */
	protected function respond($status, $text) {
		if (function_exists('http_response_code')) {
			http_response_code((int)$status);
		}
		header('Content-Type: text/plain; charset=utf-8');
		header('Cache-Control: no-store');
		echo $text;
		exit;
	}

	/** Leaves for an address outside the shop (the payment page) or inside it. */
	protected function leave($url) {
		header('Cache-Control: no-store');
		header('Location: ' . $url, true, 302);
		exit;
	}
}
