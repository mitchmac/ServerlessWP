<?php

/**
 * Parser node backed by a native (Rust) AST.
 *
 * Constructed by the native MySQL parser extension. Read methods delegate
 * into the Rust-owned AST so children are never copied into PHP unless a
 * caller actually walks the tree. On the first mutation (append_child or
 * merge_fragment), the node materializes its children into the inherited
 * `$children` array and behaves like a plain WP_Parser_Node from then on.
 */
class WP_MySQL_Native_Parser_Node extends WP_Parser_Node {
	private $was_mutated = false;

	public function __construct( $rule_id, $rule_name ) {
		parent::__construct( $rule_id, $rule_name );
	}

	public function __destruct() {
		if ( function_exists( 'wp_sqlite_mysql_native_ast_release_wrapper' ) ) {
			wp_sqlite_mysql_native_ast_release_wrapper( $this );
		}
	}

	/** @inheritDoc */
	public function append_child( $node ) {
		$this->materialize_native_children();
		parent::append_child( $node );
	}

	/** @inheritDoc */
	public function merge_fragment( $node ) {
		$this->materialize_native_children();
		if ( $node instanceof self ) {
			$node->materialize_native_children();
		}
		parent::merge_fragment( $node );
	}

	/** @inheritDoc */
	public function has_child(): bool {
		if ( $this->was_mutated ) {
			return parent::has_child();
		}
		return wp_sqlite_mysql_native_ast_has_child( $this );
	}

	/** @inheritDoc */
	public function has_child_node( ?string $rule_name = null ): bool {
		if ( $this->was_mutated ) {
			return parent::has_child_node( $rule_name );
		}
		return wp_sqlite_mysql_native_ast_has_child_node( $this, $rule_name );
	}

	/** @inheritDoc */
	public function has_child_token( ?int $token_id = null ): bool {
		if ( $this->was_mutated ) {
			return parent::has_child_token( $token_id );
		}
		return wp_sqlite_mysql_native_ast_has_child_token( $this, $token_id );
	}

	/** @inheritDoc */
	public function get_first_child() {
		if ( $this->was_mutated ) {
			return parent::get_first_child();
		}
		return wp_sqlite_mysql_native_ast_get_first_child( $this );
	}

	/** @inheritDoc */
	public function get_first_child_node( ?string $rule_name = null ): ?WP_Parser_Node {
		if ( $this->was_mutated ) {
			return parent::get_first_child_node( $rule_name );
		}
		return wp_sqlite_mysql_native_ast_get_first_child_node( $this, $rule_name );
	}

	/** @inheritDoc */
	public function get_first_child_token( ?int $token_id = null ): ?WP_Parser_Token {
		if ( $this->was_mutated ) {
			return parent::get_first_child_token( $token_id );
		}
		return wp_sqlite_mysql_native_ast_get_first_child_token( $this, $token_id );
	}

	/** @inheritDoc */
	public function get_first_descendant_node( ?string $rule_name = null ): ?WP_Parser_Node {
		if ( $this->was_mutated ) {
			return parent::get_first_descendant_node( $rule_name );
		}
		return wp_sqlite_mysql_native_ast_get_first_descendant_node( $this, $rule_name );
	}

	/** @inheritDoc */
	public function get_first_descendant_token( ?int $token_id = null ): ?WP_Parser_Token {
		if ( $this->was_mutated ) {
			return parent::get_first_descendant_token( $token_id );
		}
		return wp_sqlite_mysql_native_ast_get_first_descendant_token( $this, $token_id );
	}

	/** @inheritDoc */
	public function get_children(): array {
		if ( $this->was_mutated ) {
			return parent::get_children();
		}
		return wp_sqlite_mysql_native_ast_get_children( $this );
	}

	/** @inheritDoc */
	public function get_child_nodes( ?string $rule_name = null ): array {
		if ( $this->was_mutated ) {
			return parent::get_child_nodes( $rule_name );
		}
		return wp_sqlite_mysql_native_ast_get_child_nodes( $this, $rule_name );
	}

	/** @inheritDoc */
	public function get_child_tokens( ?int $token_id = null ): array {
		if ( $this->was_mutated ) {
			return parent::get_child_tokens( $token_id );
		}
		return wp_sqlite_mysql_native_ast_get_child_tokens( $this, $token_id );
	}

	/** @inheritDoc */
	public function get_descendants(): array {
		if ( $this->was_mutated ) {
			return parent::get_descendants();
		}
		return wp_sqlite_mysql_native_ast_get_descendants( $this );
	}

	/** @inheritDoc */
	public function get_descendant_nodes( ?string $rule_name = null ): array {
		if ( $this->was_mutated ) {
			return parent::get_descendant_nodes( $rule_name );
		}
		return wp_sqlite_mysql_native_ast_get_descendant_nodes( $this, $rule_name );
	}

	/** @inheritDoc */
	public function get_descendant_tokens( ?int $token_id = null ): array {
		if ( $this->was_mutated ) {
			return parent::get_descendant_tokens( $token_id );
		}
		return wp_sqlite_mysql_native_ast_get_descendant_tokens( $this, $token_id );
	}

	/** @inheritDoc */
	public function get_start(): int {
		if ( $this->was_mutated ) {
			return parent::get_start();
		}
		return wp_sqlite_mysql_native_ast_get_start( $this );
	}

	/** @inheritDoc */
	public function get_length(): int {
		if ( $this->was_mutated ) {
			return parent::get_length();
		}
		return wp_sqlite_mysql_native_ast_get_length( $this );
	}

	private function materialize_native_children(): void {
		if ( $this->was_mutated ) {
			return;
		}

		$this->children    = wp_sqlite_mysql_native_ast_get_children( $this );
		$this->was_mutated = true;
		if ( function_exists( 'wp_sqlite_mysql_native_ast_materialize_wrapper' ) ) {
			wp_sqlite_mysql_native_ast_materialize_wrapper( $this );
		}
	}
}
