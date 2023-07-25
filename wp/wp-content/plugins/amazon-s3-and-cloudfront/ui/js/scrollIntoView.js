/**
 * A simple action to scroll the element into view if active.
 *
 * @param {Object} node
 * @param {boolean} active
 */
export function scrollIntoView( node, active ) {
	if ( active ) {
		node.scrollIntoView( { behavior: "smooth", block: "center", inline: "nearest" } );
	}
}