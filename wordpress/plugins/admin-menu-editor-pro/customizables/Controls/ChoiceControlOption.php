<?php

namespace YahnisElsts\AdminMenuEditor\Customizable\Controls;

use YahnisElsts\AdminMenuEditor\Customizable\HtmlHelper;
use YahnisElsts\AdminMenuEditor\Customizable\Schemas\Enum;
use YahnisElsts\AdminMenuEditor\Customizable\Settings\Setting;
use YahnisElsts\AdminMenuEditor\Customizable\Settings;

class ChoiceControlOption {
	public $value;
	public $label;
	public $description = '';
	public $enabled = true;
	public $icon = null;

	/**
	 * @param mixed|null $value
	 * @param string|null $label
	 * @param array $params
	 */
	public function __construct($value, $label = null, $params = []) {
		$this->value = $value;
		$this->label = ($label !== null) ? $label : $value;
		if ( isset($params['description']) ) {
			$this->description = $params['description'];
		}
		if ( array_key_exists('enabled', $params) ) {
			$this->enabled = (bool)($params['enabled']);
		}
		if ( isset($params['icon']) ) {
			$this->icon = $params['icon'];
		}
	}

	public function serializeForJs() {
		$result = [
			'value' => $this->value,
			'label' => $this->label,
		];
		if ( $this->description !== '' ) {
			$result['description'] = $this->description;
		}
		if ( !$this->enabled ) {
			$result['enabled'] = false;
		}
		if ( $this->icon !== null ) {
			$result['icon'] = $this->icon;
		}
		return $result;
	}

	public static function fromArray($array) {
		return new static(
			array_key_exists('value', $array) ? $array['value'] : null,
			array_key_exists('label', $array) ? $array['label'] : null,
			$array
		);
	}

	/**
	 * @param ChoiceControlOption[] $options
	 * @param mixed $selectedValue
	 * @param Settings\AbstractSetting $setting
	 * @return array
	 */
	public static function generateSelectOptions($options, $selectedValue, Settings\AbstractSetting $setting) {
		$htmlLines = [];

		foreach ($options as $option) {
			$htmlLines[] = HtmlHelper::tag(
				'option',
				[
					'value'    => $setting->encodeForForm($option->value),
					'selected' => ($selectedValue === $option->value),
					'disabled' => !$option->enabled,

				],
				$option->label
			);
		}

		$koOptionData = self::generateKoOptions($options);
		$optionBindings = array_map('wp_json_encode', $koOptionData);

		return [implode("\n", $htmlLines), $optionBindings];
	}

	/**
	 * @param ChoiceControlOption[] $choiceOptions
	 * @return array{options: array, optionsText: string, optionsValue: string}
	 */
	public static function generateKoOptions($choiceOptions) {
		$koOptions = [];
		foreach ($choiceOptions as $option) {
			$koOptions[] = [
				'value'    => $option->value,
				'label'    => $option->label,
				'disabled' => !$option->enabled,
			];
		}

		return [
			'options'      => $koOptions,
			'optionsText'  => 'label',
			'optionsValue' => 'value',
		];
	}

	/**
	 * Try to generate a list of options from the given setting.
	 *
	 * Returns an empty array if the setting is not a valid source of options.
	 *
	 * @param Settings\AbstractSetting|null $setting
	 * @return ChoiceControlOption[]
	 */
	public static function tryGenerateFromSetting($setting) {
		if ( $setting instanceof Settings\EnumSetting ) {
			return static::fromEnumSetting($setting);
		} elseif ( $setting instanceof Settings\WithSchema\SettingWithSchema ) {
			$schema = $setting->getSchema();
			if ( $schema instanceof Enum ) {
				return static::fromEnumSchema($schema);
			}
		}
		return [];
	}

	/**
	 * @param Settings\EnumSetting $setting
	 * @return ChoiceControlOption[]
	 */
	public static function fromEnumSetting(Settings\EnumSetting $setting) {
		$results = array();

		foreach ($setting->getEnumValues() as $value) {
			$results[] = static::createFromValue(
				$value,
				$setting->getChoiceDetails($value),
				$setting->isChoiceEnabled($value)
			);
		}

		return $results;
	}

	public static function fromEnumSchema(Enum $schema) {
		$results = array();

		foreach ($schema->getEnumValues() as $value) {
			$results[] = static::createFromValue(
				$value,
				$schema->getValueDetails($value),
				$schema->isValueEnabled($value)
			);
		}

		return $results;
	}

	protected static function createFromValue($value, $details, $enabled = true) {
		if ( !empty($details) ) {
			return new ChoiceControlOption(
				$value,
				$details['label'],
				array(
					'description' => $details['description'],
					'enabled'     => $enabled,
					'icon'        => $details['icon'],
				)
			);
		} else {
			if ( $value === null ) {
				$label = 'Default';
			} else {
				$label = is_string($value) ? $value : wp_json_encode($value);
				$label = ucwords(preg_replace('/[_-]+/', ' ', $label));
			}
			return new ChoiceControlOption($value, $label, array(
				'enabled' => $enabled,
			));
		}
	}
}