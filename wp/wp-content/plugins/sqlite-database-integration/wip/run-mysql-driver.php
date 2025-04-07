<?php // phpcs:disable WordPress.Files.FileName.InvalidClassFileName

require_once __DIR__ . '/MySQLLexer.php';
require_once __DIR__ . '/DynamicRecursiveDescentParser.php';
require_once __DIR__ . '/SQLiteDriver.php';

$query = <<<SQL
WITH
    mytable AS (select 1 as a, `b`.c from dual),
    mytable2 AS (select 1 as a, `b`.c from dual)
SELECT HIGH_PRIORITY SQL_SMALL_RESULT DISTINCT
	CONCAT("a", "b"),
	UPPER(z),
    DATE_FORMAT(col_a, '%Y-%m-%d %H:%i:%s') as formatted_date,
    DATE_ADD(col_b, INTERVAL 5 MONTH ) as date_plus_one,
	col_a
FROM
my_table FORCE INDEX (`idx_department_id`),
(SELECT `mycol`, 997482686 FROM "mytable") as subquery
LEFT JOIN (SELECT a_column_yo from mytable) as t2
    ON (t2.id = mytable.id AND t2.id = 1)
WHERE 1 = 3
GROUP BY col_a, col_b
HAVING 1 = 2
UNION SELECT * from table_cde
ORDER BY col_a DESC, col_b ASC
FOR UPDATE;
;
SQL;


$grammar_data = include './grammar.php';
$grammar      = new Grammar( $grammar_data );
$driver       = new MySQLonSQLiteDriver( $grammar );
// $parse_tree = $parser->parse();
// print_r($parse_tree);
// die();
// echo 'a';

$query = <<<SQL
SELECT VALUES("col_a")
SQL;

echo $driver->run_query( $query );
die();
// $transformer = new SQLTransformer($parse_tree, 'sqlite');
// $expression = $transformer->transform();
// print_r($expression);

class MySQLonSQLiteDriver {
	private $grammar                 = false;
	private $has_sql_calc_found_rows = false;
	private $has_found_rows_call     = false;
	private $last_calc_rows_result   = null;

	public function __construct( $grammar ) {
		$this->grammar = $grammar;
	}

	public function run_query( $query ) {
		$this->has_sql_calc_found_rows = false;
		$this->has_found_rows_call     = false;
		$this->last_calc_rows_result   = null;

		$parser     = new WP_MySQL_Parser( $this->grammar, tokenize_query( $query ) );
		$parse_tree = $parser->parse();
		$expr       = $this->translate_query( $parse_tree );
		$expr       = $this->rewrite_sql_calc_found_rows( $expr );

		$sqlite_query = SQLiteQueryBuilder::stringify( $expr ) . '';

		// Returning the expery just for now for testing. In the end, we'll
		// run it and return the SQLite interaction result.
		return $sqlite_query;
	}

	private function rewrite_sql_calc_found_rows( SQLiteExpression $expr ) {
		if ( $this->has_found_rows_call && ! $this->has_sql_calc_found_rows && null === $this->last_calc_rows_result ) {
			throw new Exception( 'FOUND_ROWS() called without SQL_CALC_FOUND_ROWS' );
		}

		if ( $this->has_sql_calc_found_rows ) {
			$expr_to_run = $expr;
			if ( $this->has_found_rows_call ) {
				$expr_without_found_rows = new SQLiteExpression( array() );
				foreach ( $expr->elements as $k => $element ) {
					if ( SQLiteToken::TYPE_IDENTIFIER === $element->type && 'FOUND_ROWS' === $element->value ) {
						$expr_without_found_rows->add_token(
							SQLiteTokenFactory::value( 0 )
						);
					} else {
						$expr_without_found_rows->add_token( $element );
					}
				}
				$expr_to_run = $expr_without_found_rows;
			}

			// ...remove the LIMIT clause...
			$query = 'SELECT COUNT(*) as cnt FROM (' . SQLiteQueryBuilder::stringify( $expr_to_run ) . ');';

			// ...run $query...
			// $result = ...

			$this->last_calc_rows_result = $result['cnt'];
		}

		if ( ! $this->has_found_rows_call ) {
			return $expr;
		}

		$expr_with_found_rows_result = new SQLiteExpression( array() );
		foreach ( $expr->elements as $k => $element ) {
			if ( SQLiteToken::TYPE_IDENTIFIER === $element->type && 'FOUND_ROWS' === $element->value ) {
				$expr_with_found_rows_result->add_token(
					SQLiteTokenFactory::value( $this->last_calc_rows_result )
				);
			} else {
				$expr_with_found_rows_result->add_token( $element );
			}
		}
		return $expr_with_found_rows_result;
	}

	private function translate_query( $parse_tree ) {
		if ( null === $parse_tree ) {
			return null;
		}

		if ( $parse_tree instanceof WP_MySQL_Token ) {
			$token = $parse_tree;
			switch ( $token->type ) {
				case WP_MySQL_Lexer::EOF:
					return new SQLiteExpression( array() );

				case WP_MySQL_Lexer::IDENTIFIER:
					return new SQLiteExpression(
						array(
							SQLiteTokenFactory::identifier(
								trim( $token->text, '`"' )
							),
						)
					);

				default:
					return new SQLiteExpression(
						array(
							SQLiteTokenFactory::raw( $token->text ),
						)
					);
			}
		}

		if ( ! ( $parse_tree instanceof WP_Parser_Node ) ) {
			throw new Exception( 'translateQuery only accepts MySQLToken and ParseTree instances' );
		}

		$rule_name = $parse_tree->rule_name;

		switch ( $rule_name ) {
			case 'indexHintList':
				// SQLite doesn't support index hints. Let's
				// skip them.
				return null;

			case 'querySpecOption':
				$token = $parse_tree->get_token();
				switch ( $token->type ) {
					case WP_MySQL_Lexer::ALL_SYMBOL:
					case WP_MySQL_Lexer::DISTINCT_SYMBOL:
						return new SQLiteExpression(
							array(
								SQLiteTokenFactory::raw( $token->text ),
							)
						);
					case WP_MySQL_Lexer::SQL_CALC_FOUND_ROWS_SYMBOL:
						$this->has_sql_calc_found_rows = true;
						// Fall through to default.
					default:
						// we'll need to run the current SQL query without any
						// LIMIT clause, and then substitute the FOUND_ROWS()
						// function with a literal number of rows found.
						return new SQLiteExpression( array() );
				}
				// Otherwise, fall through.

			case 'fromClause':
				// Skip `FROM DUAL`. We only care about a singular
				// FROM DUAL statement, as FROM mytable, DUAL is a syntax
				// error.
				if (
					$parse_tree->has_token( WP_MySQL_Lexer::DUAL_SYMBOL ) &&
					! $parse_tree->has_child( 'tableReferenceList' )
				) {
					return null;
				}
				// Otherwise, fall through.

			case 'selectOption':
			case 'interval':
			case 'intervalTimeStamp':
			case 'bitExpr':
			case 'boolPri':
			case 'lockStrengh':
			case 'orderList':
			case 'simpleExpr':
			case 'columnRef':
			case 'exprIs':
			case 'exprAnd':
			case 'primaryExprCompare':
			case 'fieldIdentifier':
			case 'dotIdentifier':
			case 'identifier':
			case 'literal':
			case 'joinedTable':
			case 'nullLiteral':
			case 'boolLiteral':
			case 'numLiteral':
			case 'textLiteral':
			case 'predicate':
			case 'predicateExprBetween':
			case 'primaryExprPredicate':
			case 'pureIdentifier':
			case 'unambiguousIdentifier':
			case 'qualifiedIdentifier':
			case 'query':
			case 'queryExpression':
			case 'queryExpressionBody':
			case 'queryExpressionParens':
			case 'queryPrimary':
			case 'querySpecification':
			case 'selectAlias':
			case 'selectItem':
			case 'selectItemList':
			case 'selectStatement':
			case 'simpleExprColumnRef':
			case 'simpleExprFunction':
			case 'outerJoinType':
			case 'simpleExprSubQuery':
			case 'simpleExprLiteral':
			case 'compOp':
			case 'simpleExprList':
			case 'simpleStatement':
			case 'subquery':
			case 'exprList':
			case 'expr':
			case 'tableReferenceList':
			case 'tableReference':
			case 'tableRef':
			case 'tableAlias':
			case 'tableFactor':
			case 'singleTable':
			case 'udfExprList':
			case 'udfExpr':
			case 'withClause':
			case 'whereClause':
			case 'commonTableExpression':
			case 'derivedTable':
			case 'columnRefOrLiteral':
			case 'orderClause':
			case 'groupByClause':
			case 'lockingClauseList':
			case 'lockingClause':
			case 'havingClause':
			case 'direction':
			case 'orderExpression':
				$child_expressions = array();
				foreach ( $parse_tree->children as $child ) {
					$child_expressions[] = $this->translate_query( $child );
				}
				return new SQLiteExpression( $child_expressions );

			case 'textStringLiteral':
				return new SQLiteExpression(
					array(
						$parse_tree->has_token( WP_MySQL_Lexer::DOUBLE_QUOTED_TEXT ) ?
						SQLiteTokenFactory::double_quoted_value( $parse_tree->get_token( WP_MySQL_Lexer::DOUBLE_QUOTED_TEXT )->text ) : false,
						$parse_tree->has_token( WP_MySQL_Lexer::SINGLE_QUOTED_TEXT ) ?
						SQLiteTokenFactory::raw( $parse_tree->get_token( WP_MySQL_Lexer::SINGLE_QUOTED_TEXT )->text ) : false,
					)
				);

			case 'functionCall':
				return $this->translate_function_call( $parse_tree );

			case 'runtimeFunctionCall':
				return $this->translate_runtime_function_call( $parse_tree );

			default:
				// var_dump(count($ast->children));
				// foreach($ast->children as $child) {
				//     var_dump(get_class($child));
				//     echo $child->getText();
				//     echo "\n\n";
				// }
				return new SQLiteExpression(
					array(
						SQLiteTokenFactory::raw(
							$rule_name
						),
					)
				);
		}
	}

	private function translate_runtime_function_call( $parse_tree ): SQLiteExpression {
		$name_token = $parse_tree->children[0];

		switch ( strtoupper( $name_token->text ) ) {
			case 'ADDDATE':
			case 'DATE_ADD':
				$args     = $parse_tree->get_children( 'expr' );
				$interval = $parse_tree->get_child( 'interval' );
				$timespan = $interval->get_child( 'intervalTimeStamp' )->get_token()->text;
				return SQLiteTokenFactory::create_function(
					'DATETIME',
					array(
						$this->translate_query( $args[0] ),
						new SQLiteExpression(
							array(
								SQLiteTokenFactory::value( '+' ),
								SQLiteTokenFactory::raw( '||' ),
								$this->translate_query( $args[1] ),
								SQLiteTokenFactory::raw( '||' ),
								SQLiteTokenFactory::value( $timespan ),
							)
						),
					)
				);

			case 'DATE_SUB':
				// return new Expression([
				//     SQLiteTokenFactory::raw("DATETIME("),
				//     $args[0],
				//     SQLiteTokenFactory::raw(", '-'"),
				//     $args[1],
				//     SQLiteTokenFactory::raw(" days')")
				// ]);

			case 'VALUES':
				$column = $parse_tree->get_child()->get_descendant( 'pureIdentifier' );
				if ( ! $column ) {
					throw new Exception( 'VALUES() calls without explicit column names are unsupported' );
				}

				$colname = $column->get_token()->extractValue();
				return new SQLiteExpression(
					array(
						SQLiteTokenFactory::raw( 'excluded.' ),
						SQLiteTokenFactory::identifier( $colname ),
					)
				);
			default:
				throw new Exception( 'Unsupported function: ' . $name_token->text );
		}
	}

	private function translate_function_call( $function_call_tree ): SQLiteExpression {
		$name = $function_call_tree->get_child( 'pureIdentifier' )->get_token()->text;
		$args = array();
		foreach ( $function_call_tree->get_child( 'udfExprList' )->get_children() as $node ) {
			$args[] = $this->translate_query( $node );
		}
		switch ( strtoupper( $name ) ) {
			case 'ABS':
			case 'ACOS':
			case 'ASIN':
			case 'ATAN':
			case 'ATAN2':
			case 'COS':
			case 'DEGREES':
			case 'TRIM':
			case 'EXP':
			case 'MAX':
			case 'MIN':
			case 'FLOOR':
			case 'RADIANS':
			case 'ROUND':
			case 'SIN':
			case 'SQRT':
			case 'TAN':
			case 'TRUNCATE':
			case 'RANDOM':
			case 'PI':
			case 'LTRIM':
			case 'RTRIM':
				return SQLiteTokenFactory::create_function( $name, $args );

			case 'CEIL':
			case 'CEILING':
				return SQLiteTokenFactory::create_function( 'CEIL', $args );

			case 'COT':
				return new Expression(
					array(
						SQLiteTokenFactory::raw( '1 / ' ),
						SQLiteTokenFactory::create_function( 'TAN', $args ),
					)
				);

			case 'LN':
			case 'LOG':
			case 'LOG2':
				return SQLiteTokenFactory::create_function( 'LOG', $args );

			case 'LOG10':
				return SQLiteTokenFactory::create_function( 'LOG10', $args );

			// case 'MOD':
			//     return $this->transformBinaryOperation([
			//         'operator' => '%',
			//         'left' => $args[0],
			//         'right' => $args[1]
			//     ]);

			case 'POW':
			case 'POWER':
				return SQLiteTokenFactory::create_function( 'POW', $args );

			// String functions
			case 'ASCII':
				return SQLiteTokenFactory::create_function( 'UNICODE', $args );
			case 'CHAR_LENGTH':
			case 'LENGTH':
				return SQLiteTokenFactory::create_function( 'LENGTH', $args );
			case 'CONCAT':
				$concated = array( SQLiteTokenFactory::raw( '(' ) );
				foreach ( $args as $k => $arg ) {
					$concated[] = $arg;
					if ( $k < count( $args ) - 1 ) {
						$concated[] = SQLiteTokenFactory::raw( '||' );
					}
				}
				$concated[] = SQLiteTokenFactory::raw( ')' );
				return new SQLiteExpression( $concated );
			// case 'CONCAT_WS':
			//     return new Expression([
			//         SQLiteTokenFactory::raw("REPLACE("),
			//         implode(" || ", array_slice($args, 1)),
			//         SQLiteTokenFactory::raw(", '', "),
			//         $args[0],
			//         SQLiteTokenFactory::raw(")")
			//     ]);
			case 'INSTR':
				return SQLiteTokenFactory::create_function( 'INSTR', $args );
			case 'LCASE':
			case 'LOWER':
				return SQLiteTokenFactory::create_function( 'LOWER', $args );
			case 'LEFT':
				return SQLiteTokenFactory::create_function(
					'SUBSTR',
					array(
						$args[0],
						'1',
						$args[1],
					)
				);
			case 'LOCATE':
				return SQLiteTokenFactory::create_function(
					'INSTR',
					array(
						$args[1],
						$args[0],
					)
				);
			case 'REPEAT':
				return new Expression(
					array(
						SQLiteTokenFactory::raw( "REPLACE(CHAR(32), ' ', " ),
						$args[0],
						SQLiteTokenFactory::raw( ')' ),
					)
				);

			case 'REPLACE':
				return new Expression(
					array(
						SQLiteTokenFactory::raw( 'REPLACE(' ),
						implode( ', ', $args ),
						SQLiteTokenFactory::raw( ')' ),
					)
				);
			case 'RIGHT':
				return new Expression(
					array(
						SQLiteTokenFactory::raw( 'SUBSTR(' ),
						$args[0],
						SQLiteTokenFactory::raw( ', -(' ),
						$args[1],
						SQLiteTokenFactory::raw( '))' ),
					)
				);
			case 'SPACE':
				return new Expression(
					array(
						SQLiteTokenFactory::raw( "REPLACE(CHAR(32), ' ', '')" ),
					)
				);
			case 'SUBSTRING':
			case 'SUBSTR':
				return SQLiteTokenFactory::create_function( 'SUBSTR', $args );
			case 'UCASE':
			case 'UPPER':
				return SQLiteTokenFactory::create_function( 'UPPER', $args );

			case 'DATE_FORMAT':
				$mysql_date_format_to_sqlite_strftime = array(
					'%a' => '%D',
					'%b' => '%M',
					'%c' => '%n',
					'%D' => '%jS',
					'%d' => '%d',
					'%e' => '%j',
					'%H' => '%H',
					'%h' => '%h',
					'%I' => '%h',
					'%i' => '%M',
					'%j' => '%z',
					'%k' => '%G',
					'%l' => '%g',
					'%M' => '%F',
					'%m' => '%m',
					'%p' => '%A',
					'%r' => '%h:%i:%s %A',
					'%S' => '%s',
					'%s' => '%s',
					'%T' => '%H:%i:%s',
					'%U' => '%W',
					'%u' => '%W',
					'%V' => '%W',
					'%v' => '%W',
					'%W' => '%l',
					'%w' => '%w',
					'%X' => '%Y',
					'%x' => '%o',
					'%Y' => '%Y',
					'%y' => '%y',
				);
				// @TODO: Implement as user defined function to avoid
				//        rewriting something that may be an expression as a string
				$format     = $args[1]->elements[0]->value;
				$new_format = strtr( $format, $mysql_date_format_to_sqlite_strftime );

				return SQLiteTokenFactory::create_function(
					'STRFTIME',
					array(
						new Expression( array( SQLiteTokenFactory::raw( $new_format ) ) ),
						new Expression( array( $args[0] ) ),
					)
				);
			case 'DATEDIFF':
				return new Expression(
					array(
						SQLiteTokenFactory::create_function( 'JULIANDAY', array( $args[0] ) ),
						SQLiteTokenFactory::raw( ' - ' ),
						SQLiteTokenFactory::create_function( 'JULIANDAY', array( $args[1] ) ),
					)
				);
			case 'DAYNAME':
				return SQLiteTokenFactory::create_function(
					'STRFTIME',
					array( '%w', ...$args )
				);
			case 'DAY':
			case 'DAYOFMONTH':
				return new Expression(
					array(
						SQLiteTokenFactory::raw( "CAST('" ),
						SQLiteTokenFactory::create_function( 'STRFTIME', array( '%d', ...$args ) ),
						SQLiteTokenFactory::raw( ") AS INTEGER'" ),
					)
				);
			case 'DAYOFWEEK':
				return new Expression(
					array(
						SQLiteTokenFactory::raw( "CAST('" ),
						SQLiteTokenFactory::create_function( 'STRFTIME', array( '%w', ...$args ) ),
						SQLiteTokenFactory::raw( ") + 1 AS INTEGER'" ),
					)
				);
			case 'DAYOFYEAR':
				return new Expression(
					array(
						SQLiteTokenFactory::raw( "CAST('" ),
						SQLiteTokenFactory::create_function( 'STRFTIME', array( '%j', ...$args ) ),
						SQLiteTokenFactory::raw( ") AS INTEGER'" ),
					)
				);
			case 'HOUR':
				return new Expression(
					array(
						SQLiteTokenFactory::raw( "CAST('" ),
						SQLiteTokenFactory::create_function( 'STRFTIME', array( '%H', ...$args ) ),
						SQLiteTokenFactory::raw( ") AS INTEGER'" ),
					)
				);
			case 'MINUTE':
				return new Expression(
					array(
						SQLiteTokenFactory::raw( "CAST('" ),
						SQLiteTokenFactory::create_function( 'STRFTIME', array( '%M', ...$args ) ),
						SQLiteTokenFactory::raw( ") AS INTEGER'" ),
					)
				);
			case 'MONTH':
				return new Expression(
					array(
						SQLiteTokenFactory::raw( "CAST('" ),
						SQLiteTokenFactory::create_function( 'STRFTIME', array( '%m', ...$args ) ),
						SQLiteTokenFactory::raw( ") AS INTEGER'" ),
					)
				);
			case 'MONTHNAME':
				return SQLiteTokenFactory::create_function( 'STRFTIME', array( '%m', ...$args ) );
			case 'NOW':
				return new Expression(
					array(
						SQLiteTokenFactory::raw( 'CURRENT_TIMESTAMP()' ),
					)
				);
			case 'SECOND':
				return new Expression(
					array(
						SQLiteTokenFactory::raw( "CAST('" ),
						SQLiteTokenFactory::create_function( 'STRFTIME', array( '%S', ...$args ) ),
						SQLiteTokenFactory::raw( ") AS INTEGER'" ),
					)
				);
			case 'TIMESTAMP':
				return new Expression(
					array(
						SQLiteTokenFactory::raw( 'DATETIME(' ),
						...$args,
						SQLiteTokenFactory::raw( ')' ),
					)
				);
			case 'YEAR':
				return new Expression(
					array(
						SQLiteTokenFactory::raw( "CAST('" ),
						SQLiteTokenFactory::create_function( 'STRFTIME', array( '%Y', ...$args ) ),
						SQLiteTokenFactory::raw( ") AS INTEGER'" ),
					)
				);
			case 'FOUND_ROWS':
				$this->has_found_rows_call = true;
				return new Expression(
					array(
						// Post-processed in handleSqlCalcFoundRows()
						SQLiteTokenFactory::raw( 'FOUND_ROWS' ),
					)
				);
			default:
				throw new Exception( 'Unsupported function: ' . $name );
		}
	}
}
