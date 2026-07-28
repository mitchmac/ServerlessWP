<?php

/**
 * Native-mode public parser entry point.
 *
 * Always extends the pure-PHP `WP_Parser` so `$parser instanceof WP_Parser`
 * keeps working for callers regardless of whether the Rust extension is
 * loaded. The actual parsing work is delegated to a composed
 * `WP_MySQL_Native_Parser` instance via the `WP_MySQL_Native_Parser_Impl`
 * trait — see that file for the per-method delegation.
 */
class WP_MySQL_Parser extends WP_Parser {
	use WP_MySQL_Native_Parser_Impl;
}
