/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

test.describe( 'Query Monitor plugin', () => {
	async function deactivateQueryMonitor( requestUtils ) {
		await requestUtils.deactivatePlugin( 'query-monitor' );
		const plugin = await requestUtils.rest( {
			path: 'wp/v2/plugins/query-monitor/query-monitor',
		} );
		expect( plugin.status ).toBe( 'inactive' );
	}

	test.beforeEach( async ( { requestUtils } ) => {
		await deactivateQueryMonitor( requestUtils );
	} );

	test.afterEach( async ( { requestUtils } ) => {
		await deactivateQueryMonitor( requestUtils );
	} );

	test( 'should activate and show SQLite queries (QM 4.0+)', async ( { admin, page } ) => {
		// Activate the Query Monitor plugin on the plugins page.
		await admin.visitAdminPage( '/plugins.php' );
		await page.getByLabel( 'Activate Query Monitor', { exact: true } ).click();
		await page.getByText( 'Plugin activated.', { exact: true } ).waitFor();

		// Skip this test if QM 4.0+ is not active (no shadow DOM container).
		const hasContainer = await page.locator( '#query-monitor-container' ).count();
		test.skip( hasContainer === 0, 'QM 4.0+ not detected' );

		// Click on the Query Monitor admin bar item.
		// QM 4.0 re-renders the admin bar with Preact — use the ab-item class.
		await page.locator( '#wp-admin-bar-query-monitor > a.ab-item' ).click();

		// Wait for the QM panel to render inside the shadow DOM.
		const container = page.locator( '#query-monitor-container' );
		await expect( async () => {
			const hasShadow = await container.evaluate(
				( el ) => el.shadowRoot !== null
			);
			expect( hasShadow ).toBe( true );
		} ).toPass();

		// Click on the Database Queries tab inside the shadow DOM.
		// QM 4.0 renders nav items as <button role="tab">.
		await expect( async () => {
			await container.evaluate( ( el ) => {
				const shadow = el.shadowRoot;
				const tabs = shadow.querySelectorAll( 'button[role="tab"]' );
				for ( const tab of tabs ) {
					if ( tab.textContent.includes( 'Database Queries' ) ) {
						tab.click();
						return;
					}
				}
				throw new Error( 'Database Queries tab not found' );
			} );
		} ).toPass();

		// Verify the first logged query is visible in the shadow DOM.
		await expect( async () => {
			const hasSqlQuery = await container.evaluate( ( el ) => {
				const shadow = el.shadowRoot;
				const codeCells = shadow.querySelectorAll( 'td code' );
				for ( const cell of codeCells ) {
					if ( cell.textContent.includes( 'SELECT option_name, option_value' ) ) {
						return true;
					}
				}
				return false;
			} );
			expect( hasSqlQuery ).toBe( true );
		} ).toPass();

		// Click the SQLite <details> summary for the first query row.
		// The element is injected by a debounced MutationObserver, so retry.
		await expect( async () => {
			await container.evaluate( ( el ) => {
				const shadow = el.shadowRoot;
				const summary = shadow.querySelector( 'details.qm-sqlite summary' );
				if ( ! summary ) {
					throw new Error( 'SQLite details summary not found' );
				}
				summary.click();
			} );
		} ).toPass();

		// Verify the SQLite query is displayed.
		await expect( async () => {
			const hasSqliteQuery = await container.evaluate( ( el ) => {
				const shadow = el.shadowRoot;
				const sqliteQueries = shadow.querySelectorAll( '.qm-sqlite-query' );
				for ( const query of sqliteQueries ) {
					if (
						query.textContent.includes( 'SELECT' ) &&
						query.textContent.includes( 'option_name' )
					) {
						return true;
					}
				}
				return false;
			} );
			expect( hasSqliteQuery ).toBe( true );
		} ).toPass();

		// Apply a Caller filter on the DB Queries table and verify our
		// <details> stay attached to the (filtered) visible rows. QM removes
		// hidden rows from the DOM and renumbers them, so this would break
		// any index-based lookup.
		const setCallerFilter = ( value ) =>
			container.evaluate( ( el, v ) => {
				const panel = el.shadowRoot.getElementById( 'qm-db_queries' );
				const callerSelect = panel?.querySelectorAll( 'thead select' )[ 0 ];
				if ( ! callerSelect ) {
					throw new Error( 'Caller filter select not found' );
				}
				callerSelect.value = v;
				callerSelect.dispatchEvent( new Event( 'change', { bubbles: true } ) );
			}, value );

		await expect( () => setCallerFilter( 'get_option' ) ).toPass();

		await expect( async () => {
			const counts = await container.evaluate( ( el ) => {
				const panel = el.shadowRoot.getElementById( 'qm-db_queries' );
				return {
					rows: panel.querySelectorAll( 'tbody tr' ).length,
					details: panel.querySelectorAll( 'details.qm-sqlite' ).length,
				};
			} );
			expect( counts.rows ).toBeGreaterThan( 0 );
			expect( counts.details ).toBe( counts.rows );
		} ).toPass();

		// Reset the filter.
		await setCallerFilter( '' );

		// Switch to "Queries by Caller" and verify no SQLite details bleed
		// into the sub-panel — we explicitly scope injection to the main
		// "Database Queries" panel.
		await expect( async () => {
			await container.evaluate( ( el ) => {
				const shadow = el.shadowRoot;
				const tabs = shadow.querySelectorAll( 'button[role="tab"]' );
				for ( const tab of tabs ) {
					if ( tab.textContent.includes( 'Queries by Caller' ) ) {
						tab.click();
						return;
					}
				}
				throw new Error( 'Queries by Caller tab not found' );
			} );
		} ).toPass();

		await expect( async () => {
			const detailsCount = await container.evaluate( ( el ) => {
				const shadow = el.shadowRoot;
				return shadow.querySelectorAll( 'details.qm-sqlite' ).length;
			} );
			expect( detailsCount ).toBe( 0 );
		} ).toPass();
	} );

	test( 'should activate and show SQLite queries (QM 3.x)', async ( { admin, page } ) => {
		// Activate the Query Monitor plugin on the plugins page.
		await admin.visitAdminPage( '/plugins.php' );
		await page.getByLabel( 'Activate Query Monitor', { exact: true } ).click();
		await page.getByText( 'Plugin activated.', { exact: true } ).waitFor();

		// Skip this test if QM 3.x is not active (has shadow DOM container = QM 4.0+).
		const hasContainer = await page.locator( '#query-monitor-container' ).count();
		test.skip( hasContainer > 0, 'QM 3.x not detected' );

		// Click on the Query Monitor menu item in the WordPress admin bar.
		await page.locator( '#wp-admin-bar-query-monitor > a' ).click();

		// Wait for the Query Monitor panel to open.
		await page.locator( '#query-monitor-main' ).waitFor();
		await page.getByRole( 'heading', { name: 'Query Monitor', exact: true } ).waitFor();

		// Click on the Database Queries tab.
		await page.getByRole( 'tab', { name: 'Database Queries' } ).click();

		// Verify the first logged query.
		const sqlCell = page.locator( '.qm-row-sql' ).first();
		await expect( sqlCell ).toContainText( 'SELECT option_name, option_value' );

		// Check that the query is logged with SQLite information.
		await sqlCell.getByLabel( 'Toggle SQLite queries' ).click();
		await expect(
			page
				.locator( '.qm-sqlite-query', {
					hasText:
						'SELECT `option_name` , `option_value` FROM `wp_options`',
				} )
				.first()
		).toBeVisible();
	} );
} );
