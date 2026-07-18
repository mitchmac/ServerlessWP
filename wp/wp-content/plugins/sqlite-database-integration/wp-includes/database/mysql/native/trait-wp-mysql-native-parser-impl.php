<?php

/**
 * Native-mode `WP_MySQL_Parser` implementation, delivered as a trait.
 *
 * The class that uses this trait (`WP_MySQL_Parser` in native mode)
 * extends the pure-PHP `WP_Parser` so callers' `instanceof WP_Parser`
 * checks keep working, while the actual parsing work is delegated to
 * the Rust-registered `WP_MySQL_Native_Parser` instance held in
 * `$this->native`. `WP_Parser`'s state (`$grammar`, `$tokens`,
 * `$position`) stays inert in native mode — the trait's overrides
 * never read it.
 *
 * Adding a public method here is enough to plumb a new public method
 * through to the native parser; the using class does not need touching.
 */
trait WP_MySQL_Native_Parser_Impl {
	/**
	 * @var WP_MySQL_Native_Parser
	 */
	private $native;

	/**
	 * @param WP_Parser_Grammar                          $grammar
	 * @param array<WP_Parser_Token>|WP_MySQL_Native_Token_Stream $tokens
	 */
	public function __construct( WP_Parser_Grammar $grammar, $tokens ) {
		// WP_Parser's `array $tokens` constructor signature can't accept
		// the native token stream object; its `$this->tokens` /
		// `$this->position` state is inert in native mode anyway, so we
		// pass an empty array to satisfy the parent contract and keep
		// the actual tokens on the native parser.
		parent::__construct( $grammar, array() );
		$this->native = new WP_MySQL_Native_Parser( $grammar, $tokens );
	}

	/**
	 * @param array<WP_Parser_Token>|WP_MySQL_Native_Token_Stream $tokens
	 */
	public function reset_tokens( $tokens ): void {
		$this->native->reset_tokens( $tokens );
	}

	public function next_query(): bool {
		return $this->native->next_query();
	}

	public function get_query_ast(): ?WP_Parser_Node {
		return $this->native->get_query_ast();
	}

	public function parse() {
		return $this->native->parse();
	}
}
