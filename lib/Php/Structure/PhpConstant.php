<?php


namespace Goetas\Xsd\XsdToPhp\Php\Structure;


class PhpConstant {
	protected $name;
	protected $value;

	/**
	 * @param string $name
	 * @param string $value
	 */
	public function __construct( $name, $value ) {
		$this->name  = $name;
		$this->value = $value;
	}

	/**
	 * @return string
	 */
	public function getName() {
		return $this->name;
	}

	/**
	 * @param string $name
	 */
	public function setName( $name ) {
		$this->name = $name;
	}

	/**
	 * @return string
	 */
	public function getValue() {
		return $this->value;
	}

	/**
	 * @param string $value
	 */
	public function setValue( $value ) {
		$this->value = $value;
	}
}
