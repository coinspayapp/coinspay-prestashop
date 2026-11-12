<?php
/**
 * The module's own order states and the map of the default kinds (CoinsPay_Settings::$defaults)
 * to state ids. The states are shared by all shops, as PrestaShop keeps order states.
 */
if (!defined('_PS_VERSION_')) {
	exit;
}

class CoinsPay_States {

	const AWAITING = 'COINSPAY_OS_AWAITING';
	const REVIEW = 'COINSPAY_OS_REVIEW';

	/** @var array config key → {color, names: lang iso → name} */
	private static $states = array(
		self::AWAITING => array(
			'color' => '#4169E1',
			'names' => array('en' => 'Awaiting crypto payment', 'ru' => 'Ожидает оплаты криптовалютой'),
		),
		self::REVIEW => array(
			'color' => '#E67E22',
			'names' => array('en' => 'CoinsPay: check the payment', 'ru' => 'CoinsPay: проверьте платёж'),
		),
	);

	/**
	 * Creates the states that are missing or were deleted; a reinstall keeps the existing ones.
	 *
	 * @param string $moduleDir Folder of the module: its logo becomes the state icon.
	 */
	public static function install($moduleDir) {
		foreach (self::$states as $key => $definition) {
			$id = (int)Configuration::getGlobalValue($key);
			if ($id > 0) {
				$state = new OrderState($id);
				if (Validate::isLoadedObject($state) && !$state->deleted) {
					continue;
				}
			}
			$state = new OrderState();
			$names = array();
			$templates = array();
			foreach (Language::getLanguages(false) as $language) {
				$iso = strtolower((string)$language['iso_code']);
				$names[(int)$language['id_lang']] = isset($definition['names'][$iso]) ? $definition['names'][$iso] : $definition['names']['en'];
				$templates[(int)$language['id_lang']] = '';
			}
			$state->name = $names;
			$state->template = $templates;
			$state->color = $definition['color'];
			$state->module_name = 'coinspay';
			$state->send_email = false;
			$state->invoice = false;
			$state->logable = false;
			$state->paid = false;
			$state->delivery = false;
			$state->shipped = false;
			$state->hidden = false;
			$state->unremovable = true;
			$state->pdf_invoice = false;
			$state->pdf_delivery = false;
			$state->add();
			Configuration::updateGlobalValue($key, (int)$state->id);
			// The state icon in the order history of 1.6 and in the old order pages.
			$icon = _PS_IMG_DIR_ . 'os/' . (int)$state->id . '.gif';
			if (is_dir(_PS_IMG_DIR_ . 'os') && !file_exists($icon) && file_exists($moduleDir . '/logo.gif')) {
				@copy($moduleDir . '/logo.gif', $icon);
			}
		}
	}

	/**
	 * A language was added to the shop: the module's states get their names in it. Called
	 * before PrestaShop copies the rows of the default language, which keeps existing rows.
	 *
	 * @param Language $language
	 */
	public static function addLanguage($language) {
		$iso = strtolower((string)$language->iso_code);
		foreach (self::$states as $key => $definition) {
			$id = (int)Configuration::getGlobalValue($key);
			if ($id <= 0 || !isset($definition['names'][$iso]) || !(int)$language->id) {
				continue;
			}
			$name = pSQL($definition['names'][$iso]);
			Db::getInstance()->execute('INSERT INTO `' . _DB_PREFIX_ . 'order_state_lang` (id_order_state, id_lang, name, template) VALUES ('
				. $id . ', ' . (int)$language->id . ', \'' . $name . '\', \'\') ON DUPLICATE KEY UPDATE name = \'' . $name . '\'');
		}
	}

	/**
	 * Kinds of the default event statuses → state ids of this installation.
	 *
	 * @return array
	 */
	public static function kinds() {
		return array(
			'awaiting' => (int)Configuration::getGlobalValue(self::AWAITING),
			'review' => (int)Configuration::getGlobalValue(self::REVIEW),
			'payment' => (int)Configuration::get('PS_OS_PAYMENT'),
			'canceled' => (int)Configuration::get('PS_OS_CANCELED'),
		);
	}

	/** Order states that mean "the shop cancelled the order" by default. */
	public static function defaultCancelStates() {
		return array((int)Configuration::get('PS_OS_CANCELED'));
	}

	/** The module's own "awaiting" state: placing an order falls back to it. */
	public static function awaiting() {
		return (int)Configuration::getGlobalValue(self::AWAITING);
	}

	/**
	 * Order states for the settings screen, in the language of the employee.
	 *
	 * @return array [{id, name, paid, logable}]
	 */
	public static function all($langId) {
		$states = array();
		foreach (OrderState::getOrderStates((int)$langId) as $row) {
			$states[] = array(
				'id' => (int)$row['id_order_state'],
				'name' => (string)$row['name'],
				'paid' => !empty($row['paid']),
				'logable' => !empty($row['logable']),
			);
		}
		return $states;
	}
}
