<?php

/**
 * This script parses the ANTLR v4 formatted MySQL grammar from "MySQLParser.g4",
 * flattens it, expands rule quantifiers, and exports it as a compressed PHP array.
 * The script understands only a subset of the ANTLR v4 format, including rules,
 * branches, quantifiers, and conditional MySQL server version specifiers.
 *
 * @TODO Propagate MySQL version specifiers up to the exported PHP array.
 * @TODO Migrate the current regex-based solution to a proper grammar parser.
 */

require_once __DIR__ . '/../wp-includes/parser/class-wp-parser-grammar.php';
require_once __DIR__ . '/../wp-includes/mysql/class-wp-mysql-lexer.php';

const GRAMMAR_FILE = __DIR__ . '/../wp-includes/mysql/mysql-grammar.php';

// Convert the original MySQLParser.g4 grammar to a JSON format.
// The grammar is also flattened and expanded to an ebnf-to-json-like format.
// Additionally, it captures version specifiers to be used in the parser.

// Recursive pattern to capture matching parentheses, including nested ones.
const PARENS_REGEX = '\((?:[^()]+|(?R))*+\)[?*+]?';

// 1. Parse MySQLParser.g4 grammar.
$grammar = file_get_contents( __DIR__ . '/MySQLParser.g4' );
$grammar = preg_replace( '~/\*[\s\S]*?\*/|//.*$~m', '', $grammar ); // remove comments
$grammar = preg_replace( '/^.*?\s(?=\w+:)/ms', '', $grammar, 1 ); // remove all until first rule
$parts   = explode( ';', $grammar ); // split grammar by ";"

function process_rule( string $rule ) {
	if ( preg_match( '/^[\w%?*+]+$/', $rule ) ) {
		return $rule;
	}

	$parens_regex = PARENS_REGEX;

	// Match empty branches in the original grammar. The equal to "ε", making the parent optional.
	// This matches a "|" not followed by any rule, e.g. (A | B |) or (A | | B), etc.
	$empty_branch_regex = '\|(?=\s*(?:\||\)|$))';

	// extract rule branches (split by | not inside parentheses)
	preg_match_all( "/((?:[^()|]|$parens_regex)+|$empty_branch_regex)/", $rule, $matches );
	$branches = $matches[0];
	$subrules = array();
	foreach ( $branches as $branch ) {
		$branch = trim( $branch );

		// empty branch equals to "ε"
		if ( '|' === $branch ) {
			$subrules[] = array( 'ε' );
			continue;
		}

		// extract version specifiers (like "{serverVersion >= 80000}?")
		$versions = null;
		if ( preg_match( '/^\{(.+?)}\?\s+(.*)$/s', $branch, $matches ) ) {
			$versions = $matches[1];
			$branch   = $matches[2];
		}

		// remove named accessors
		$branch = preg_replace( '/\w+\s*=\s*/', '', $branch );

		// remove labels
		$branch = preg_replace( '/#\s*\w+/', '', $branch );

		// extract branch sequence (split by whitespace not inside parentheses)
		preg_match_all( "/(?:[^()\s]|$parens_regex)+/s", $branch, $matches );
		$sequence = array();
		foreach ( $matches[0] as $part ) {
			// extract subrule (inside parentheses), capture quantifiers (?, *, +)
			if ( '(' === $part[0] ) {
				$last       = $part[ strlen( $part ) - 1 ];
				$quantifier = null;
				if ( '?' === $last || '*' === $last || '+' === $last ) {
					$part       = substr( $part, 0, -1 );
					$quantifier = $last;
				}
				$subrule = array( 'value' => process_rule( substr( $part, 1, -1 ) ) );
				if ( null !== $quantifier ) {
					$subrule['quantifier'] = $quantifier;
				}
				$sequence[] = $subrule;
			} else {
				$sequence[] = process_rule( $part );
			}
		}
		$subrule = null !== $versions ? array(
			array(
				'value'    => $sequence,
				'versions' => $versions,
			),
		) : $sequence;
		if ( count( $subrule ) > 0 ) {
			$subrules[] = $subrule;
		}
	}
	return $subrules;
}

$rules = array();
foreach ( $parts as $i => $part ) {
	$part = trim( $part );
	if ( '' === $part ) {
		continue;
	}

	$rule_parts = explode( ':', $part );
	if ( count( $rule_parts ) !== 2 ) {
		throw new Exception( 'Invalid rule: ' . $part );
	}
	$rules[ trim( $rule_parts[0] ) ] = process_rule( $rule_parts[1] );
}

//echo json_encode($rules, JSON_PRETTY_PRINT); return;

// 2. Flatten the grammar.
$flat = array();
function flatten_rule( $name, $rule ) {
	global $flat;

	if ( is_string( $rule ) ) {
		return $rule;
	}

	$values   = isset( $rule['value'] ) ? $rule['value'] : $rule;
	$branches = array();
	foreach ( $values as $i => $branch ) {
		$branches[] = flatten_rule( $name . $i, $branch );
	}

	if ( isset( $rule['value'] ) ) {
		$new_name = '%' . $name;
		$flat[]   = array_merge(
			$rule,
			array(
				'name'  => $new_name,
				'value' => $branches,
			)
		);
		return $new_name . ( $rule['quantifier'] ?? '' );
	} else {
		return $branches;
	}
}

$flat_rules = array();
foreach ( $rules as $name => $rule ) {
	$flat_rules[] = array(
		'name'  => $name,
		'value' => flatten_rule( $name, $rule ),
	);
	$flat_rules   = array_merge( $flat_rules, $flat );
	$flat         = array();
}

//echo json_encode($flat_rules, JSON_PRETTY_PRINT); return;

// 3. Expand the grammar.
$expanded = array();
function expand( $value ) {
	global $expanded;

	$last = $value[ strlen( $value ) - 1 ];
	$name = substr( $value, 0, -1 );
	if ( '?' === $last ) {
		$expanded[] = array(
			'name'  => $value,
			'value' => array( array( $name ), array( 'ε' ) ),
		);
	} elseif ( '*' === $last ) {
		$expanded[] = array(
			'name'  => $value,
			'value' => array( array( $name, $value ), array( $name ), array( 'ε' ) ),
		);
	} elseif ( '+' === $last ) {
		$expanded[] = array(
			'name'  => $value,
			'value' => array( array( $name, $value ), array( $name ) ),
		);
	}
}

foreach ( $flat_rules as $rule ) {
	foreach ( $rule['value'] as $i => $branch ) {
		$values = is_string( $branch ) ? array( $branch ) : $branch;
		foreach ( $values as $value ) {
			expand( $value );
		}
	}

	if ( isset( $rule['quantifier'] ) ) {
		$value = $rule['name'] . $rule['quantifier'];
		expand( $value );
		unset( $rule['quantifier'] );
	}

	$expanded[ $rule['name'] ] = $rule;
}

foreach ( $expanded as $i => $rule ) {
	if ( is_string( $rule['value'][0] ?? null ) ) {
		$expanded[ $i ]['value'] = array( $rule['value'] );
	}
}

//echo json_encode($expanded, JSON_PRETTY_PRINT); return;

// 4. Export the grammar as a PHP array.
$grammar = $expanded;

function export_as_php_var( $variable ) {
	if ( is_array( $variable ) ) {
		$array_notation = '[';
		$keys           = array_keys( $variable );
		$last_key       = end( $keys );
		$export_keys    = json_encode( array_keys( $variable ) ) !== json_encode( range( 0, count( $variable ) - 1 ) );
		foreach ( $variable as $key => $value ) {
			if ( $export_keys ) {
				$array_notation .= var_export( $key, true ) . '=>';
			}
			$array_notation .= export_as_php_var( $value );
			if ( $key !== $last_key ) {
				$array_notation .= ',';
			}
		}
		$array_notation .= ']';
		return $array_notation;
	}
	return var_export( $variable, true );
}

// Lookup tables
$rules_offset       = 2000;
$rule_id_by_name    = array();
$rule_index_by_name = array();
foreach ( $grammar as $rule ) {
	$rules_ids[]                         = $rule['name'];
	$rule_index_by_name[ $rule['name'] ] = ( count( $rules_ids ) - 1 );
	$rule_id_by_name[ $rule['name'] ]    = $rule_index_by_name[ $rule['name'] ] + $rules_offset;
	$compressed_grammar[ $rule['name'] ] = array();
}

// Convert rules ids and token ids to integers
$compressed_grammar = array();
foreach ( $grammar as $rule ) {
	$new_branches = array();
	foreach ( $rule['value'] as $branch ) {
		$new_branch = array();
		foreach ( $branch as $i => $name ) {
			$is_terminal = ! isset( $rule_id_by_name[ $name ] );
			if ( $is_terminal ) {
				$token_id     = 'ε' === $name
					? WP_Parser_Grammar::EMPTY_RULE_ID
					: WP_MySQL_Lexer::get_token_id( $name );
				$new_branch[] = $token_id;
			} else {
				// Use rule id to avoid conflicts with token ids
				$new_branch[] = $rule_id_by_name[ $name ];
			}
		}
		$new_branches[] = $new_branch;
	}
	// Use rule index
	$compressed_grammar[ $rule_index_by_name[ $rule['name'] ] ] = $new_branches;
}

// Compress the fragment rules names – they take a lot of disk space and are
// inlined in the final parse tree anyway.
$last_fragment = 1;
foreach ( $rules_ids as $id => $name ) {
	if (
		'%' === $name[0] ||
		str_ends_with( $name, '?' ) ||
		str_ends_with( $name, '*' ) ||
		str_ends_with( $name, '+' )
	) {
		$rules_ids[ $id ] = '%f' . $last_fragment;
		++$last_fragment;
	}
}

$full_grammar = array(
	'rules_offset' => $rules_offset,
	'rules_names'  => $rules_ids,
	'grammar'      => $compressed_grammar,
);

$php_array = export_as_php_var( $full_grammar );
file_put_contents(
	GRAMMAR_FILE,
	"<?php\n"
	. "// THIS FILE IS GENERATED. DO NOT MODIFY IT MANUALLY.\n"
	. "// phpcs:disable\n"
	. "return $php_array;\n"
);
