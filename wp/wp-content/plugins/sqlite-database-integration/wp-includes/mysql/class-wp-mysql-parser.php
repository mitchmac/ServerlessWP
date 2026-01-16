<?php

class WP_MySQL_Parser extends WP_Parser {
	/**
	 * The current query AST.
	 *
	 * @var WP_Parser_Node|null
	 */
	private $current_ast;

	/**
	 * Parse the next query from the input SQL string.
	 *
	 * This method reads tokens until a query is parsed, or the parsing fails.
	 * It returns a boolean indicating whether a query was successfully parsed.
	 *
	 * Example:
	 *
	 *     // Parse all queries in the input SQL string.
	 *     $parser = new WP_MySQL_Parser( $sql );
	 *     while ( $parser->next_query() ) {
	 *         $ast = $parser->get_query_ast();
	 *         if ( ! $ast ) {
	 *             // The parsing failed.
	 *         }
	 *         // The query was successfully parsed.
	 *     }
	 *
	 * @return bool Whether a query was successfully parsed.
	 */
	public function next_query(): bool {
		if ( $this->position >= count( $this->tokens ) ) {
			return false;
		}
		$this->current_ast = $this->parse();
		return true;
	}

	/**
	 * Get the current query AST.
	 *
	 * When no query has been parsed yet, the parsing failed, or the end of the
	 * input was reached, this method returns null.
	 *
	 * @see WP_MySQL_Parser::next_query() for usage example.
	 *
	 * @return WP_Parser_Node|null The current query AST, or null if no query was parsed.
	 */
	public function get_query_ast(): ?WP_Parser_Node {
		return $this->current_ast;
	}
}
