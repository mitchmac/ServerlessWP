<?php

/**
 * @TODO: Consider making this a generic WP_Parser_Token or similar.
 *        We can also make WP_MySQL_Token extend the generic one.
 * @TODO: Document the class.
 */
class WP_MySQL_Token {
	/**
	 * @TODO: Review and document these properties and their visibility.
	 */
	public $type;
	public $text;

	public function __construct( $type, $text ) {
		$this->type = $type;
		$this->text = $text;
	}

	public function get_type() {
		return $this->type;
	}

	public function get_text() {
		return $this->text;
	}

	public function get_name() {
		return WP_MySQL_Lexer::get_token_name( $this->type );
	}

	public function __toString() {
		return $this->text . '<' . $this->type . ',' . $this->get_name() . '>';
	}
}
