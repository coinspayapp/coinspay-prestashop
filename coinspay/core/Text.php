<?php
/**
 * Texts of the module (order notes, the settings screen, buyer messages) in English or
 * Russian: lang/en.php and lang/ru.php. The module does not use PrestaShop's translation
 * systems, which differ between 1.6, 1.7 and 9.x.
 */
if (!defined('_PS_VERSION_') && !defined('COINSPAY_TESTS')) {
	exit;
}

class CoinsPay_Text {

	/** @var array */
	private $strings;

	/** @var array */
	private $fallback;

	/**
	 * @param string $lang en or ru.
	 */
	public function __construct($lang) {
		$this->fallback = self::load('en');
		$this->strings = $lang === 'en' ? $this->fallback : self::load($lang);
	}

	/**
	 * Text by key with sprintf arguments: t('note_paid', $number, $payments).
	 *
	 * @param string $key
	 * @return string
	 */
	public function t($key) {
		$args = func_get_args();
		array_shift($args);
		if (isset($this->strings[$key])) {
			$text = $this->strings[$key];
		} elseif (isset($this->fallback[$key])) {
			$text = $this->fallback[$key];
		} else {
			$text = $key;
		}
		return $args ? vsprintf($text, $args) : $text;
	}

	private static function load($lang) {
		$file = dirname(__FILE__) . '/lang/' . preg_replace('/[^a-z]/', '', (string)$lang) . '.php';
		if (!is_file($file)) {
			return array();
		}
		$strings = include $file;
		return is_array($strings) ? $strings : array();
	}
}
