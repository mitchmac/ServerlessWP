/**
 * Scrolls the notifications into view.
 */
export function scrollNotificationsIntoView() {
	const element = document.getElementById( "notifications" );

	if ( element ) {
		element.scrollIntoView( { behavior: "smooth", block: "start" } );
	}
}
