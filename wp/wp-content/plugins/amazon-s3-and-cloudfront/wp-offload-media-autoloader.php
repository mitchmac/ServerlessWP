<?php

// Check if already defined
if ( ! class_exists( 'WP_Offload_Media_Autoloader' ) ) {

	class WP_Offload_Media_Autoloader {

		/**
		 * @var string
		 */
		protected $abspath;

		/**
		 * @var string
		 */
		protected $prefix;

		/**
		 * @var string
		 */
		protected $vendor = 'DeliciousBrains';

		/**
		 * Autoloader constructor.
		 *
		 * @param string $prefix
		 * @param string $abspath
		 */
		public function __construct( string $prefix, string $abspath ) {
			$this->prefix  = $prefix;
			$this->abspath = $abspath;

			spl_autoload_register( array( $this, 'autoloader' ) );
		}

		/**
		 * Autoloader.
		 *
		 * @param string $source_path
		 */
		public function autoloader( string $source_path ) {
			if ( ! $this->source_belongs_to_plugin( $source_path ) ) {
				return;
			}

			$bare_source_path = $this->get_bare_source_path( $source_path );

			foreach ( array( 'classes', 'interfaces', 'traits' ) as $type ) {
				$path = $this->get_source_directory( $type ) . $bare_source_path;

				if ( file_exists( $path ) ) {
					require_once $path;

					return;
				}
			}
		}

		/**
		 * Does source path belong to plugin.
		 *
		 * @param string $source_path
		 *
		 * @return bool
		 */
		protected function source_belongs_to_plugin( string $source_path ): bool {
			if ( 0 !== strpos( $source_path, $this->vendor . '\\' . $this->prefix . '\\' ) ) {
				return false;
			}

			return true;
		}

		/**
		 * Get un-prefixed source path.
		 *
		 * @param string $source_path
		 *
		 * @return string
		 */
		protected function get_bare_source_path( string $source_path ): string {
			$parts = explode( '\\', strtolower( $source_path ) );
			$parts = array_slice( $parts, 2 );

			$filename = implode( DIRECTORY_SEPARATOR, $parts ) . '.php';

			return str_replace( '_', '-', strtolower( $filename ) );
		}

		/**
		 * Get source directory for type.
		 *
		 * @param string $type
		 *
		 * @return string
		 */
		protected function get_source_directory( string $type ): string {
			return $this->abspath . DIRECTORY_SEPARATOR . $type . DIRECTORY_SEPARATOR;
		}
	}
}
