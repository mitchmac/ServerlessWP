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
	public $children = array();

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

	public function has_child( $rule_name ) {
		foreach ( $this->children as $child ) {
			if ( ( $child instanceof WP_Parser_Node && $child->rule_name === $rule_name ) ) {
				return true;
			}
		}
		return false;
	}

	public function has_token( $token_id = null ) {
		foreach ( $this->children as $child ) {
			if ( $child instanceof WP_MySQL_Token && (
				null === $token_id ||
				$child->type === $token_id
			) ) {
				return true;
			}
		}
		return false;
	}

	public function get_token( $token_id = null ) {
		foreach ( $this->children as $child ) {
			if ( $child instanceof WP_MySQL_Token && (
				null === $token_id ||
				$child->type === $token_id
			) ) {
				return $child;
			}
		}
		return null;
	}

	public function get_child( $rule_name = null ) {
		foreach ( $this->children as $child ) {
			if ( $child instanceof WP_Parser_Node && (
				$child->rule_name === $rule_name ||
				null === $rule_name
			) ) {
				return $child;
			}
		}
	}

	public function get_descendant( $rule_name ) {
		$parse_trees = array( $this );
		while ( count( $parse_trees ) ) {
			$parse_tree = array_pop( $parse_trees );
			if ( $parse_tree->rule_name === $rule_name ) {
				return $parse_tree;
			}
			array_push( $parse_trees, ...$parse_tree->get_children() );
		}
		return null;
	}

	public function get_descendants( $rule_name ) {
		$parse_trees     = array( $this );
		$all_descendants = array();
		while ( count( $parse_trees ) ) {
			$parse_tree      = array_pop( $parse_trees );
			$all_descendants = array_merge( $all_descendants, $parse_tree->get_children( $rule_name ) );
			array_push( $parse_trees, ...$parse_tree->get_children() );
		}
		return $all_descendants;
	}

	public function get_children( $rule_name = null ) {
		$matches = array();
		foreach ( $this->children as $child ) {
			if ( $child instanceof WP_Parser_Node && (
				null === $rule_name ||
				$child->rule_name === $rule_name
			) ) {
				$matches[] = $child;
			}
		}
		return $matches;
	}
}
