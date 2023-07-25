/**
 * Does the current object have different keys or values compared to the previous version?
 *
 * @param {object} previous
 * @param {object} current
 *
 * @returns {boolean}
 */
export function objectsDiffer( [previous, current] ) {
	if ( !previous || !current ) {
		return false;
	}

	// Any difference in keys?
	const prevKeys = Object.keys( previous );
	const currKeys = Object.keys( current );

	if ( prevKeys.length !== currKeys.length ) {
		return true;
	}

	// Symmetrical diff to find extra keys in either object.
	if (
		prevKeys.filter( x => !currKeys.includes( x ) )
			.concat(
				currKeys.filter( x => !prevKeys.includes( x ) )
			)
			.length > 0
	) {
		return true;
	}

	// Any difference in values?
	for ( const key in previous ) {
		if ( JSON.stringify( current[ key ] ) !== JSON.stringify( previous[ key ] ) ) {
			return true;
		}
	}

	return false;
}
