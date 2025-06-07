<?php

/**
 * A node in parse tree.
 *
 * This class represents a node in the parse tree that is produced by WP_Parser.
 * A node corresponds to the related grammar rule that was matched by the parser.
 * Each node can contain children, consisting of other nodes and grammar tokens.
 * In this way, a parser node constitutes a recursive structure that represents
 * a parse (sub)tree at each level of the full grammar tree.
 */
class WP_Parser_Node {
	/**
	 * @TODO: Review and document these properties and their visibility.
	 */
	public $rule_id;
	public $rule_name;
	private $children = array();

	public function __construct( $rule_id, $rule_name ) {
		$this->rule_id   = $rule_id;
		$this->rule_name = $rule_name;
	}

	public function append_child( $node ) {
		$this->children[] = $node;
	}

	/**
	 * Flatten the matched rule fragments as if their children were direct
	 * descendants of the current rule.
	 *
	 * What are rule fragments?
	 *
	 * When we initially parse the grammar file, it has compound rules such
	 * as this one:
	 *
	 *      query ::= EOF | ((simpleStatement | beginWork) ((SEMICOLON_SYMBOL EOF?) | EOF))
	 *
	 * Building a parser that can understand such rules is way more complex than building
	 * a parser that only follows simple rules, so we flatten those compound rules into
	 * simpler ones. The above rule would be flattened to:
	 *
	 *      query ::= EOF | %query0
	 *      %query0 ::= %%query01 %%query02
	 *      %%query01 ::= simpleStatement | beginWork
	 *      %%query02 ::= SEMICOLON_SYMBOL EOF_zero_or_one | EOF
	 *      EOF_zero_or_one ::= EOF | ε
	 *
	 * This factorization happens in "convert-grammar.php".
	 *
	 * "Fragments" are intermediate artifacts whose names are not in the original grammar.
	 * They are extremely useful for the parser, but the API consumer should never have to
	 * worry about them. Fragment names start with a percent sign ("%").
	 *
	 * The code below inlines every fragment back in its parent rule.
	 *
	 * We could optimize this. The current $match may be discarded later on so any inlining
	 * effort here would be wasted. However, inlining seems cheap and doing it bottom-up here
	 * is **much** easier than reprocessing the parse tree top-down later on.
	 *
	 * The following parse tree:
	 *
	 * [
	 *      'query' => [
	 *          [
	 *              '%query01' => [
	 *                  [
	 *                      'simpleStatement' => [
	 *                          MySQLToken(MySQLLexer::WITH_SYMBOL, 'WITH')
	 *                      ],
	 *                      '%query02' => [
	 *                          [
	 *                              'simpleStatement' => [
	 *                                  MySQLToken(MySQLLexer::WITH_SYMBOL, 'WITH')
	 *                          ]
	 *                      ],
	 *                  ]
	 *              ]
	 *          ]
	 *      ]
	 * ]
	 *
	 * Would be inlined as:
	 *
	 * [
	 *      'query' => [
	 *          [
	 *              'simpleStatement' => [
	 *                  MySQLToken(MySQLLexer::WITH_SYMBOL, 'WITH')
	 *              ]
	 *          ],
	 *          [
	 *              'simpleStatement' => [
	 *                  MySQLToken(MySQLLexer::WITH_SYMBOL, 'WITH')
	 *              ]
	 *          ]
	 *      ]
	 * ]
	 */
	public function merge_fragment( $node ) {
		$this->children = array_merge( $this->children, $node->children );
	}

	public function has_child(): bool {
		return count( $this->children ) > 0;
	}

	public function has_child_node( ?string $rule_name = null ): bool {
		foreach ( $this->children as $child ) {
			if (
				$child instanceof WP_Parser_Node
				&& ( null === $rule_name || $child->rule_name === $rule_name )
			) {
				return true;
			}
		}
		return false;
	}

	public function has_child_token( ?int $token_id = null ): bool {
		foreach ( $this->children as $child ) {
			if (
				$child instanceof WP_Parser_Token
				&& ( null === $token_id || $child->id === $token_id )
			) {
				return true;
			}
		}
		return false;
	}


	public function get_first_child() {
		return $this->children[0] ?? null;
	}

	public function get_first_child_node( ?string $rule_name = null ): ?WP_Parser_Node {
		foreach ( $this->children as $child ) {
			if (
				$child instanceof WP_Parser_Node
				&& ( null === $rule_name || $child->rule_name === $rule_name )
			) {
				return $child;
			}
		}
		return null;
	}

	public function get_first_child_token( ?int $token_id = null ): ?WP_Parser_Token {
		foreach ( $this->children as $child ) {
			if (
				$child instanceof WP_Parser_Token
				&& ( null === $token_id || $child->id === $token_id )
			) {
				return $child;
			}
		}
		return null;
	}

	public function get_first_descendant_node( ?string $rule_name = null ): ?WP_Parser_Node {
		$nodes = array( $this );
		while ( count( $nodes ) ) {
			$node  = array_shift( $nodes );
			$child = $node->get_first_child_node( $rule_name );
			if ( $child ) {
				return $child;
			}
			$children = $node->get_child_nodes();
			if ( count( $children ) > 0 ) {
				array_push( $nodes, ...$children );
			}
		}
		return null;
	}

	public function get_first_descendant_token( ?int $token_id = null ): ?WP_Parser_Token {
		$nodes = array( $this );
		while ( count( $nodes ) ) {
			$node  = array_shift( $nodes );
			$child = $node->get_first_child_token( $token_id );
			if ( $child ) {
				return $child;
			}
			$children = $node->get_child_nodes();
			if ( count( $children ) > 0 ) {
				array_push( $nodes, ...$children );
			}
		}
		return null;
	}

	public function get_children(): array {
		return $this->children;
	}

	public function get_child_nodes( ?string $rule_name = null ): array {
		$nodes = array();
		foreach ( $this->children as $child ) {
			if (
				$child instanceof WP_Parser_Node
				&& ( null === $rule_name || $child->rule_name === $rule_name )
			) {
				$nodes[] = $child;
			}
		}
		return $nodes;
	}

	public function get_child_tokens( ?int $token_id = null ): array {
		$tokens = array();
		foreach ( $this->children as $child ) {
			if (
				$child instanceof WP_Parser_Token
				&& ( null === $token_id || $child->id === $token_id )
			) {
				$tokens[] = $child;
			}
		}
		return $tokens;
	}

	public function get_descendants(): array {
		$nodes           = array( $this );
		$all_descendants = array();
		while ( count( $nodes ) ) {
			$node            = array_shift( $nodes );
			$all_descendants = array_merge( $all_descendants, $node->get_children() );
			$children        = $node->get_child_nodes();
			if ( count( $children ) > 0 ) {
				array_push( $nodes, ...$children );
			}
		}
		return $all_descendants;
	}

	public function get_descendant_nodes( ?string $rule_name = null ): array {
		$nodes           = array( $this );
		$all_descendants = array();
		while ( count( $nodes ) ) {
			$node            = array_shift( $nodes );
			$all_descendants = array_merge( $all_descendants, $node->get_child_nodes( $rule_name ) );
			$children        = $node->get_child_nodes();
			if ( count( $children ) > 0 ) {
				array_push( $nodes, ...$children );
			}
		}
		return $all_descendants;
	}

	public function get_descendant_tokens( ?int $token_id = null ): array {
		$nodes           = array( $this );
		$all_descendants = array();
		while ( count( $nodes ) ) {
			$node            = array_shift( $nodes );
			$all_descendants = array_merge( $all_descendants, $node->get_child_tokens( $token_id ) );
			$children        = $node->get_child_nodes();
			if ( count( $children ) > 0 ) {
				array_push( $nodes, ...$children );
			}
		}
		return $all_descendants;
	}

	/**
	 * Get the byte offset in the input SQL string where this node begins.
	 *
	 * @return int
	 */
	public function get_start(): int {
		return $this->get_first_descendant_token()->start;
	}

	/**
	 * Get the byte length of this node in the input SQL string.
	 *
	 * @return int
	 */
	public function get_length(): int {
		$tokens     = $this->get_descendant_tokens();
		$last_token = end( $tokens );
		$start      = $this->get_start();
		return $last_token->start + $last_token->length - $start;
	}

	/*
	 * @TODO: Let's implement a more powerful AST-querying API.
	 *        See: https://github.com/WordPress/sqlite-database-integration/pull/164#discussion_r1855230501
	 */
}
