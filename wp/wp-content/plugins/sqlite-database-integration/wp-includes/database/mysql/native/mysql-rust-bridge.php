<?php

/**
 * Bridge helpers for the optional Rust MySQL lexer/parser extension.
 * PHP keeps the grammar object, while Rust owns the exported parser state.
 */

/**
 * Export grammar internals for the native parser.
 *
 * @param WP_Parser_Grammar $grammar Parser grammar.
 * @return array<string, mixed>
 */
function wp_sqlite_mysql_native_export_grammar( WP_Parser_Grammar $grammar ): array {
	return array(
		'highest_terminal_id'         => $grammar->highest_terminal_id,
		'rules'                       => $grammar->rules,
		'lookahead_is_match_possible' => $grammar->lookahead_is_match_possible,
		'rule_names'                  => $grammar->rule_names,
		'fragment_ids'                => $grammar->fragment_ids,
	);
}
