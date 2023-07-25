/**
 * Get the user's current locale string.
 *
 * @return {string}
 */
export function getLocale() {
	return (navigator.languages && navigator.languages.length) ? navigator.languages[ 0 ] : navigator.language;
}
