<?php

namespace YahnisElsts\AdminMenuEditor\Customizable\Schemas;

class Record extends Collection {
	public function __construct(Schema $keySchema, Schema $itemSchema, $label = null) {
		//Keys must be strings.
		if ( !($keySchema instanceof StringSchema) ) {
			throw new \InvalidArgumentException('Key schema for Record must be a string schema');
		}

		parent::__construct($itemSchema, $keySchema, $label);
		$this->keySchema = $keySchema;
	}
}