<?php

namespace DeliciousBrains\WP_Offload_Media\Settings;

use AS3CF_Utils;
use DeliciousBrains\WP_Offload_Media\Settings\Exceptions\Domain_Check_Exception;
use DeliciousBrains\WP_Offload_Media\Settings\Exceptions\HTTP_Response_Exception;
use DeliciousBrains\WP_Offload_Media\Settings\Exceptions\Invalid_Response_Code_Exception;
use DeliciousBrains\WP_Offload_Media\Settings\Exceptions\Invalid_Response_Type_Exception;
use DeliciousBrains\WP_Offload_Media\Settings\Exceptions\Malformed_Query_String_Exception;
use DeliciousBrains\WP_Offload_Media\Settings\Exceptions\Malformed_Response_Exception;
use DeliciousBrains\WP_Offload_Media\Settings\Exceptions\S3_Bucket_Origin_Exception;
use DeliciousBrains\WP_Offload_Media\Settings\Exceptions\Ssl_Connection_Exception;
use DeliciousBrains\WP_Offload_Media\Settings\Exceptions\Unresolveable_Hostname_Exception;
use Exception;
use InvalidArgumentException;
use Requests_Utility_CaseInsensitiveDictionary;
use WP_Error;
use WP_Http;

class Domain_Check {
	/**
	 * @var string domain / hostname
	 */
	protected $domain = '';

	/**
	 * @var bool
	 */
	private $dns_checked;

	/**
	 * @var array Validated domain cache
	 */
	private static $validated = array();

	/**
	 * Domain_Check constructor.
	 *
	 * @param string $domain
	 */
	public function __construct( string $domain ) {
		$this->domain = $domain;
	}

	/**
	 * Get the domain that was checked.
	 *
	 * @return string
	 */
	public function domain(): string {
		return $this->domain;
	}

	/**
	 * Validate the domain looks like a real domain.
	 *
	 * @throws InvalidArgumentException
	 */
	public function validate() {
		if ( ! is_string( $this->domain ) ) {
			throw new InvalidArgumentException(
				sprintf(
					__( 'Domain must be a string, [%s] given.', 'amazon-s3-and-cloudfront' ),
					gettype( $this->domain )
				)
			);
		}

		if ( ! trim( $this->domain ) ) {
			throw new InvalidArgumentException(
				__( 'Domain cannot be blank.', 'amazon-s3-and-cloudfront' )
			);
		}

		if ( preg_match( '/[^a-z0-9-\.]/i', $this->domain ) ) {
			throw new InvalidArgumentException(
				__( 'Domain can only contain letters, numbers, hyphens (-), and periods (.)', 'amazon-s3-and-cloudfront' )
			);
		}

		if ( $this->domain === parse_url( network_home_url(), PHP_URL_HOST ) ) {
			throw new InvalidArgumentException(
				sprintf(
					__( '<code>%s</code> must not be the same as the site domain. Use a subdomain instead.', 'amazon-s3-and-cloudfront' ),
					$this->domain
				)
			);
		}
	}

	/**
	 * Validate the domain looks like a real domain and return a description of
	 * any issue found or an empty string if no issue was found.
	 *
	 * Convenience function for validate() and check_dns_resolution() that doesn't throw exceptions.
	 *
	 * @return string
	 */
	public function get_validation_issue(): string {
		try {
			$this->validate();
			$this->check_dns_resolution();
		} catch ( Exception $e ) {
			return $e->getMessage();
		}

		return '';
	}

	/**
	 * Check if the given domain passes all validation.
	 *
	 * @param string $domain
	 *
	 * @return bool
	 */
	public static function is_valid( string $domain ): bool {
		if ( isset( self::$validated[ $domain ] ) ) {
			return self::$validated[ $domain ];
		}

		$check = new static( $domain );

		try {
			$check->validate();

			self::$validated[ $domain ] = true;
		} catch ( Exception $e ) {
			self::$validated[ $domain ] = false;
		}

		return self::$validated[ $domain ];
	}

	/**
	 * Test that the given URL works and returns a response that looks like
	 * a REST response.
	 *
	 * @param string $url
	 *
	 * @return array
	 *
	 * @throws Domain_Check_Exception
	 */
	public function test_rest_endpoint( string $url ): array {
		$this->validate();
		$this->check_dns_resolution();

		$response = $this->dispatch_request( $url );

		$this->check_response_headers( wp_remote_retrieve_headers( $response ) );
		$this->check_response_code( wp_remote_retrieve_response_code( $response ) );
		$this->check_response_type( wp_remote_retrieve_header( $response, 'content-type' ) );
		$this->check_response_body( wp_remote_retrieve_body( $response ) );

		return $response;
	}

	/**
	 * Rewrite the given URL to use the configured domain.
	 *
	 * @param string $url
	 *
	 * @return string
	 */
	protected function prepare_url( string $url ): string {
		if ( empty( $this->domain() ) ) {
			return $url;
		}

		$pull_hostname = AS3CF_Utils::parse_url( $url, PHP_URL_HOST );

		// Force the given domain in the rewritten URL if hostnames do not match.
		if ( $this->domain !== $pull_hostname ) {
			$url = str_replace( "//$pull_hostname/", "//$this->domain/", $url );
		}

		return $url;
	}

	/**
	 * Check that the domain is resolvable.
	 *
	 * @throws Unresolveable_Hostname_Exception
	 */
	protected function check_dns_resolution() {
		if ( $this->dns_checked ) {
			return;
		}

		if ( ! WP_Http::is_ip_address( $this->domain ) && $this->domain === gethostbyname( $this->domain ) ) {
			throw new Unresolveable_Hostname_Exception(
				sprintf(
					__( '<code>%s</code> did not resolve to an IP address.', 'amazon-s3-and-cloudfront' ),
					$this->domain
				)
			);
		}

		$this->dns_checked = true;
	}

	/**
	 * Convert a WP_Error to the appropriate exception.
	 *
	 * @param WP_Error $error
	 *
	 * @return Domain_Check_Exception
	 */
	protected function get_exception_for_wp_error( WP_Error $error ) {
		if ( preg_match( '/SSL (certificate problem|operation failed)/i', $error->get_error_message() ) ) {
			return new Ssl_Connection_Exception(
				sprintf( __( 'An HTTPS connection error was encountered: <code>%s</code>.', 'amazon-s3-and-cloudfront' ), $error->get_error_message() )
			);
		}

		return new HTTP_Response_Exception(
			sprintf( __( 'An HTTP connection error was encountered: <code>%s</code>.', 'amazon-s3-and-cloudfront' ), $error->get_error_message() )
		);
	}

	/**
	 * Check that the given response code is within the acceptable range.
	 *
	 * @param int $response_code
	 *
	 * @throws Invalid_Response_Code_Exception
	 */
	protected function check_response_code( int $response_code ) {
		if ( $response_code < 200 || $response_code > 399 ) {
			throw new Invalid_Response_Code_Exception(
				sprintf(
					__( 'An error was encountered while testing the domain: <code>Received %d from endpoint</code>.', 'amazon-s3-and-cloudfront' ),
					$response_code
				)
			);
		}
	}

	/**
	 * Check that the response type is the correct type.
	 *
	 * @param array|string $content_type
	 *
	 * @throws Invalid_Response_Type_Exception
	 */
	protected function check_response_type( $content_type ) {
		if ( is_array( $content_type ) || ! preg_match( '/^application\/json/i', $content_type ) ) {
			throw new Invalid_Response_Type_Exception(
				sprintf(
					__( 'An error was encountered while testing the domain: <code>Invalid response type: %s</code>.', 'amazon-s3-and-cloudfront' ),
					join( ', ', (array) $content_type )
				)
			);
		}
	}

	/**
	 * Send a request to the given URL.
	 *
	 * @param string $url
	 *
	 * @return array Response data
	 * @throws Domain_Check_Exception
	 */
	protected function dispatch_request( string $url ): array {
		$request_url = $this->prepare_url( $url );
		$response    = wp_remote_get( $request_url, array(
			/**
			 * CloudFront origin timeout is configurable in Origin settings.
			 *
			 * @param int $seconds
			 */
			'timeout'   => apply_filters( 'as3cf_assets_pull_test_endpoint_timeout', 15 ),

			/**
			 * Verify SSL certificates by default.
			 *
			 * @param bool   $verify
			 * @param string $domain
			 */
			'sslverify' => apply_filters( 'as3cf_assets_pull_test_endpoint_sslverify', true, $this->domain ),

			// Make sure WordPress knows this is a REST-API request.
			'headers'   => array( 'content-type' => 'application/json' ),
		) );

		if ( is_wp_error( $response ) ) {
			throw $this->get_exception_for_wp_error( $response );
		}

		return $response;
	}

	/**
	 * Make assertions about the pull request based on the response body.
	 *
	 * @param string $response_body
	 *
	 * @throws Malformed_Query_String_Exception
	 * @throws Malformed_Response_Exception
	 */
	protected function check_response_body( string $response_body ) {
		$raw_body = json_decode( $response_body, true );

		if ( null === $raw_body ) {
			throw new Malformed_Response_Exception(
				__( 'An error was encountered while testing the domain: <code>Malformed response from endpoint</code>.', 'amazon-s3-and-cloudfront' )
			);
		}

		if ( empty( $raw_body['ver'] ) ) {
			throw new Malformed_Query_String_Exception(
				__( 'An error was encountered while testing the domain: <code>Query string missing "ver" parameter</code>.', 'amazon-s3-and-cloudfront' )
			);
		}
	}

	/**
	 * Checks response headers for possible errors.
	 *
	 * @param array|Requests_Utility_CaseInsensitiveDictionary $response_headers
	 *
	 * @throws S3_Bucket_Origin_Exception
	 */
	public static function check_response_headers( $response_headers ) {
		if ( ! empty( $response_headers['server'] ) && 'AmazonS3' === $response_headers['server'] ) {
			throw new S3_Bucket_Origin_Exception(
				__( 'An error was encountered while testing the domain: <code>S3 bucket set as CDN origin</code>.', 'amazon-s3-and-cloudfront' )
			);
		}
	}
}
