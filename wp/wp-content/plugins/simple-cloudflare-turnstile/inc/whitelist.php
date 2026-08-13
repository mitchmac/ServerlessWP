<?php
if (!defined('ABSPATH')) {
	exit;
}

/**
* Check if whitelisted
*/
function cfturnstile_whitelisted() {
    // If admin page return false
    if(isset($_GET['page']) && $_GET['page'] == 'cfturnstile') {
        return false;
    }
    // Filter
    $whitelisted = apply_filters('cfturnstile_whitelisted', false);
    // Logged In Users
    if(get_option('cfturnstile_whitelist_users')) {
        if (is_user_logged_in()) {
            $whitelisted = true;
        } elseif (
            function_exists('wp_validate_auth_cookie')
            && !empty($_COOKIE[LOGGED_IN_COOKIE])
            && wp_validate_auth_cookie($_COOKIE[LOGGED_IN_COOKIE], 'logged_in')
        ) {
            // REST API requests (e.g. CF7's public /feedback endpoint) drop the
            // current user when no X-WP-Nonce header is sent, even though the
            // visitor has valid login cookies. Verify the signed cookie directly.
            $whitelisted = true;
        }
    }
    // If the IP address is within the list of IPs in get_option('cfturnstile_whitelist_ips')
    if(get_option('cfturnstile_whitelist_ips')) {
        $whitelist = get_option('cfturnstile_whitelist_ips');
        $whitelist_ips = explode("\n", str_replace("\r", "", $whitelist));
        $current_ip = cfturnstile_get_ip();
        if ($current_ip) {
            foreach ($whitelist_ips as $whitelist_ip) {
                $whitelist_ip = sanitize_text_field(trim($whitelist_ip));
                // Skip empty lines
                if ($whitelist_ip === '') {
                    continue;
                }
                // Match an exact IP (in any textual form) or a CIDR range (IPv4/IPv6)
                if (cfturnstile_ip_matches($current_ip, $whitelist_ip)) {
                    $whitelisted = true;
                    break;
                }
            }
        }
    }
    // If the User Agent is within the list of User Agents in get_option('cfturnstile_whitelist_agents')
    if (get_option( 'cfturnstile_whitelist_agents' )) {
        $whitelist        = get_option( 'cfturnstile_whitelist_agents' );
        $whitelist_agents = explode("\n", str_replace("\r", "", $whitelist));

        $current_agent = '';
        if ( isset( $_SERVER['HTTP_USER_AGENT'] ) ) {
            $current_agent = sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) );
        }

        foreach ( $whitelist_agents as $whitelist_agent ) {
            $whitelist_agent = sanitize_text_field( trim( $whitelist_agent ) );
            // Check if the User Agent contains the whitelist agent
            if ( $current_agent && $whitelist_agent && strpos( $current_agent, $whitelist_agent ) !== false ) {
                $whitelisted = true;
                break;
            }
        }
    }
    return $whitelisted;
}

/**
 * Get IP Address
 */
function cfturnstile_get_ip() {
    // Helper: validate public IP (no private/reserved ranges)
    $is_public_ip = function ( $ip ) {
        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        );
    };

    // 1. Cloudflare header – trusted if site is known to be behind Cloudflare
    if ( ! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) {
        $ip = sanitize_text_field( trim( wp_unslash( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) );
        if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
            return $ip;
        }
    }

    // 2. Common proxy headers
    $proxy_headers = array(
        'HTTP_X_FORWARDED_FOR',
        'HTTP_X_REAL_IP',
        'HTTP_X_CLIENT_IP',
        'HTTP_CLIENT_IP',
        'HTTP_X_CLUSTER_CLIENT_IP',
    );

    foreach ( $proxy_headers as $header ) {
        if ( empty( $_SERVER[ $header ] ) ) {
            continue;
        }

        // Can be a comma-separated list (especially X-Forwarded-For)
        $ips = explode( ',', wp_unslash( $_SERVER[ $header ] ) );
        foreach ( $ips as $ip ) {
            $ip = sanitize_text_field( trim( $ip ) );
            if ( $is_public_ip( $ip ) ) {
                return $ip;
            }
        }

        // If no public IP found, fall back to first valid IP in the header
        foreach ( $ips as $ip ) {
            $ip = sanitize_text_field( trim( $ip ) );
            if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
                return $ip;
            }
        }
    }

    // 3. Fallback to REMOTE_ADDR
    if ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
        $ip = sanitize_text_field( trim( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) );
        if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
            return $ip;
        }
    }

    return false; // return false if no valid IP found
}

/**
 * Check whether a visitor IP matches a whitelist entry.
 *
 * Supports an exact IP (compared in binary form, so differing textual
 * representations of the same IPv6 address still match) or a CIDR range
 * such as 203.0.113.0/24 or 2001:db8::/32. This lets dual-stack, dynamic
 * or rotating-IPv6 visitors be whitelisted by subnet instead of trying to
 * list every individual address.
 *
 * @param string $ip    The visitor IP from cfturnstile_get_ip().
 * @param string $entry A single whitelist entry (exact IP or CIDR range).
 * @return bool
 */
function cfturnstile_ip_matches( $ip, $entry ) {
    if ( '' === $ip || '' === $entry ) {
        return false;
    }

    // CIDR range, e.g. 203.0.113.0/24 or 2001:db8::/32.
    if ( false !== strpos( $entry, '/' ) ) {
        return cfturnstile_ip_in_cidr( $ip, $entry );
    }

    // Both sides must be valid IPs. Comparing the normalized packed (binary) form
    // means 2001:DB8::1 and 2001:db8:0:0:0:0:0:1 are treated as the same address,
    // and a visitor reported as ::ffff:203.0.113.5 still matches 203.0.113.5.
    $ip_bin    = cfturnstile_ip_to_binary( $ip );
    $entry_bin = cfturnstile_ip_to_binary( $entry );
    if ( false === $ip_bin || false === $entry_bin ) {
        return false;
    }

    return $ip_bin === $entry_bin;
}

/**
 * Convert an IP address to its packed binary form, normalized.
 *
 * Unwraps IPv4-mapped IPv6 (::ffff:203.0.113.5), which dual-stack servers commonly report in
 * REMOTE_ADDR, so it still matches an IPv4 entry or range. The deprecated IPv4-compatible form
 * (::203.0.113.5) is left alone - it cannot be told apart from addresses such as ::1.
 *
 * @param string $ip An IP address in any textual form.
 * @return string|false Packed binary address, or false if $ip is not a valid IP.
 */
function cfturnstile_ip_to_binary( $ip ) {
    if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
        return false;
    }

    $bin = inet_pton( $ip );
    if ( false === $bin ) {
        return false;
    }

    // ::ffff:0:0/96 is the IPv4-mapped range: 10 zero bytes, then 0xffff, then the IPv4.
    if ( 16 === strlen( $bin ) && "\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\xff\xff" === substr( $bin, 0, 12 ) ) {
        return substr( $bin, 12 );
    }

    return $bin;
}

/**
 * Check whether an IP falls within a CIDR range (IPv4 or IPv6).
 *
 * @param string $ip   The visitor IP.
 * @param string $cidr A CIDR range, e.g. 203.0.113.0/24 or 2001:db8::/32.
 * @return bool
 */
function cfturnstile_ip_in_cidr( $ip, $cidr ) {
    list( $subnet, $bits ) = array_pad( explode( '/', $cidr, 2 ), 2, null );
    $subnet = trim( (string) $subnet );
    $bits   = trim( (string) $bits );

    // A missing or non-numeric prefix length is not a valid CIDR entry.
    if ( ! ctype_digit( $bits ) ) {
        return false;
    }

    // The IP and subnet base must both be valid IP addresses.
    $ip_bin     = cfturnstile_ip_to_binary( $ip );
    $subnet_bin = cfturnstile_ip_to_binary( $subnet );
    if ( false === $ip_bin || false === $subnet_bin ) {
        return false;
    }

    // Both operands must be the same IP version (same byte length).
    if ( strlen( $ip_bin ) !== strlen( $subnet_bin ) ) {
        return false;
    }

    $bits = (int) $bits;

    // A range written in IPv4-mapped form (::ffff:203.0.113.0/120) counts its prefix
    // from the start of the 128 bit address, so drop the 96 bit mapping prefix now
    // that the subnet has been unwrapped to its 4 byte IPv4 form.
    if ( 4 === strlen( $subnet_bin ) && $bits >= 96 && false !== strpos( $subnet, ':' ) ) {
        $bits -= 96;
    }

    $max_bits = strlen( $ip_bin ) * 8; // 32 for IPv4, 128 for IPv6.
    if ( $bits > $max_bits ) {
        return false;
    }

    // A /0 prefix matches every possible address, which would silently switch
    // Turnstile off for all visitors rather than whitelist anyone in particular.
    // That is never a meaningful whitelist entry, so treat it as invalid.
    if ( $bits < 1 ) {
        return false;
    }

    $whole_bytes = intdiv( $bits, 8 );
    $rem_bits    = $bits % 8;

    // Compare the full masked bytes.
    if ( $whole_bytes > 0 && substr( $ip_bin, 0, $whole_bytes ) !== substr( $subnet_bin, 0, $whole_bytes ) ) {
        return false;
    }

    // Compare the remaining bits of the next byte.
    if ( $rem_bits > 0 ) {
        $mask = chr( ( 0xff << ( 8 - $rem_bits ) ) & 0xff );
        if ( ( substr( $ip_bin, $whole_bytes, 1 ) & $mask ) !== ( substr( $subnet_bin, $whole_bytes, 1 ) & $mask ) ) {
            return false;
        }
    }

    return true;
}
