import {getLocale} from "./getLocale";

/**
 * Get number formatted for user's current locale.
 *
 * @param {number} num
 *
 * @return {string}
 */
export function numToString( num ) {
	return Intl.NumberFormat( getLocale() ).format( num );
}

/**
 * Get number formatted with short representation for user's current locale.
 *
 * @param {number} num
 *
 * @return {string}
 */
export function numToShortString( num ) {
	return Intl.NumberFormat( getLocale(), { notation: "compact" } ).format( num );
}
