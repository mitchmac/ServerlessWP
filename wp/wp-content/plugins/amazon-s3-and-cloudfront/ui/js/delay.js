/**
 * Return a promise that resolves after a minimum amount of time has elapsed.
 *
 * @param {number} start   Timestamp of when the action started.
 * @param {number} minTime Minimum amount of time to delay in milliseconds.
 *
 * @return {Promise}
 */
export function delayMin( start, minTime ) {
	let elapsed = Date.now() - start;
	return new Promise( ( resolve ) => setTimeout( resolve, minTime - elapsed ) );
}
