import {objectsDiffer} from "./objectsDiffer";

/**
 * Determines whether a page should be refreshed due to changes to settings.
 *
 * @param {boolean} saving
 * @param {object} previousSettings
 * @param {object} currentSettings
 * @param {object} previousDefines
 * @param {object} currentDefines
 *
 * @returns {boolean}
 */
export function needsRefresh( saving, previousSettings, currentSettings, previousDefines, currentDefines ) {
	if ( saving ) {
		return false;
	}

	if ( objectsDiffer( [previousSettings, currentSettings] ) ) {
		return true;
	}

	return objectsDiffer( [previousDefines, currentDefines] );
}
