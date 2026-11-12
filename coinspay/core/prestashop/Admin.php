<?php
/**
 * The settings screen (Module → Configure), install and uninstall. Settings are kept per shop:
 * with multistore the screen asks to pick one shop first.
 */
if (!defined('_PS_VERSION_')) {
	exit;
}

class CoinsPay_Admin {

	/** @var CoinsPay */
	private $module;

	/** @var Context */
	private $context;

	/** @var CoinsPay_PsStore */
	public $store;

	/** @var CoinsPay_Plugin */
	public $plugin;

	/** @var array field → error text */
	private $errors = array();

	/** @var array Plain settings (no events): key → default. */
	private static $fields = array(
		'api_url' => '',
		'api_key' => '',
		'webhook_secret' => '',
		'total' => '',
		'lifetime' => CoinsPay_Settings::DEFAULT_LIFETIME,
		'auto_return' => 1,
		'stablecoins_only' => 0,
		'buyer_pays_fee' => 0,
		'buyer_pays_network' => 0,
		'test_buyer_only' => 1,
		'debug' => 0,
	);

	/** @var array Check boxes among the fields. */
	private static $flags = array('auto_return', 'stablecoins_only', 'buyer_pays_fee', 'buyer_pays_network', 'test_buyer_only', 'debug');

	/** @var array Settings that are secrets: never written back into the form. */
	private static $secrets = array('api_key', 'webhook_secret');

	public function __construct($module) {
		$this->module = $module;
		$this->context = Context::getContext();
		$this->store = new CoinsPay_PsStore($this->shopId());
		$this->plugin = new CoinsPay_Plugin($this->store);
	}

	/** The shop being configured: the one picked at the top of the back office, or the default one. */
	private function shopId() {
		if (Shop::isFeatureActive() && Shop::getContext() === Shop::CONTEXT_SHOP) {
			return (int)Shop::getContextShopID();
		}
		return (int)Configuration::get('PS_SHOP_DEFAULT');
	}

	/** Multistore is on and "All shops" or a group is picked: settings cannot be shown. */
	private function needsShop() {
		return Shop::isFeatureActive() && Shop::getContext() !== Shop::CONTEXT_SHOP;
	}

	/**
	 * The screen: answers the AJAX actions, saves the form, renders the template.
	 *
	 * @return string HTML
	 */
	public function content() {
		$action = (string)Tools::getValue('coinspay_action');
		if ($action === 'check' || $action === 'refresh') {
			$answer = $this->needsShop()
				? array('ok' => false, 'lines' => array($this->plugin->text->t('admin_pick_shop')), 'message' => $this->plugin->text->t('admin_pick_shop'))
				: ($action === 'check' ? $this->check() : $this->refresh());
			header('Content-Type: application/json; charset=utf-8');
			echo CoinsPay_Util::json($answer);
			exit;
		}
		$t = $this->plugin->text;
		if ($this->needsShop()) {
			$this->context->smarty->assign(array('coinspay' => array('pick_shop' => $t->t('admin_pick_shop'), 'texts' => $this->texts())));
			return $this->module->display($this->module->getLocalPath() . 'coinspay.php', 'views/templates/admin/configure.tpl');
		}
		// A shop added after the install without copying the module: it starts from the defaults.
		if ($this->store->setting('secret') === null) {
			self::fillDefaults($this->store, array());
			$this->plugin = new CoinsPay_Plugin($this->store);
		}
		$success = '';
		if (Tools::isSubmit('submitCoinspay')) {
			if ($this->save()) {
				$success = $t->t('admin_success');
				// Rebuilt: the settings are read again for the screen.
				$this->store = new CoinsPay_PsStore($this->shopId());
				$this->plugin = new CoinsPay_Plugin($this->store);
			}
		}
		$this->context->smarty->assign(array('coinspay' => $this->data($success)));
		return $this->module->display($this->module->getLocalPath() . 'coinspay.php', 'views/templates/admin/configure.tpl');
	}

	/**
	 * Saves the posted settings when they are valid.
	 *
	 * @return bool
	 */
	private function save() {
		$values = $this->posted();
		if (!$this->validate($values)) {
			$this->errors['warning'] = $this->plugin->text->t('admin_error_form');
			return false;
		}
		if ($this->plugin->settings->secret() === '') {
			$values['secret'] = CoinsPay_Util::randomHex(32);
		}
		$this->store->saveSettings($values);
		$this->plugin->repo->kvDelete('status_problem');
		$this->plugin->currencies->flush();
		return true;
	}

	/** Settings from the form. */
	private function posted() {
		$values = array();
		foreach (self::$fields as $key => $default) {
			$values[$key] = trim((string)Tools::getValue('coinspay_' . $key, ''));
		}
		foreach (self::$flags as $flag) {
			$values[$flag] = Tools::getValue('coinspay_' . $flag) ? 1 : 0;
		}
		$values['api_url'] = rtrim($values['api_url'], '/');
		$values['total'] = str_replace(',', '.', $values['total']);
		// The form never shows saved secrets: an empty field keeps the saved one.
		foreach (self::$secrets as $key) {
			if ($values[$key] === '') {
				$values[$key] = (string)$this->store->setting($key);
			}
		}
		foreach (CoinsPay_Settings::$events as $event) {
			$values['status_' . $event] = trim((string)Tools::getValue('coinspay_status_' . $event, ''));
		}
		$cancel = array();
		$posted = Tools::getValue('coinspay_cancel_statuses');
		if (is_array($posted)) {
			foreach ($posted as $id) {
				if (ctype_digit((string)$id) && (int)$id > 0) {
					$cancel[] = (int)$id;
				}
			}
		}
		$values['cancel_statuses'] = implode(',', array_unique($cancel));
		return $values;
	}

	private function validate($values) {
		$t = $this->plugin->text;
		if ($values['api_url'] !== '' && !preg_match('#^https?://[^\s/]+#i', $values['api_url'])) {
			$this->errors['api_url'] = $t->t('admin_error_api_url');
		}
		if ($values['api_key'] !== '' && !preg_match('/^cpk_(test_)?[A-Za-z0-9]{40}$/', $values['api_key'])) {
			$this->errors['api_key'] = $t->t('admin_error_api_key');
		}
		if ($values['webhook_secret'] !== '' && strpos($values['webhook_secret'], 'whsec_') !== 0) {
			$this->errors['webhook_secret'] = $t->t('admin_error_secret');
		}
		if ($values['total'] !== '' && !preg_match('/^\d+(\.\d+)?$/', $values['total'])) {
			$this->errors['total'] = $t->t('admin_error_total');
		}
		$lifetime = (int)$values['lifetime'];
		if (!ctype_digit($values['lifetime']) || $lifetime < CoinsPay_Settings::MIN_LIFETIME || $lifetime > CoinsPay_Settings::MAX_LIFETIME) {
			$this->errors['lifetime'] = $t->t('admin_error_lifetime');
		}
		foreach (CoinsPay_Settings::$events as $event) {
			$value = $values['status_' . $event];
			$ok = (ctype_digit($value) && (int)$value > 0 && $this->plugin->settings->statusExists((int)$value))
				|| ($value === '0' && !in_array($event, CoinsPay_Settings::$required, true))
				|| ($value === CoinsPay_Settings::SAME_AS_PAID && in_array($event, CoinsPay_Settings::$paidLike, true));
			if (!$ok) {
				$this->errors['status_' . $event] = $t->t('admin_error_status');
			}
		}
		return !$this->errors;
	}

	/** Everything the template needs. */
	private function data($success) {
		$t = $this->plugin->text;
		$posting = Tools::isSubmit('submitCoinspay') && $success === '';

		$values = array();
		foreach (self::$fields as $key => $default) {
			if ($posting) {
				$values[$key] = in_array($key, self::$flags, true) ? (Tools::getValue('coinspay_' . $key) ? 1 : 0) : (string)Tools::getValue('coinspay_' . $key, '');
			} else {
				$saved = $this->store->setting($key);
				$values[$key] = $saved === null ? $default : $saved;
			}
		}
		// Secrets stay on the server: the page only says whether one is saved. A key that can
		// move money must not be readable by whoever opens the settings.
		$secretSaved = array();
		foreach (self::$secrets as $key) {
			$secretSaved[$key] = (string)$this->store->setting($key) !== '';
			$values[$key] = '';
		}

		$langId = (int)$this->context->employee->id_lang;
		$states = CoinsPay_States::all($langId);
		$events = array();
		foreach (CoinsPay_Settings::$events as $event) {
			if ($posting) {
				$value = (string)Tools::getValue('coinspay_status_' . $event, '');
			} else {
				$saved = $this->store->setting('status_' . $event);
				$value = $saved === null ? '' : (string)$saved;
			}
			$events[] = array(
				'key' => $event,
				'label' => $t->t('admin_event_' . $event),
				'value' => $value,
				'no_change' => !in_array($event, CoinsPay_Settings::$required, true),
				'same_as_paid' => in_array($event, CoinsPay_Settings::$paidLike, true),
				'error' => isset($this->errors['status_' . $event]) ? $this->errors['status_' . $event] : '',
			);
		}
		if ($posting) {
			$cancel = is_array(Tools::getValue('coinspay_cancel_statuses')) ? array_map('intval', Tools::getValue('coinspay_cancel_statuses')) : array();
		} else {
			$cancel = $this->plugin->settings->cancelStatuses();
		}

		$warnings = array();
		$broken = array();
		foreach ($this->plugin->settings->brokenEvents() as $event) {
			$broken[] = $t->t('admin_event_' . $event);
		}
		if ($broken) {
			$warnings[] = $t->t('admin_warning_broken', implode('; ', $broken));
		}
		$byId = array();
		foreach ($states as $state) {
			$byId[$state['id']] = $state;
		}
		$paid = $this->plugin->settings->statusFor('paid');
		if ($paid > 0 && isset($byId[$paid]) && !$byId[$paid]['paid']) {
			$warnings[] = $t->t('admin_hint_paid_flag');
		}
		$created = $this->plugin->settings->statusFor('created');
		if ($created > 0 && isset($byId[$created]) && $byId[$created]['logable']) {
			$warnings[] = $t->t('admin_hint_created_flag');
		}
		if (!$this->plugin->settings->configured()) {
			$warnings[] = $t->t('admin_hint_setup');
		}

		foreach ($states as $i => $state) {
			$states[$i]['cancel'] = in_array($state['id'], $cancel, true);
		}

		$lastTest = $this->plugin->repo->kvGet('last_webhook_test');
		$secretReady = $this->plugin->settings->secret() !== '';
		$base = $this->configureUrl();
		return array(
			'pick_shop' => '',
			'texts' => $this->texts(),
			'action' => $base,
			'check_url' => $base . '&coinspay_action=check',
			'refresh_url' => $base . '&coinspay_action=refresh',
			'script_url' => $this->module->getPathUri() . 'views/js/admin.js?v=' . CoinsPay_Plugin::VERSION,
			'values' => $values,
			'errors' => $this->errors,
			'success' => $success,
			'events' => $events,
			'states' => $states,
			'cancel_statuses' => $cancel,
			'warnings' => $warnings,
			'test_mode' => $this->plugin->settings->isTestMode(),
			'saved' => $secretSaved,
			'webhook_url' => $this->store->webhookUrl(),
			'webhook_last' => $lastTest ? $t->t('admin_webhook_last', date('Y-m-d H:i:s', (int)$lastTest)) : '',
			'cron_url' => $secretReady ? $this->store->cronUrl($this->plugin->tokens->cronKey()) : '',
			'test_url' => $secretReady ? $this->store->link('test', array('t' => $this->plugin->tokens->testBuyer())) : '',
			'invoices' => $this->invoices(),
			'shop_name' => Shop::isFeatureActive() ? (string)$this->context->shop->name : '',
			'version' => $t->t('admin_version', CoinsPay_Plugin::VERSION, _PS_VERSION_),
			'legacy' => version_compare(_PS_VERSION_, '1.7.0.0', '<'),
		);
	}

	/** Address of this settings screen, with the back office token. */
	private function configureUrl() {
		return $this->context->link->getAdminLink('AdminModules', true) . '&configure=' . $this->module->name . '&tab_module=' . $this->module->tab . '&module_name=' . $this->module->name;
	}

	/** Texts of the admin screens: the admin_ keys of the dictionary, without the prefix. */
	public function texts() {
		$all = include dirname(dirname(__FILE__)) . '/lang/' . $this->store->adminLanguage() . '.php';
		$texts = array();
		foreach ($all as $key => $text) {
			if (strpos($key, 'admin_') === 0) {
				$texts[substr($key, 6)] = $text;
			}
		}
		return $texts;
	}

	private function invoices() {
		$rows = array();
		$t = $this->plugin->text;
		$orderLink = $this->context->link->getAdminLink('AdminOrders', true);
		foreach ($this->plugin->repo->recent(50) as $row) {
			$snapshot = json_decode((string)$row['snapshot'], true);
			$paid = is_array($snapshot) ? CoinsPay_Util::normalize(CoinsPay_Util::value($snapshot, 'amount_paid')) : '0';
			$rows[] = array(
				'invoice_id' => $row['invoice_id'],
				'order_id' => (int)$row['order_id'],
				'order_url' => $orderLink . '&id_order=' . (int)$row['order_id'] . '&vieworder',
				'number' => (int)$row['number'],
				'amount' => $row['amount'] . ' ' . $row['currency'],
				'paid' => $paid . ' ' . $row['currency'],
				'status' => $row['status'],
				'status_text' => $t->t('admin_invoice_' . preg_replace('/[^a-z]/', '', $row['status'])),
				'test' => (bool)$row['test'],
				'created' => date('Y-m-d H:i', (int)$row['created']),
				'pay_url' => $row['pay_url'],
			);
		}
		return $rows;
	}

	/**
	 * "Check connection": the address and key entered in the form, saved or not.
	 *
	 * @return array JSON answer.
	 */
	private function check() {
		$url = rtrim(trim((string)Tools::getValue('api_url', '')), '/');
		$key = trim((string)Tools::getValue('api_key', ''));
		// The key field is empty unless a new key is typed: the saved one is checked then.
		if ($key === '') {
			$key = $this->plugin->settings->apiKey();
		}
		if ($url === '') {
			$url = $this->plugin->settings->apiUrl();
		}
		return $this->plugin->checkConnection($url, $key);
	}

	/**
	 * "Refresh" of an invoice: asks CoinsPay about it now and applies it to the order.
	 *
	 * @return array JSON answer.
	 */
	private function refresh() {
		$t = $this->plugin->text;
		$invoiceId = (string)Tools::getValue('invoice_id', '');
		if (!preg_match(CoinsPay_ApiClient::UUID, $invoiceId) || !$this->plugin->repo->find($invoiceId)) {
			return array('ok' => false, 'message' => $t->t('admin_refresh_failed'));
		}
		try {
			$invoice = $this->plugin->settings->client()->invoice($invoiceId);
			$result = $this->plugin->sync->apply($invoice);
		} catch (Exception $e) {
			return array('ok' => false, 'message' => $t->t('admin_refresh_failed') . ' ' . $e->getMessage());
		}
		return array(
			'ok' => true,
			'message' => $t->t('admin_refreshed', $t->t('admin_invoice_' . preg_replace('/[^a-z]/', '', CoinsPay_Util::value($invoice, 'status')))),
			'status' => CoinsPay_Util::value($invoice, 'status'),
			'result' => $result,
		);
	}

	/**
	 * Install: tables, the module's order states, default settings for every shop. A
	 * reinstall keeps the settings the owner made.
	 *
	 * @return bool
	 */
	public function install() {
		$this->plugin->repo->install();
		CoinsPay_States::install(rtrim($this->module->getLocalPath(), '/'));
		$shopIds = Shop::isFeatureActive() ? Shop::getShops(false, null, true) : array((int)Configuration::get('PS_SHOP_DEFAULT'));
		foreach ($shopIds as $shopId) {
			self::fillDefaults(new CoinsPay_PsStore((int)$shopId), array());
		}
		return true;
	}

	/** Settings of a shop that has none: the defaults, the module's order states. */
	private static function defaults() {
		$defaults = CoinsPay_Settings::installDefaults(CoinsPay_States::kinds(), CoinsPay_States::defaultCancelStates());
		foreach (self::$fields as $key => $default) {
			$defaults[$key] = $default;
		}
		return $defaults;
	}

	/**
	 * Saves the settings a shop does not have yet: $values first, the defaults for the rest.
	 * Each shop gets its own secret of the module's links.
	 */
	private static function fillDefaults(CoinsPay_PsStore $store, $values) {
		$missing = array();
		foreach (array_merge(self::defaults(), $values) as $key => $value) {
			if ($store->setting($key) === null || $store->setting($key) === '') {
				$missing[$key] = $value;
			}
		}
		if (isset($missing['secret'])) {
			$missing['secret'] = CoinsPay_Util::randomHex(32);
		}
		$store->saveSettings($missing);
	}

	/**
	 * A shop was created as a copy of another one (multistore): it gets the settings of that
	 * shop, the CoinsPay key included, and a secret of its own links. Shops may share one
	 * CoinsPay shop: its webhook comes to one of them, which passes events about the other
	 * shops' invoices on (CoinsPay_Front::webhook()).
	 */
	public static function copyShop($oldShopId, $newShopId) {
		$old = new CoinsPay_PsStore((int)$oldShopId);
		$values = array();
		foreach (array_keys(self::defaults()) as $key) {
			$value = $old->setting($key);
			if ($value !== null) {
				$values[$key] = $value;
			}
		}
		$values['secret'] = CoinsPay_Util::randomHex(32);
		$new = new CoinsPay_PsStore((int)$newShopId);
		$new->saveSettings($values);
		self::fillDefaults($new, array());
	}

	/** Uninstall keeps the settings and the invoices: orders refer to them. Only the cache goes. */
	public function uninstall() {
		$this->plugin->repo->uninstall();
		return true;
	}
}
