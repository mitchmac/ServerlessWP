export const settingsNotifications = {
	/**
	 * Process local and server settings to return a new Map of inline notifications.
	 *
	 * @param {Map} notifications
	 * @param {Object} settings
	 * @param {Object} current_settings
	 * @param {Object} strings
	 *
	 * @return {Map<string, Map<string, Object>>} keyed by setting name, containing map of notification objects keyed by id.
	 */
	process: ( notifications, settings, current_settings, strings ) => {
		// remove-local-file
		if ( settings.hasOwnProperty( "remove-local-file" ) && settings[ "remove-local-file" ] ) {
			let entries = notifications.has( "remove-local-file" ) ? notifications.get( "remove-local-file" ) : new Map();

			if ( settings.hasOwnProperty( "serve-from-s3" ) && !settings[ "serve-from-s3" ] ) {
				if ( !entries.has( "lost-files-notice" ) ) {
					entries.set( "lost-files-notice", {
						inline: true,
						type: "error",
						heading: strings.lost_files_notice_heading,
						message: strings.lost_files_notice_message
					} );
				}
			} else {
				entries.delete( "lost-files-notice" );
			}

			// Show inline warning about potential compatibility issues
			// when turning on setting for the first time.
			if (
				!entries.has( "remove-local-file-notice" ) &&
				current_settings.hasOwnProperty( "remove-local-file" ) &&
				!current_settings[ "remove-local-file" ]
			) {
				entries.set( "remove-local-file-notice", {
					inline: true,
					type: "warning",
					message: strings.remove_local_file_message
				} );
			}

			notifications.set( "remove-local-file", entries );
		} else {
			notifications.delete( "remove-local-file" );
		}

		return notifications;
	}
};
