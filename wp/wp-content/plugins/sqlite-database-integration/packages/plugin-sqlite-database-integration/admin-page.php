<?php
/**
 * Functions for the admin page of the plugin.
 *
 * @since 1.0.0
 * @package wp-sqlite-integration
 */

/**
 * Add an admin menu page.
 *
 * @since 1.0.0
 */
function sqlite_add_admin_menu() {
	add_options_page(
		__( 'SQLite integration', 'sqlite-database-integration' ),
		__( 'SQLite integration', 'sqlite-database-integration' ),
		'manage_options',
		'sqlite-integration',
		'sqlite_integration_admin_screen'
	);
}
add_action( 'admin_menu', 'sqlite_add_admin_menu' );

/**
 * The admin page contents.
 */
function sqlite_integration_admin_screen() {
	$db_dropin_path = WP_CONTENT_DIR . '/db.php';

	/*
	 * When an existing "db.php" drop-in is detected, let's check if it's a known
	 * plugin that we can continue supporting even when we override the drop-in.
	 */
	$override_db_dropin = false;
	if ( file_exists( $db_dropin_path ) && ! defined( 'SQLITE_DB_DROPIN_VERSION' ) ) {
		// Check for the Query Monitor plugin.
		// When "QM_DB" exists, it must have been loaded via the "db.php" file.
		if ( class_exists( 'QM_DB', false ) ) {
			$override_db_dropin = true;
		}
	}

	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'SQLite integration.', 'sqlite-database-integration' ); ?></h1>
	</div>
	<!-- Set the wrapper width to 50em, to improve readability. -->
	<div style="max-width:50em;">
		<?php if ( defined( 'SQLITE_DB_DROPIN_VERSION' ) ) : ?>
			<div class="notice notice-success">
				<p><?php esc_html_e( 'SQLite is enabled.', 'sqlite-database-integration' ); ?></p>
			</div>
			<p>
				<?php
					printf(
						/* translators: 1: Admin URL to deactivate the module, 2: db.php drop-in path, */
						__( 'The SQLite drop-in is enabled. To disable it and get back to your previous, MySQL database, you can <a href="%1$s">deactivate the plugin</a>. Alternatively, you can manually delete the %2$s file from your server.', 'sqlite-database-integration' ),
						esc_url( admin_url( 'plugins.php' ) ),
						'<code>' . esc_html( basename( WP_CONTENT_DIR ) ) . '/db.php</code>'
					);
				?>
			</p>
		<?php elseif ( ! extension_loaded( 'pdo_sqlite' ) ) : ?>
			<div class="notice notice-error">
				<p><?php esc_html_e( 'We detected that the PDO SQLite driver is missing from your server (the pdo_sqlite extension is not loaded). Please make sure that SQLite is enabled in your PHP installation before proceeding.', 'sqlite-database-integration' ); ?></p>
			</div>
		<?php elseif ( file_exists( $db_dropin_path ) && ! defined( 'SQLITE_DB_DROPIN_VERSION' ) && ! $override_db_dropin ) : ?>
			<?php if ( defined( 'PERFLAB_SQLITE_DB_DROPIN_VERSION' ) ) : ?>
				<div class="notice notice-warning">
					<p>
						<?php
						printf(
							/* translators: %s: db.php drop-in path */
							esc_html__( 'An older %s file was detected. Please click the button below to update the file.', 'sqlite-database-integration' ),
							'<code>' . esc_html( basename( WP_CONTENT_DIR ) ) . '/db.php</code>'
						);
						?>
					</p>
				</div>
				<a class="button button-primary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=sqlite-integration&confirm-install&upgrade-from-pl' ), 'sqlite-install' ) ); ?>">
					<?php
					printf(
						/* translators: %s: db.php drop-in path */
						esc_html__( 'Update %s file', 'sqlite-database-integration' ),
						'<code>' . esc_html( basename( WP_CONTENT_DIR ) ) . '/db.php</code>'
					);
					?>
				</a>
			<?php else : ?>
				<div class="notice notice-error">
					<p>
						<?php
						printf(
							/* translators: %s: db.php drop-in path */
							esc_html__( 'The SQLite plugin cannot be activated because a different %s drop-in already exists.', 'sqlite-database-integration' ),
							'<code>' . esc_html( basename( WP_CONTENT_DIR ) ) . '/db.php</code>'
						);
						?>
					</p>
				</div>
			<?php endif; ?>
		<?php elseif ( ! is_writable( WP_CONTENT_DIR ) ) : ?>
			<div class="notice notice-error">
				<p>
					<?php
					printf(
						/* translators: %s: db.php drop-in path */
						esc_html__( 'The SQLite plugin cannot be activated because the %s directory is not writable.', 'sqlite-database-integration' ),
						'<code>' . esc_html( basename( WP_CONTENT_DIR ) ) . '</code>'
					);
					?>
				</p>
			</div>
		<?php else : ?>
			<div class="notice notice-success">
				<p><?php esc_html_e( 'All checks completed successfully, your site can use an SQLite database. You can proceed with the installation.', 'sqlite-database-integration' ); ?></p>
			</div>
			<h2><?php esc_html_e( 'Important note', 'sqlite-database-integration' ); ?></h2>
			<p><?php esc_html_e( 'This plugin will switch to a separate database and install WordPress in it. You will need to reconfigure your site, and start with a fresh site. Disabling the plugin you will get back to your previous MySQL database, with all your previous data intact.', 'sqlite-database-integration' ); ?></p>

			<?php if ( $override_db_dropin ) : ?>
				<p>
					<strong>NOTE:</strong>
					<?php
					printf(
						/* translators: %s: db.php drop-in path */
						esc_html__( 'We’ve detected an existing database drop-in file at %s, created by the Query Monitor plugin.', 'sqlite-database-integration' ),
						'<code>' . esc_html( basename( WP_CONTENT_DIR ) ) . '/db.php</code>'
					);
					?>
					<?php esc_html_e( 'To enable SQLite support, this file will need to be replaced.', 'sqlite-database-integration' ); ?>
					<?php esc_html_e( 'The Query Monitor plugin will continue to function correctly after the change. You can safely proceed with the installation.', 'sqlite-database-integration' ); ?>
				</p>
			<?php endif; ?>

			<p><?php esc_html_e( 'By clicking the button below, you will be redirected to the WordPress installation screen to setup your new database', 'sqlite-database-integration' ); ?></p>

			<a class="button button-primary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=sqlite-integration&confirm-install' ), 'sqlite-install' ) ); ?>"><?php esc_html_e( 'Install SQLite database', 'sqlite-database-integration' ); ?></a>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * Adds a link to the admin bar.
 *
 * @since n.e.x.t
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 *
 * @param WP_Admin_Bar $admin_bar The admin bar object.
 */
function sqlite_plugin_adminbar_item( $admin_bar ) {
	global $wpdb;

	if ( defined( 'SQLITE_DB_DROPIN_VERSION' ) && defined( 'DB_ENGINE' ) && 'sqlite' === DB_ENGINE ) {
		$suffix = defined( 'WP_SQLITE_AST_DRIVER' ) && WP_SQLITE_AST_DRIVER ? ' (AST)' : '';
		$title  = '<span style="color:#46B450;">' . __( 'Database: SQLite', 'sqlite-database-integration' ) . $suffix . '</span>';
	} elseif ( stripos( $wpdb->db_server_info(), 'maria' ) !== false ) {
		$title = '<span style="color:#DC3232;">' . __( 'Database: MariaDB', 'sqlite-database-integration' ) . '</span>';
	} else {
		$title = '<span style="color:#DC3232;">' . __( 'Database: MySQL', 'sqlite-database-integration' ) . '</span>';
	}

	$args = array(
		'id'     => 'sqlite-db-integration',
		'parent' => 'top-secondary',
		'title'  => $title,
		'href'   => esc_url( admin_url( 'options-general.php?page=sqlite-integration' ) ),
		'meta'   => false,
	);
	$admin_bar->add_node( $args );
}
add_action( 'admin_bar_menu', 'sqlite_plugin_adminbar_item', 999 );
