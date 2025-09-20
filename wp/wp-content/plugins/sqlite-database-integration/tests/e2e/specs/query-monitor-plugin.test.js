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

	test.beforeEach( async ( { requestUtils }) => {
		await deactivateQueryMonitor( requestUtils );
	} );

	test.afterEach( async ( { requestUtils }) => {
		await deactivateQueryMonitor( requestUtils );
	} );

	test( 'should activate', async ( { admin, page } ) => {
		// Activate the Query Monitor plugin on the plugins page.
		await admin.visitAdminPage( '/plugins.php' );
		await page.getByLabel( 'Activate Query Monitor', { exact: true } ).click();
		await page.getByText( 'Plugin activated.', { exact: true } ).waitFor();

		// Click on the Query Monitor menu item in the WordPress admin bar.
		await page.locator('a[role="menuitem"][href="#qm-overview"][aria-expanded="false"]').click();

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
		expect( page.locator('.qm-sqlite-query', { hasText: 'SELECT `option_name` , `option_value` FROM `wp_options`' }).first() ).toBeVisible();
	} );
} );
