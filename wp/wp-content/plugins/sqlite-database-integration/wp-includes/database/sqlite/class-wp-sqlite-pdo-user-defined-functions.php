<?php
/**
 * Custom functions for the SQLite implementation.
 */

/**
 * Registers MySQL-compatible functions with PDO SQLite.
 *
 * Each callback implements a MySQL SQL function that SQLite does not provide.
 *
 * @access private
 */
class WP_SQLite_PDO_User_Defined_Functions {

	/**
	 * Register the user-defined SQLite functions on a PDO connection.
	 *
	 * The functions are registered using PDO::sqliteCreateFunction().
	 *
	 * @param PDO|Pdo\Sqlite $pdo The PDO object.
	 */
	public static function register_for( $pdo ): self {
		$instance = new self();
		foreach ( $instance->functions as $f => $t ) {
			$arities = $instance->function_arities[ $f ] ?? array( -1 );
			foreach ( $arities as $arity ) {
				if ( $pdo instanceof Pdo\Sqlite ) {
					$pdo->createFunction( $f, array( $instance, $t ), $arity );
				} else {
					$pdo->sqliteCreateFunction( $f, array( $instance, $t ), $arity );
				}
			}
		}
		return $instance;
	}

	/**
	 * Array to define MySQL function => function defined with PHP.
	 *
	 * Replaced functions must be public.
	 *
	 * @var array
	 */
	private $functions = array(
		'throw'                        => 'throw',
		'month'                        => 'month',
		'monthnum'                     => 'month',
		'year'                         => 'year',
		'day'                          => 'day',
		'hour'                         => 'hour',
		'minute'                       => 'minute',
		'second'                       => 'second',
		'week'                         => 'week',
		'weekday'                      => 'weekday',
		'dayofweek'                    => 'dayofweek',
		'dayofmonth'                   => 'dayofmonth',
		'unix_timestamp'               => 'unix_timestamp',
		'now'                          => 'now',
		'md5'                          => 'md5',
		'curdate'                      => 'curdate',
		'rand'                         => 'rand',
		'from_unixtime'                => 'from_unixtime',
		'localtime'                    => 'now',
		'localtimestamp'               => 'now',
		'isnull'                       => 'isnull',
		'regexp'                       => 'regexp',
		'regexp_like'                  => 'regexp_like',
		'regexp_replace'               => 'regexp_replace',
		'regexp_substr'                => 'regexp_substr',
		'regexp_instr'                 => 'regexp_instr',
		'field'                        => 'field',
		'log'                          => 'log',
		'least'                        => 'least',
		'greatest'                     => 'greatest',
		'get_lock'                     => 'get_lock',
		'release_lock'                 => 'release_lock',
		'ucase'                        => 'ucase',
		'lcase'                        => 'lcase',
		'unhex'                        => 'unhex',
		'from_base64'                  => 'from_base64',
		'to_base64'                    => 'to_base64',
		'inet_ntoa'                    => 'inet_ntoa',
		'inet_aton'                    => 'inet_aton',
		'datediff'                     => 'datediff',
		'locate'                       => 'locate',
		'utc_date'                     => 'utc_date',
		'utc_time'                     => 'utc_time',
		'utc_timestamp'                => 'utc_timestamp',
		'version'                      => 'version',
		'reverse'                      => 'reverse',

		// Internal helper functions.
		'_helper_like_to_glob_pattern' => '_helper_like_to_glob_pattern',
	);

	/**
	 * Exact argument counts for functions with optional arguments.
	 *
	 * Functions absent from this array are registered as variadic.
	 *
	 * @var array
	 */
	private $function_arities = array(
		'regexp'         => array( 2, 3 ),
		'regexp_like'    => array( 2, 3 ),
		'regexp_replace' => array( 3, 4, 5, 6 ),
		'regexp_substr'  => array( 2, 3, 4, 5 ),
		'regexp_instr'   => array( 2, 3, 4, 5, 6 ),
	);

	/** @var string[] Compiled PCRE patterns, keyed by byte mode, "\G" variant, match type, and pattern. */
	private $regexp_cache = array();

	/**
	 * First element of the RAND(N) LCG state (the value the output is derived from).
	 *
	 * @var int|null
	 */
	private $rand_seed1 = null;

	/**
	 * Second element of the RAND(N) LCG state (the paired value used in the recurrence).
	 *
	 * @var int|null
	 */
	private $rand_seed2 = null;

	/**
	 * Last seed value passed to RAND(N) in the current statement.
	 *
	 * Used to detect whether the rand sequence is advancing with the same seed
	 * (e.g. "SELECT RAND(3) FROM t"), or reseeding (starting a new sequence).
	 *
	 * @var int|null
	 */
	private $rand_last_seed = null;

	/**
	 * Clear any per-statement state held by the UDFs.
	 */
	public function flush(): void {
		$this->rand_seed1     = null;
		$this->rand_seed2     = null;
		$this->rand_last_seed = null;
	}

	/**
	 * A helper function to throw an error from SQLite expressions.
	 *
	 * @param string $message The error message.
	 *
	 * @throws Exception The error message.
	 * @return void
	 */
	public function throw( $message ): void {
		throw new Exception( $message );
	}

	/**
	 * Method to return the unix timestamp.
	 *
	 * Used without an argument, it returns PHP time() function (total seconds passed
	 * from '1970-01-01 00:00:00' GMT). Used with the argument, it changes the value
	 * to the timestamp.
	 *
	 * @param string $field Representing the date formatted as '0000-00-00 00:00:00'.
	 *
	 * @return number of unsigned integer
	 */
	public function unix_timestamp( $field = null ) {
		return is_null( $field ) ? time() : strtotime( $field );
	}

	/**
	 * Method to emulate MySQL FROM_UNIXTIME() function.
	 *
	 * @param int    $field The unix timestamp.
	 * @param string $format Indicate the way of formatting(optional).
	 *
	 * @return string
	 */
	public function from_unixtime( $field, $format = null ) {
		// Convert to ISO time.
		$date = gmdate( 'Y-m-d H:i:s', $field );

		return is_null( $format ) ? $date : $this->dateformat( $date, $format );
	}

	/**
	 * Method to emulate MySQL NOW() function.
	 *
	 * @return string representing current time formatted as '0000-00-00 00:00:00'.
	 */
	public function now() {
		return gmdate( 'Y-m-d H:i:s' );
	}

	/**
	 * Method to emulate MySQL CURDATE() function.
	 *
	 * @return string representing current time formatted as '0000-00-00'.
	 */
	public function curdate() {
		return gmdate( 'Y-m-d' );
	}

	/**
	 * Method to emulate MySQL MD5() function.
	 *
	 * @param string $field The string to be hashed.
	 *
	 * @return string of the md5 hash value of the argument.
	 */
	public function md5( $field ) {
		return md5( $field );
	}

	/**
	 * Method to emulate MySQL's seeded RAND(N) function.
	 *
	 * Implements MySQL's deterministic LCG (Linear Congruential Generator),
	 * producing bit-exact output for a given seed.
	 *
	 * Known divergences from MySQL:
	 *
	 *  1. In MySQL, RAND(N) behaves differently depending on whether the seed
	 *     is constant expression or varies per invocation:
	 *      - Constant seed (e.g. "SELECT RAND(3) FROM t"):
	 *        LCG is initialized once per statement and advanced for each row.
	 *      - Non-constant seed (e.g. "SELECT RAND(col) FROM t"):
	 *        LCG is initialized for every row with its seed value.
	 *
	 *     A SQLite UDF cannot tell whether the seed expression is constant, so
	 *     we just compare the seed against its last value. This diverges from
	 *     MySQL in rare cases, and we can consider improving it in the future.
	 *
	 *  2. The LCG state is shared across call sites in the same query, so
	 *     "SELECT RAND(1), RAND(1)" yields different results here than in MySQL.
	 *     This is a rare edge case that we can consider improving in the future.
	 *
	 * Unseeded RAND() never reaches this function. The AST driver translates it
	 * directly to a more efficient SQLite-native expression.
	 *
	 * @param int|float|string|null $seed Seed value.
	 *
	 * @return float A value in [0, 1).
	 */
	public function rand( $seed ) {
		// Requires 64-bit PHP. Seed * 0x10000001 can exceed PHP_INT_MAX on 32-bit.
		$max_value = 0x3FFFFFFF;

		if ( null === $seed ) {
			// MySQL treats NULL seed as 0.
			$seed = 0;
		} elseif ( ! is_int( $seed ) ) {
			/*
			 * MySQL rounds float values and numeric strings take the same path.
			 * Reduce the value to a 32-bit range using "fmod" to avoid firing
			 * the "out-of-range float to int" cast deprecation on PHP 8.1+.
			 */
			$seed = (int) fmod( round( (float) $seed, 0, PHP_ROUND_HALF_EVEN ), 0x100000000 );
		}

		// Initialize MySQL's internal 30-bit seeds.
		if ( $seed !== $this->rand_last_seed ) {
			/*
			 * MySQL casts to uint32, and the intermediate results wrap at 32-bit
			 * unsigned boundaries. We emulate this with & 0xFFFFFFFF masks.
			 */
			$seed_u32             = $seed & 0xFFFFFFFF;
			$this->rand_seed1     = ( ( $seed_u32 * 0x10001 + 55555555 ) & 0xFFFFFFFF ) % $max_value;
			$this->rand_seed2     = ( ( $seed_u32 * 0x10000001 ) & 0xFFFFFFFF ) % $max_value;
			$this->rand_last_seed = $seed;
		}

		/*
		 * MySQL's LCG recurrence:
		 *   seed1 = (seed1 * 3 + seed2) % max_value
		 *   seed2 = (seed1 + seed2 + 33) % max_value
		 *
		 * Note that seed1 is updated first and the new value is used for seed2.
		 */
		$this->rand_seed1 = ( $this->rand_seed1 * 3 + $this->rand_seed2 ) % $max_value;
		$this->rand_seed2 = ( $this->rand_seed1 + $this->rand_seed2 + 33 ) % $max_value;

		return (float) $this->rand_seed1 / (float) $max_value;
	}

	/**
	 * Method to emulate MySQL DATEFORMAT() function.
	 *
	 * @param string $date   Formatted as '0000-00-00' or datetime as '0000-00-00 00:00:00'.
	 * @param string $format The string format.
	 *
	 * @return string formatted according to $format
	 */
	public function dateformat( $date, $format ) {
		$mysql_php_date_formats = array(
			'%a' => 'D',
			'%b' => 'M',
			'%c' => 'n',
			'%D' => 'jS',
			'%d' => 'd',
			'%e' => 'j',
			'%H' => 'H',
			'%h' => 'h',
			'%I' => 'h',
			'%i' => 'i',
			'%j' => 'z',
			'%k' => 'G',
			'%l' => 'g',
			'%M' => 'F',
			'%m' => 'm',
			'%p' => 'A',
			'%r' => 'h:i:s A',
			'%S' => 's',
			'%s' => 's',
			'%T' => 'H:i:s',
			'%U' => 'W',
			'%u' => 'W',
			'%V' => 'W',
			'%v' => 'W',
			'%W' => 'l',
			'%w' => 'w',
			'%X' => 'Y',
			'%x' => 'o',
			'%Y' => 'Y',
			'%y' => 'y',
		);

		$time   = strtotime( $date );
		$format = strtr( $format, $mysql_php_date_formats );

		return gmdate( $format, $time );
	}

	/**
	 * Method to extract the month value from the date.
	 *
	 * @param string $field Representing the date formatted as 0000-00-00.
	 *
	 * @return string Representing the number of the month between 1 and 12.
	 */
	public function month( $field ) {
		/*
		 * MySQL returns 0 for MONTH('0000-00-00') and for dates with
		 * zero month parts like '2020-00-15'. PHP's strtotime() can't
		 * parse these, so we extract the month directly from the string.
		 */
		if ( preg_match( '/^\d{4}-(\d{2})/', $field, $matches ) ) {
			return intval( $matches[1] );
		}
		/*
		 * From https://www.php.net/manual/en/datetime.format.php:
		 *
		 * n - Numeric representation of a month, without leading zeros.
		 *     1 through 12
		 */
		return intval( gmdate( 'n', strtotime( $field ) ) );
	}

	/**
	 * Method to extract the year value from the date.
	 *
	 * @param string $field Representing the date formatted as 0000-00-00.
	 *
	 * @return string Representing the number of the year.
	 */
	public function year( $field ) {
		/*
		 * MySQL returns 0 for YEAR('0000-00-00'). PHP's strtotime()
		 * can't parse zero dates, so we extract the year directly.
		 */
		if ( preg_match( '/^(\d{4})-\d{2}/', $field, $matches ) ) {
			return intval( $matches[1] );
		}
		/*
		 * From https://www.php.net/manual/en/datetime.format.php:
		 *
		 * Y - A full numeric representation of a year, 4 digits.
		 */
		return intval( gmdate( 'Y', strtotime( $field ) ) );
	}

	/**
	 * Method to extract the day value from the date.
	 *
	 * @param string $field Representing the date formatted as 0000-00-00.
	 *
	 * @return string Representing the number of the day of the month from 1 and 31.
	 */
	public function day( $field ) {
		/*
		 * MySQL returns 0 for DAY('0000-00-00') and for dates with
		 * zero day parts like '2020-01-00'. PHP's strtotime() can't
		 * parse these, so we extract the day directly from the string.
		 */
		if ( preg_match( '/^\d{4}-\d{2}-(\d{2})/', $field, $matches ) ) {
			return intval( $matches[1] );
		}
		/*
		 * From https://www.php.net/manual/en/datetime.format.php:
		 *
		 * j - Day of the month without leading zeros.
		 *     1 to 31.
		 */
		return intval( gmdate( 'j', strtotime( $field ) ) );
	}

	/**
	 * Method to emulate MySQL SECOND() function.
	 *
	 * @see https://www.php.net/manual/en/datetime.format.php
	 *
	 * @param string $field Representing the time formatted as '00:00:00'.
	 *
	 * @return number Unsigned integer
	 */
	public function second( $field ) {
		/*
		 * From https://www.php.net/manual/en/datetime.format.php:
		 *
		 * s - Seconds, with leading zeros (00 to 59)
		 */
		return intval( gmdate( 's', strtotime( $field ) ) );
	}

	/**
	 * Method to emulate MySQL MINUTE() function.
	 *
	 * @param string $field Representing the time formatted as '00:00:00'.
	 *
	 * @return int
	 */
	public function minute( $field ) {
		/*
		 * From https://www.php.net/manual/en/datetime.format.php:
		 *
		 * i - Minutes with leading zeros.
		 *     00 to 59.
		 */
		return intval( gmdate( 'i', strtotime( $field ) ) );
	}

	/**
	 * Method to emulate MySQL HOUR() function.
	 *
	 * Returns the hour for time, in 24-hour format, from 0 to 23.
	 * Importantly, midnight is 0, not 24.
	 *
	 * @param string $time Representing the time formatted, like '14:08:12'.
	 *
	 * @return int
	 */
	public function hour( $time ) {
		/*
		 * From https://www.php.net/manual/en/datetime.format.php:
		 *
		 * H   24-hour format of an hour with leading zeros.
		 *     00 through 23.
		 */
		return intval( gmdate( 'H', strtotime( $time ) ) );
	}

	/**
	 * Covers MySQL WEEK() function.
	 *
	 * Always assumes $mode = 1.
	 *
	 * @TODO: Support other modes.
	 *
	 * From https://dev.mysql.com/doc/refman/8.0/en/date-and-time-functions.html#function_week:
	 *
	 * > Returns the week number for date. The two-argument form of WEEK()
	 * > enables you to specify whether the week starts on Sunday or Monday
	 * > and whether the return value should be in the range from 0 to 53
	 * > or from 1 to 53. If the mode argument is omitted, the value of the
	 * > default_week_format system variable is used.
	 * >
	 * > The following table describes how the mode argument works:
	 * >
	 * > Mode   First day of week   Range   Week 1 is the first week …
	 * > 0      Sunday              0-53    with a Sunday in this year
	 * > 1      Monday              0-53    with 4 or more days this year
	 * > 2      Sunday              1-53    with a Sunday in this year
	 * > 3      Monday              1-53    with 4 or more days this year
	 * > 4      Sunday              0-53    with 4 or more days this year
	 * > 5      Monday              0-53    with a Monday in this year
	 * > 6      Sunday              1-53    with 4 or more days this year
	 * > 7      Monday              1-53    with a Monday in this year
	 *
	 * @param string $field Representing the date.
	 * @param int    $mode  The mode argument.
	 */
	public function week( $field, $mode ) {
		/*
		 * From https://www.php.net/manual/en/datetime.format.php:
		 *
		 * W - ISO-8601 week number of year, weeks starting on Monday.
		 *     Example: 42 (the 42nd week in the year)
		 *
		 * Week 1 is the first week with a Thursday in it.
		 */
		return intval( gmdate( 'W', strtotime( $field ) ) );
	}

	/**
	 * Simulates WEEKDAY() function in MySQL.
	 *
	 * Returns the day of the week as an integer.
	 * The days of the week are numbered 0 to 6:
	 * * 0 for Monday
	 * * 1 for Tuesday
	 * * 2 for Wednesday
	 * * 3 for Thursday
	 * * 4 for Friday
	 * * 5 for Saturday
	 * * 6 for Sunday
	 *
	 * @param string $field Representing the date.
	 *
	 * @return int
	 */
	public function weekday( $field ) {
		/*
		 * date('N') returns 1 (for Monday) through 7 (for Sunday)
		 * That's one more than MySQL.
		 * Let's subtract one to make it compatible.
		 */
		return intval( gmdate( 'N', strtotime( $field ) ) ) - 1;
	}

	/**
	 * Method to emulate MySQL DAYOFMONTH() function.
	 *
	 * @see https://dev.mysql.com/doc/refman/8.0/en/date-and-time-functions.html#function_dayofmonth
	 *
	 * @param string $field Representing the date.
	 *
	 * @return int Returns the day of the month for date as a number in the range 1 to 31.
	 */
	public function dayofmonth( $field ) {
		return intval( gmdate( 'j', strtotime( $field ) ) );
	}

	/**
	 * Method to emulate MySQL DAYOFWEEK() function.
	 *
	 * > Returns the weekday index for date (1 = Sunday, 2 = Monday, …, 7 = Saturday).
	 * > These index values correspond to the ODBC standard. Returns NULL if date is NULL.
	 *
	 * @param string $field Representing the date.
	 *
	 * @return int Returns the weekday index for date (1 = Sunday, 2 = Monday, …, 7 = Saturday).
	 */
	public function dayofweek( $field ) {
		/**
		 * From https://www.php.net/manual/en/datetime.format.php:
		 *
		 * `w` – Numeric representation of the day of the week
		 *     0 (for Sunday) through 6 (for Saturday)
		 */
		return intval( gmdate( 'w', strtotime( $field ) ) ) + 1;
	}

	/**
	 * Method to emulate MySQL DATE() function.
	 *
	 * @see https://www.php.net/manual/en/datetime.format.php
	 *
	 * @param string $date formatted as unix time.
	 *
	 * @return string formatted as '0000-00-00'.
	 */
	public function date( $date ) {
		return gmdate( 'Y-m-d', strtotime( $date ) );
	}

	/**
	 * Method to emulate MySQL ISNULL() function.
	 *
	 * This function returns true if the argument is null, and true if not.
	 *
	 * @param mixed $field The field to be tested.
	 *
	 * @return boolean
	 */
	public function isnull( $field ) {
		return is_null( $field );
	}

	/**
	 * Method to emulate MySQL REGEXP operator.
	 *
	 * SQLite runs "X REGEXP Y" as "regexp(Y, X)".
	 *
	 * @param string|null $pattern Regular expression to match.
	 * @param string|null $field   Haystack.
	 * @param int         $binary  1 for REGEXP BINARY, which matches bytes
	 *                             case-sensitively; 0 otherwise.
	 *
	 * @throws Exception If the pattern is not a valid regular expression.
	 * @return int|null 1 if matched, 0 if not matched, NULL if an argument is NULL.
	 */
	public function regexp( $pattern, $field, $binary = 0 ) {
		if ( null === $pattern || null === $field ) {
			return null;
		}

		/*
		 * Data that is not valid UTF-8 can only be a binary string in MySQL,
		 * and MySQL matches binary strings byte by byte and case-sensitively.
		 */
		$byte_mode = 1 === $binary || ! $this->regexp_is_utf8( $pattern ) || ! $this->regexp_is_utf8( $field );
		$compiled  = $this->regexp_compile( $pattern, $byte_mode ? 'c' : '', $byte_mode );
		return $this->regexp_match( $compiled, $pattern, $field );
	}

	/**
	 * Method to emulate MySQL REGEXP_LIKE() function.
	 *
	 * @param string|null $expr       The subject string.
	 * @param string|null $pattern    The regex pattern.
	 * @param string|null $match_type Optional. Letters that change how the pattern matches.
	 *                                Without "c" or "i", the match is case-insensitive.
	 *
	 * @throws Exception If the match type has an unknown letter, or the pattern is invalid.
	 * @return int|null 1 on match, 0 on no match, NULL if any argument is NULL.
	 */
	public function regexp_like( $expr, $pattern, $match_type = '' ) {
		$this->regexp_check_match_type( 'regexp_like', $match_type );
		if ( null === $expr || null === $pattern || null === $match_type ) {
			return null;
		}

		// Like the REGEXP operator, match data that is not valid UTF-8 byte by byte,
		// and case-sensitively, unless the match type says otherwise.
		$byte_mode = ! $this->regexp_is_utf8( $pattern ) || ! $this->regexp_is_utf8( $expr );
		$compiled  = $this->regexp_compile( $pattern, $byte_mode ? 'c' . $match_type : $match_type, $byte_mode );
		return $this->regexp_match( $compiled, $pattern, $expr );
	}

	/**
	 * Method to emulate MySQL REGEXP_REPLACE() function.
	 *
	 * The replacement follows ICU: "$N" inserts a capture group ("$0" is the whole
	 * match), "${name}" a named group, and a backslash escapes the next character.
	 *
	 * @param string|null           $expr        The subject string.
	 * @param string|null           $pattern     The regex pattern.
	 * @param string|null           $replacement The replacement template.
	 * @param int|float|string|null $pos         Optional. The 1-based character position to start at.
	 *                                           It may be one past the last character.
	 * @param int|float|string|null $occurrence  Optional. Which match to replace, or 0 for all of
	 *                                           them. A negative value replaces the first match.
	 * @param string|null           $match_type  Optional. Letters that change how the pattern matches.
	 *
	 * @throws Exception If the match type has an unknown letter, the pattern or the
	 *                   replacement is invalid, or pos is out of range.
	 * @return string|null The subject with the matches replaced, or NULL if any argument is NULL.
	 */
	public function regexp_replace( $expr, $pattern, $replacement, $pos = 1, $occurrence = 0, $match_type = '' ) {
		// Like MySQL, check the arguments in this order, so that a NULL argument makes
		// the result NULL only when the checks before it pass.
		$this->regexp_check_match_type( 'regexp_replace', $match_type );
		if ( null === $match_type ) {
			return null;
		}

		// Like REGEXP_LIKE(), match data that is not valid UTF-8 byte by byte.
		$byte_mode  = ! $this->regexp_is_utf8( $pattern ) || ! $this->regexp_is_utf8( $expr );
		$match_type = $byte_mode ? 'c' . $match_type : $match_type;
		if ( null !== $pattern ) {
			$this->regexp_compile( $pattern, $match_type, $byte_mode );
		}
		if ( null === $pos || null === $occurrence ) {
			return null;
		}
		if ( (int) $pos < 1 ) {
			throw new Exception( "Incorrect parameters in the call to native function 'regexp_replace'" );
		}
		if ( null === $expr || null === $pattern || null === $replacement ) {
			return null;
		}

		$compiled   = $this->regexp_compile( $pattern, $match_type, $byte_mode );
		$byte_start = $this->regexp_char_to_byte_offset( $expr, (int) $pos, true, $byte_mode );
		if ( '' === $expr ) {
			return $expr;
		}

		// An occurrence of 0 replaces every match, and a negative one the first match.
		$occurrence = (int) $occurrence;
		if ( $occurrence < 0 ) {
			$occurrence = 1;
		}

		/*
		 * List the capture group keys, by index and name. preg_match_all()
		 * reports every group even when nothing matches, while preg_match()
		 * omits trailing unmatched groups before PHP 7.4.
		 */
		preg_match_all( $compiled, '', $groups );
		$group_keys = array_flip( array_keys( $groups ) );

		// Rebuild the subject while going through the matches. With a positive
		// occurrence, replace only that match.
		$result = substr( $expr, 0, $byte_start );
		$offset = $byte_start;
		$index  = 0;
		foreach ( $this->regexp_matches( $pattern, $match_type, $byte_mode, $expr, $byte_start ) as $match ) {
			if ( ++$index < $occurrence ) {
				continue;
			}
			$result .= substr( $expr, $offset, $match[0][1] - $offset );
			$result .= $this->regexp_expand_replacement( $replacement, $match, $group_keys );
			$offset  = $match[0][1] + strlen( $match[0][0] );
			if ( $occurrence > 0 ) {
				break;
			}
		}
		return $result . substr( $expr, $offset );
	}

	/**
	 * Method to emulate MySQL REGEXP_SUBSTR() function.
	 *
	 * @param string|null           $expr       The subject string.
	 * @param string|null           $pattern    The regex pattern.
	 * @param int|float|string|null $pos        Optional. The 1-based character position to start at.
	 *                                          It may be one past the last character.
	 * @param int|float|string|null $occurrence Optional. Which match to return. A value below 1
	 *                                          returns the first match.
	 * @param string|null           $match_type Optional. Letters that change how the pattern matches.
	 *
	 * @throws Exception If the match type has an unknown letter, the pattern is invalid,
	 *                   or pos is out of range.
	 * @return string|null The match, or NULL if there is no match or any argument is NULL.
	 */
	public function regexp_substr( $expr, $pattern, $pos = 1, $occurrence = 1, $match_type = '' ) {
		// Like MySQL, check the arguments in this order, so that a NULL argument makes
		// the result NULL only when the checks before it pass.
		$this->regexp_check_match_type( 'regexp_substr', $match_type );
		if ( null === $match_type ) {
			return null;
		}

		// Like REGEXP_LIKE(), match data that is not valid UTF-8 byte by byte.
		$byte_mode  = ! $this->regexp_is_utf8( $pattern ) || ! $this->regexp_is_utf8( $expr );
		$match_type = $byte_mode ? 'c' . $match_type : $match_type;
		if ( null !== $pattern ) {
			$this->regexp_compile( $pattern, $match_type, $byte_mode );
		}
		if ( null === $pos || null === $occurrence ) {
			return null;
		}
		if ( (int) $pos < 1 ) {
			throw new Exception( "Incorrect parameters in the call to native function 'regexp_substr'" );
		}
		if ( null === $expr || null === $pattern ) {
			return null;
		}

		$byte_start = $this->regexp_char_to_byte_offset( $expr, (int) $pos, true, $byte_mode );
		$match      = $this->regexp_find_match( $pattern, $match_type, $byte_mode, $expr, $byte_start, (int) $occurrence );
		return null === $match ? null : $match[0][0];
	}

	/**
	 * Method to emulate MySQL REGEXP_INSTR() function.
	 *
	 * Unlike the other functions, it searches the subject from pos on, as if it started
	 * there. So "^" and "\G" match at pos, and lookbehinds don't see the text before it.
	 *
	 * @param string|null           $expr          The subject string.
	 * @param string|null           $pattern       The regex pattern.
	 * @param int|float|string|null $pos           Optional. The 1-based character position to start at.
	 *                                             It isn't checked for an empty subject.
	 * @param int|float|string|null $occurrence    Optional. Which match to find. A value below 1
	 *                                             finds the first match.
	 * @param int|float|string|null $return_option Optional. 0 for the position of the match, or 1
	 *                                             for the position after it.
	 * @param string|null           $match_type    Optional. Letters that change how the pattern matches.
	 *
	 * @throws Exception If return_option is not 0 or 1, the match type has an unknown
	 *                   letter, the pattern is invalid, or pos is out of range.
	 * @return int|null The 1-based character position, 0 if there is no match, or NULL if
	 *                  any argument is NULL.
	 */
	public function regexp_instr( $expr, $pattern, $pos = 1, $occurrence = 1, $return_option = 0, $match_type = '' ) {
		// Like MySQL, check the arguments in this order, so that a NULL argument makes
		// the result NULL only when the checks before it pass.
		if ( null !== $return_option && 0 !== (int) $return_option && 1 !== (int) $return_option ) {
			throw new Exception( 'Incorrect arguments to regexp_instr: return_option must be 1 or 0.' );
		}
		$this->regexp_check_match_type( 'regexp_instr', $match_type );
		if ( null === $match_type ) {
			return null;
		}

		// Like REGEXP_LIKE(), match data that is not valid UTF-8 byte by byte.
		$byte_mode  = ! $this->regexp_is_utf8( $pattern ) || ! $this->regexp_is_utf8( $expr );
		$match_type = $byte_mode ? 'c' . $match_type : $match_type;
		if ( null !== $pattern ) {
			$this->regexp_compile( $pattern, $match_type, $byte_mode );
		}
		if ( null === $expr || null === $pattern || null === $pos || null === $occurrence || null === $return_option ) {
			return null;
		}

		// MySQL doesn't check pos for an empty subject, and reports its matches at pos.
		if ( '' === $expr ) {
			$match = $this->regexp_find_match( $pattern, $match_type, $byte_mode, '', 0, (int) $occurrence );
			return null === $match ? 0 : (int) $pos;
		}

		$byte_start = $this->regexp_char_to_byte_offset( $expr, (int) $pos, false, $byte_mode );
		$match      = $this->regexp_find_match( $pattern, $match_type, $byte_mode, substr( $expr, $byte_start ), 0, (int) $occurrence );
		if ( null === $match ) {
			return 0;
		}

		$byte_offset = $byte_start + $match[0][1];
		if ( 1 === (int) $return_option ) {
			$byte_offset += strlen( $match[0][0] );
		}
		if ( $byte_mode ) {
			return $byte_offset + 1;
		}

		// Every byte except a UTF-8 continuation byte starts a character.
		return $byte_offset + 1 - preg_match_all( '/[\x80-\xBF]/', substr( $expr, 0, $byte_offset ) );
	}

	/**
	 * Method to emulate MySQL FIELD() function.
	 *
	 * This function gets the list argument and compares the first item to all the others.
	 * If the same value is found, it returns the position of that value. If not, it
	 * returns 0.
	 *
	 * @return int
	 */
	public function field() {
		$num_args = func_num_args();
		if ( $num_args < 2 || is_null( func_get_arg( 0 ) ) ) {
			return 0;
		}
		$arg_list      = func_get_args();
		$search_string = strtolower( array_shift( $arg_list ) );

		for ( $i = 0; $i < $num_args - 1; $i++ ) {
			if ( strtolower( $arg_list[ $i ] ) === $search_string ) {
				return $i + 1;
			}
		}

		return 0;
	}

	/**
	 * Method to emulate MySQL LOG() function.
	 *
	 * Used with one argument, it returns the natural logarithm of X.
	 * <code>
	 * LOG(X)
	 * </code>
	 * Used with two arguments, it returns the natural logarithm of X base B.
	 * <code>
	 * LOG(B, X)
	 * </code>
	 * In this case, it returns the value of log(X) / log(B).
	 *
	 * Used without an argument, it returns false. This returned value will be
	 * rewritten to 0, because SQLite doesn't understand true/false value.
	 *
	 * @return double|null
	 */
	public function log() {
		$num_args = func_num_args();
		if ( 1 === $num_args ) {
			$arg1 = func_get_arg( 0 );

			return log( $arg1 );
		}
		if ( 2 === $num_args ) {
			$arg1 = func_get_arg( 0 );
			$arg2 = func_get_arg( 1 );

			return log( $arg1 ) / log( $arg2 );
		}
		return null;
	}

	/**
	 * Method to emulate MySQL LEAST() function.
	 *
	 * This function rewrites the function name to SQLite compatible function name.
	 *
	 * @return mixed
	 */
	public function least() {
		$arg_list = func_get_args();

		return min( $arg_list );
	}

	/**
	 * Method to emulate MySQL GREATEST() function.
	 *
	 * This function rewrites the function name to SQLite compatible function name.
	 *
	 * @return mixed
	 */
	public function greatest() {
		$arg_list = func_get_args();

		return max( $arg_list );
	}

	/**
	 * Method to dummy out MySQL GET_LOCK() function.
	 *
	 * This function is meaningless in SQLite, so we do nothing.
	 *
	 * @param string  $name    Not used.
	 * @param integer $timeout Not used.
	 *
	 * @return string
	 */
	public function get_lock( $name, $timeout ) {
		return '1=1';
	}

	/**
	 * Method to dummy out MySQL RELEASE_LOCK() function.
	 *
	 * This function is meaningless in SQLite, so we do nothing.
	 *
	 * @param string $name Not used.
	 *
	 * @return string
	 */
	public function release_lock( $name ) {
		return '1=1';
	}

	/**
	 * Method to emulate MySQL UCASE() function.
	 *
	 * This is MySQL alias for upper() function. This function rewrites it
	 * to SQLite compatible name upper().
	 *
	 * @param string $content String to be converted to uppercase.
	 *
	 * @return string SQLite compatible function name.
	 */
	public function ucase( $content ) {
		return "upper($content)";
	}

	/**
	 * Method to emulate MySQL LCASE() function.
	 *
	 * This is MySQL alias for lower() function. This function rewrites it
	 * to SQLite compatible name lower().
	 *
	 * @param string $content String to be converted to lowercase.
	 *
	 * @return string SQLite compatible function name.
	 */
	public function lcase( $content ) {
		return "lower($content)";
	}

	/**
	 * Method to emulate MySQL UNHEX() function.
	 *
	 * For a string argument str, UNHEX(str) interprets each pair of characters
	 * in the argument as a hexadecimal number and converts it to the byte represented
	 * by the number. The return value is a binary string.
	 *
	 * @param string $number Number to be unhexed.
	 *
	 * @return string Binary string
	 */
	public function unhex( $number ) {
		return pack( 'H*', $number );
	}

	/**
	 * Method to emulate MySQL FROM_BASE64() function.
	 *
	 * Takes a base64-encoded string and returns the decoded result as a binary
	 * string. Returns NULL if the argument is NULL or is not a valid base64 string.
	 *
	 * @param string|null $str The base64-encoded string.
	 *
	 * @return string|null Decoded binary string, or NULL.
	 */
	public function from_base64( $str ) {
		if ( null === $str ) {
			return null;
		}
		$decoded = base64_decode( $str, true );
		if ( false === $decoded ) {
			return null;
		}
		return $decoded;
	}

	/**
	 * Method to emulate MySQL TO_BASE64() function.
	 *
	 * Takes a string and returns a base64-encoded result.
	 * Returns NULL if the argument is NULL.
	 *
	 * @param string|null $str The string to encode.
	 *
	 * @return string|null Base64-encoded string, or NULL.
	 */
	public function to_base64( $str ) {
		if ( null === $str ) {
			return null;
		}
		return base64_encode( $str );
	}

	/**
	 * Method to emulate MySQL INET_NTOA() function.
	 *
	 * This function gets 4 or 8 bytes integer and turn it into the network address.
	 *
	 * @param integer $num Long integer.
	 *
	 * @return string
	 */
	public function inet_ntoa( $num ) {
		return long2ip( $num );
	}

	/**
	 * Method to emulate MySQL INET_ATON() function.
	 *
	 * This function gets the network address and turns it into integer.
	 *
	 * @param string $addr Network address.
	 *
	 * @return int long integer
	 */
	public function inet_aton( $addr ) {
		return abs( (int) ip2long( $addr ) );
	}

	/**
	 * Method to emulate MySQL DATEDIFF() function.
	 *
	 * This function compares two dates value and returns the difference.
	 *
	 * @param string $start Start date.
	 * @param string $end   End date.
	 *
	 * @return string
	 */
	public function datediff( $start, $end ) {
		$start_date = new DateTime( $start );
		$end_date   = new DateTime( $end );
		$interval   = $end_date->diff( $start_date, false );

		return $interval->format( '%r%a' );
	}

	/**
	 * Method to emulate MySQL LOCATE() function.
	 *
	 * This function returns the position if $substr is found in $str. If not,
	 * it returns 0. If mbstring extension is loaded, mb_strpos() function is
	 * used.
	 *
	 * @param string  $substr Needle.
	 * @param string  $str    Haystack.
	 * @param integer $pos    Position.
	 *
	 * @return integer
	 */
	public function locate( $substr, $str, $pos = 0 ) {
		if ( ! extension_loaded( 'mbstring' ) ) {
			$val = strpos( $str, $substr, $pos );
			if ( false !== $val ) {
				return $val + 1;
			}
			return 0;
		}
		$val = mb_strpos( $str, $substr, $pos );
		if ( false !== $val ) {
			return $val + 1;
		}
		return 0;
	}

	/**
	 * Method to return GMT date in the string format.
	 *
	 * @return string formatted GMT date 'dddd-mm-dd'
	 */
	public function utc_date() {
		return gmdate( 'Y-m-d', time() );
	}

	/**
	 * Method to return GMT time in the string format.
	 *
	 * @return string formatted GMT time '00:00:00'
	 */
	public function utc_time() {
		return gmdate( 'H:i:s', time() );
	}

	/**
	 * Method to return GMT time stamp in the string format.
	 *
	 * @return string formatted GMT timestamp 'yyyy-mm-dd 00:00:00'
	 */
	public function utc_timestamp() {
		return gmdate( 'Y-m-d H:i:s', time() );
	}

	/**
	 * Method to return MySQL version.
	 *
	 * This function only returns the current newest version number of MySQL,
	 * because it is meaningless for SQLite database.
	 *
	 * @return string representing the version number: major_version.minor_version
	 */
	public function version() {
		return '5.5';
	}

	/**
	 * Method to emulate MySQL REVERSE() function.
	 *
	 * Reverse UTF-8 text by code point, matching MySQL behavior.
	 *
	 * @param string|null $str The string to reverse.
	 *
	 * @return string|null reversed string, or NULL.
	 */
	public function reverse( $str ) {
		if ( null === $str ) {
			return null;
		}
		if (
			preg_match( '/[^\x00-\x7F]/', $str )
			&& preg_match_all( '/./us', $str, $matches )
		) {
			return implode( '', array_reverse( $matches[0] ) );
		}
		return strrev( $str );
	}

	/**
	 * A helper to convert a LIKE pattern to a GLOB pattern for "LIKE BINARY" support.

	 * @TODO: Some of the MySQL string specifics described below are likely to
	 *        affect also other patterns than just "LIKE BINARY". We should
	 *        consider applying some of the conversions more broadly.
	 *
	 * @param string|null $pattern
	 * @return string|null
	 */
	public function _helper_like_to_glob_pattern( $pattern ) {
		if ( null === $pattern ) {
			return null;
		}

		/*
		 * 1. Escape characters that have special meaning in GLOB patterns.
		 *
		 * We need to:
		 *  1. Escape "]" as "[]]" to avoid interpreting "[...]" as a character class.
		 *  2. Escape "*" as "[*]" (must be after 1 to avoid being escaped).
		 *  3. Escape "?" as "[?]" (must be after 1 to avoid being escaped).
		 */
		$pattern = str_replace( ']', '[]]', $pattern );
		$pattern = str_replace( '*', '[*]', $pattern );
		$pattern = str_replace( '?', '[?]', $pattern );

		/*
		 * 2. Convert LIKE wildcards to GLOB wildcards ("%" -> "*", "_" -> "?").
		 *
		 * We need to convert them only when they don't follow any backslashes,
		 * or when they follow an even number of backslashes (as "\\" is "\").
		 */
		$pattern = preg_replace( '/(^|[^\\\\](?:\\\\{2})*)%/', '$1*', $pattern );
		$pattern = preg_replace( '/(^|[^\\\\](?:\\\\{2})*)_/', '$1?', $pattern );

		/*
		 * 3. Unescape LIKE escape sequences.
		 *
		 * While in MySQL LIKE patterns, a backslash is usually used to escape
		 * special characters ("%", "_", and "\"), it works with all characters.
		 *
		 * That is:
		 *   SELECT '\\x' prints '\x', but LIKE '\\x' is equivalent to LIKE 'x'.
		 *
		 * This is true also for multi-byte characters:
		 *   SELECT '\\©' prints '\©', but LIKE '\\©' is equivalent to LIKE '©'.
		 *
		 * Use "s" to include escaped newlines and omit "u" to preserve raw bytes.
		 */
		$pattern = preg_replace( '/\\\\(.)/s', '$1', $pattern );

		return $pattern;
	}

	/**
	 * Check that a match type has only known letters.
	 *
	 * Like MySQL, the functions check it before they return NULL for a NULL argument.
	 *
	 * @param string      $function_name The function name, for the error message.
	 * @param string|null $match_type    MySQL match_type flag string.
	 *
	 * @throws Exception If the match type has an unknown letter.
	 */
	private function regexp_check_match_type( $function_name, $match_type ) {
		$match_type = (string) $match_type;
		if ( strspn( $match_type, 'cimnu' ) < strlen( $match_type ) ) {
			throw new Exception( "Incorrect arguments to $function_name" );
		}
	}

	/**
	 * Compile a MySQL-style regex into a PCRE pattern string.
	 *
	 * Translates MySQL match_type flags (c/i/m/n/u) to PCRE modifiers. The u
	 * (UTF-8) modifier is applied unless byte-oriented matching is requested.
	 * Case-insensitive is the default.
	 *
	 * MySQL uses ICU, which differs from PCRE in some syntax, like ICU-only escapes
	 * and nested sets. The known failures of the REGEXP test suites list these
	 * cases. Other known differences:
	 *
	 * - Text matching ignores case unless the `c` flag is passed, as the column
	 *   collation isn't known here.
	 * - PCRE doesn't match "ß" with "ss" when ignoring case, `$` also matches
	 *   between CR and LF, and `(?P<name>...)` is valid.
	 * - `\G` in a lookbehind doesn't always match where it does in ICU.
	 * - Data that isn't valid UTF-8 is matched byte by byte and case-sensitively,
	 *   like MySQL binary strings. Other binary values match as text, so case
	 *   sensitivity, and whether positions count bytes, can differ between rows.
	 * - SQLite converts numbers to text in its own format, so `20e0` reads "20.0"
	 *   (MySQL: "20"), and `0.000001` reads "1.0e-06".
	 * - `pos`, `occurrence`, and `return_option` use PHP's integer conversion, so
	 *   `2.6` means 2 (MySQL: 3), and `'2e1'` means 20 (MySQL: 2).
	 * - A repeated group, like `(a|b)*`, can exceed PCRE's limits on a subject of
	 *   about 50,000 characters, or 2,000 on PHP 7.2.
	 *
	 * @param string $pattern     The MySQL regex pattern.
	 * @param string $match_type  MySQL match_type flag string.
	 * @param bool   $binary_mode Whether to match bytes instead of UTF-8 characters.
	 * @param bool   $without_g   Whether "\G" never matches, for a search that doesn't
	 *                            start where the previous match ended.
	 *
	 * @throws Exception If the pattern is empty or invalid.
	 * @return string PCRE-ready pattern with delimiter and modifiers.
	 */
	private function regexp_compile( $pattern, $match_type, $binary_mode = false, $without_g = false ) {
		$match_type = (string) $match_type;
		$pattern    = (string) $pattern;
		if ( '' === $pattern ) {
			throw new Exception( 'Illegal argument to a regular expression.' );
		}

		// Without "\G" in the pattern, both versions are the same.
		$without_g = $without_g && false !== strpos( $pattern, '\\G' );
		$cache_key = ( $binary_mode ? 'bytes:' : 'text:' ) . ( $without_g ? 'without-g:' : '' ) . $match_type . ':' . $pattern;
		if ( isset( $this->regexp_cache[ $cache_key ] ) ) {
			return $this->regexp_cache[ $cache_key ];
		}

		$case_sensitive = false;
		$multiline      = false;
		$dotall         = false;
		$unix_lines     = false;
		$len            = strlen( $match_type );
		for ( $i = 0; $i < $len; $i++ ) {
			$flag = $match_type[ $i ];
			if ( 'c' === $flag ) {
				$case_sensitive = true;
			} elseif ( 'i' === $flag ) {
				$case_sensitive = false;
			} elseif ( 'm' === $flag ) {
				$multiline = true;
			} elseif ( 'n' === $flag ) {
				$dotall = true;
			} elseif ( 'u' === $flag ) {
				$unix_lines = true;
			}
		}

		/*
		 * PHP 7 can read an unknown escape as literal text. For "\y", that matches ICU,
		 * but "\u0041" matches "u0041", where ICU matches "A". The "X" modifier makes
		 * all of them errors, like in PHP 8.
		 */
		$modifiers = $binary_mode ? 'X' : 'uX';
		if ( ! $case_sensitive ) {
			$modifiers .= 'i';
		}
		if ( $multiline ) {
			$modifiers .= 'm';
		}
		if ( $dotall ) {
			$modifiers .= 's';
		}

		/*
		 * In binary data, MySQL ends lines at CR, LF, VT and FF. No PCRE setting
		 * matches that. (*ANY) also ends lines at the byte 0x85, which is a part of
		 * characters like "ą" (0xC4 0x85). (*ANYCRLF) misses only the rare VT and FF.
		 */
		if ( $unix_lines ) {
			$newline = '(*LF)';
		} elseif ( $binary_mode ) {
			$newline = '(*ANYCRLF)';
		} else {
			$newline = '(*ANY)';
		}
		$compiled = '/' . $newline . $this->regexp_escape_pattern( $pattern, $without_g ) . '/' . $modifiers;

		/*
		 * Compile the pattern by matching it against an empty string. Only a compile
		 * error emits a PHP warning, so silence it and report a MySQL error instead.
		 * A limit error concerns only the empty string, so the pattern is valid.
		 */
		set_error_handler( static function () {} );
		try {
			$valid = false !== preg_match( $compiled, '' ) || PREG_INTERNAL_ERROR !== preg_last_error();
		} finally {
			restore_error_handler();
		}
		if ( ! $valid ) {
			$this->regexp_fail( $pattern );
		}
		// A query can use many patterns, such as one from a column, so drop the oldest one.
		if ( count( $this->regexp_cache ) >= 64 ) {
			array_shift( $this->regexp_cache );
		}
		$this->regexp_cache[ $cache_key ] = $compiled;
		return $compiled;
	}

	/**
	 * Escape pattern delimiters and NUL bytes that are not already escaped, read
	 * "\K" as "K", and, if asked, make "\G" never match.
	 *
	 * PHP before 8.2 rejects NUL bytes in patterns, so they become "\x00".
	 *
	 * @param string $pattern   The regex pattern.
	 * @param bool   $without_g Optional. Whether "\G" never matches.
	 *
	 * @return string Pattern safe to wrap in slash delimiters.
	 */
	private function regexp_escape_pattern( $pattern, $without_g = false ) {
		$special = array(
			'/'  => '\\/',
			"\0" => '\\x00',
		);

		/*
		 * ICU reads "\K" as "K". In PCRE, "\K" moves the start of the match, even to
		 * before the place where the search started, like in "(?<=\Ka)".
		 */
		$escapes = $special + array( 'K' => 'K' );
		if ( $without_g ) {
			// A position can't be both a word boundary and not one, so "\b\B" never
			// matches. Unlike "(?!)", it has no ")" to end a "(?#...)" comment.
			$escapes['G'] = '\\b\\B';
		}

		$escaped = '';
		$quoted  = false;
		$length  = strlen( $pattern );
		for ( $i = 0; $i < $length; ++$i ) {
			$character = $pattern[ $i ];
			if ( $quoted ) {
				if ( '\\' === $character && $i + 1 < $length && 'E' === $pattern[ $i + 1 ] ) {
					$escaped .= '\\E';
					$quoted   = false;
					++$i;
				} elseif ( isset( $special[ $character ] ) ) {
					$escaped .= '\\E' . $special[ $character ] . '\\Q';
				} else {
					$escaped .= $character;
				}
				continue;
			}
			if ( '\\' === $character && $i + 1 < $length ) {
				$next     = $pattern[ $i + 1 ];
				$escaped .= isset( $escapes[ $next ] ) ? $escapes[ $next ] : $character . $next;
				$quoted   = 'Q' === $next;
				++$i;
			} elseif ( isset( $special[ $character ] ) ) {
				$escaped .= $special[ $character ];
			} else {
				$escaped .= $character;
			}
		}

		// Close quoted text that runs to the end, so that a trailing backslash
		// can't escape the delimiter.
		if ( $quoted ) {
			$escaped .= '\\E';
		}
		return $escaped;
	}

	/**
	 * Check whether a string is valid UTF-8.
	 *
	 * @param string|null $value The string. NULL counts as valid.
	 *
	 * @return bool Whether the string is valid UTF-8.
	 */
	private function regexp_is_utf8( $value ) {
		return null === $value || 1 === preg_match( '//u', $value );
	}

	/**
	 * Convert a 1-based character position to a byte offset.
	 *
	 * @param string $subject        The subject string.
	 * @param int    $position       The 1-based character position.
	 * @param bool   $allow_past_end Whether the position may be one past the last character.
	 * @param bool   $byte_mode      Whether every byte is a character.
	 *
	 * @throws Exception If the position is out of range.
	 * @return int The byte offset.
	 */
	private function regexp_char_to_byte_offset( $subject, $position, $allow_past_end, $byte_mode ) {
		$length = strlen( $subject );
		$chars  = 0;
		for ( $offset = 0; $offset < $length; $offset++ ) {
			// Every byte except a UTF-8 continuation byte starts a character.
			if ( $byte_mode || 0x80 !== ( ord( $subject[ $offset ] ) & 0xC0 ) ) {
				if ( ++$chars === $position ) {
					return $offset;
				}
			}
		}
		if ( $allow_past_end && $chars + 1 === $position ) {
			return $length;
		}
		throw new Exception( 'Index out of bounds in regular expression search.' );
	}

	/**
	 * Expand an ICU replacement template for one match.
	 *
	 * - A backslash escapes the next character, and a trailing one is dropped.
	 *   Unlike ICU, "\uXXXX" and "\UXXXXXXXX" are not expanded.
	 * - "$N" inserts group N. It takes the longest run of digits that is a group
	 *   number, and the other digits stay literal. A larger number is an error.
	 *   Unlike ICU, only the ASCII digits 0-9 are read as digits.
	 * - "${name}" inserts a named group. An unknown name is an error, and so are
	 *   "${N}" and a "$" before anything else.
	 * - A group that didn't match inserts an empty string.
	 *
	 * @param string $replacement The replacement template.
	 * @param array  $match_data  The match, with offsets.
	 * @param array  $group_keys  The pattern's group numbers and names, as array keys.
	 *
	 * @throws Exception On an invalid group reference.
	 * @return string The expanded replacement.
	 */
	private function regexp_expand_replacement( $replacement, $match_data, $group_keys ) {
		/*
		 * Like ICU, accept only letters and the digits 1-9 in a group name. A name
		 * must start with a letter, or "${1}" would match group 1's numeric key.
		 */
		return preg_replace_callback(
			'/\\\\(.?)|\$(?:\{([A-Za-z][A-Za-z1-9]*)\}|(\d+))?/s',
			static function ( $parts ) use ( $match_data, $group_keys ) {
				if ( '\\' === $parts[0][0] ) {
					return $parts[1];
				}
				$name   = $parts[2] ?? '';
				$digits = $parts[3] ?? '';
				if ( '' !== $name && isset( $group_keys[ $name ] ) ) {
					return $match_data[ $name ][0] ?? '';
				}
				if ( '' === $digits ) {
					throw new Exception( 'A capture group has an invalid name.' );
				}
				for ( $length = strlen( $digits ); $length > 0; --$length ) {
					$group = (int) substr( $digits, 0, $length );
					if ( isset( $group_keys[ $group ] ) ) {
						return ( $match_data[ $group ][0] ?? '' ) . substr( $digits, $length );
					}
				}
				throw new Exception( 'Index out of bounds in regular expression search.' );
			},
			$replacement
		);
	}

	/**
	 * Find one match from an offset.
	 *
	 * @param string $pattern    The MySQL regex pattern.
	 * @param string $match_type MySQL match_type flag string.
	 * @param bool   $byte_mode  Whether every byte is a character.
	 * @param string $subject    The subject string.
	 * @param int    $offset     The byte offset to start at.
	 * @param int    $occurrence Which match to find. A value below 1 finds the first match.
	 *
	 * @throws Exception On a preg failure.
	 * @return array|null The match, with offsets, or NULL if there is none.
	 */
	private function regexp_find_match( $pattern, $match_type, $byte_mode, $subject, $offset, $occurrence ) {
		$index = 0;
		foreach ( $this->regexp_matches( $pattern, $match_type, $byte_mode, $subject, $offset ) as $match ) {
			if ( ++$index >= $occurrence ) {
				return $match;
			}
		}
		return null;
	}

	/**
	 * Find the matches from an offset, one at a time.
	 *
	 * The search starts at the offset of the whole subject, so that lookbehinds
	 * see the text before it. Like in ICU, "\G" matches only at the start of the
	 * subject and where the previous match ended.
	 *
	 * @param string $pattern    The MySQL regex pattern.
	 * @param string $match_type MySQL match_type flag string.
	 * @param bool   $byte_mode  Whether every byte is a character.
	 * @param string $subject    The subject string.
	 * @param int    $offset     The byte offset to start at.
	 *
	 * @throws Exception On a preg failure.
	 * @return Generator The matches, with offsets.
	 */
	private function regexp_matches( $pattern, $match_type, $byte_mode, $subject, $offset ) {
		$compiled     = $this->regexp_compile( $pattern, $match_type, $byte_mode );
		$without_g    = $this->regexp_compile( $pattern, $match_type, $byte_mode, true );
		$length       = strlen( $subject );
		$previous_end = 0;
		while ( $offset <= $length ) {
			$search = $offset === $previous_end ? $compiled : $without_g;
			if ( ! $this->regexp_match( $search, $pattern, $subject, $match, PREG_OFFSET_CAPTURE, $offset ) ) {
				return;
			}

			/*
			 * Only PCRE's "\K" can start a match before the search, and then the next
			 * search would find the same match forever. The escaper reads "\K" as "K",
			 * but not after a "\Q" in a comment, which it reads as quoted text.
			 */
			if ( $match[0][1] < $offset ) {
				throw new Exception( 'Invalid regular expression: ' . $pattern . '.' );
			}
			yield $match;

			$offset       = $match[0][1] + strlen( $match[0][0] );
			$previous_end = $offset;
			if ( '' === $match[0][0] ) {
				// Like ICU, continue one character after an empty match, even when the
				// pattern could match a non-empty string at the same position.
				++$offset;
				while ( ! $byte_mode && $offset < $length && 0x80 === ( ord( $subject[ $offset ] ) & 0xC0 ) ) {
					++$offset;
				}
			}
		}
	}

	/**
	 * Run preg_match(), and report a failure as a MySQL error.
	 *
	 * @param string $compiled PCRE-wrapped pattern.
	 * @param string $pattern  The original MySQL regex pattern, for error messages.
	 * @param string $subject  The subject string.
	 * @param array  $matches  Optional. Receives the match, like in preg_match().
	 * @param int    $flags    Optional. The preg_match() flags.
	 * @param int    $offset   Optional. The byte offset to start at.
	 *
	 * @throws Exception On a preg failure.
	 * @return int 1 on match, 0 on no match.
	 */
	private function regexp_match( $compiled, $pattern, $subject, &$matches = null, $flags = 0, $offset = 0 ) {
		$result = preg_match( $compiled, $subject, $matches, $flags, $offset );

		/*
		 * PHP's JIT stack is small, so a repeated group, such as "(a|b)*", can fail on
		 * a subject of a few thousand characters. Matching without JIT goes further.
		 * PHP 7.2 has no "(*NO_JIT)", and matching without JIT can crash it.
		 */
		if ( false === $result && PREG_JIT_STACKLIMIT_ERROR === preg_last_error() && PHP_VERSION_ID >= 70300 ) {
			$result = preg_match( $compiled[0] . '(*NO_JIT)' . substr( $compiled, 1 ), $subject, $matches, $flags, $offset );
		}
		if ( false === $result ) {
			$this->regexp_fail( $pattern );
		}
		return $result;
	}

	/**
	 * Translate a preg_* failure into a caller-friendly exception message.
	 *
	 * Uses preg_last_error() to distinguish invalid patterns from runtime
	 * limit failures.
	 *
	 * @param string $pattern The original MySQL regex pattern.
	 *
	 * @throws Exception Always.
	 * @return void
	 */
	private function regexp_fail( $pattern ) {
		$err = preg_last_error();
		if (
			PREG_BACKTRACK_LIMIT_ERROR === $err
			|| PREG_RECURSION_LIMIT_ERROR === $err
			|| PREG_JIT_STACKLIMIT_ERROR === $err
		) {
			throw new Exception( 'Regular expression evaluation exceeded internal limits.' );
		}
		throw new Exception( 'Invalid regular expression: ' . $pattern . '.' );
	}
}
