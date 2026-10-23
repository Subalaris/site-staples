<?php
/** Structural accessibility and confirmation-flow checks on rendered admin HTML. */
$checks = 0;
function ui_expect( $condition, $message ) {
	global $checks;
	if ( ! $condition ) { throw new RuntimeException( $message ); }
	++$checks; echo "PASS $message\n";
}
foreach ( array( false, true ) as $enabled ) {
	$process = proc_open( array( PHP_BINARY, __DIR__ . '/render-admin.php', $enabled ? '--enabled' : '--default' ), array( 1 => array( 'pipe', 'w' ), 2 => array( 'pipe', 'w' ) ), $pipes );
	$html = stream_get_contents( $pipes[1] ); fclose( $pipes[1] ); $errors = stream_get_contents( $pipes[2] ); fclose( $pipes[2] );
	ui_expect( 0 === proc_close( $process ) && '' === $errors, 'native Settings API renderer finishes without warnings' );
	$doc = new DOMDocument(); libxml_use_internal_errors( true ); $doc->loadHTML( $html ); libxml_clear_errors(); $xpath = new DOMXPath( $doc );
	$ids = array();
	foreach ( $xpath->query( '//*[@id]' ) as $node ) { $ids[] = $node->getAttribute( 'id' ); }
	ui_expect( count( $ids ) === count( array_unique( $ids ) ), 'rendered control IDs are unique' );
	ui_expect( 1 === $xpath->query( '//form[@action="options.php"]' )->length, 'one Settings API configuration form' );
	foreach ( $xpath->query( '//input[not(@type="hidden") and not(@type="submit")] | //textarea | //select' ) as $input ) {
		$id = $input->getAttribute( 'id' );
		$label = $xpath->query( 'ancestor::label', $input )->length || ( $id && $xpath->query( '//label[@for="' . $id . '"]' )->length );
		ui_expect( (bool) $label, 'control has a native associated label: ' . $input->getAttribute( 'name' ) );
	}
	ui_expect( 0 === $xpath->query( '//form//form' )->length, 'action forms are separate from the settings form' );
	ui_expect( 0 === $xpath->query( '//input[starts-with(@name,"site_staples_settings[") and not(@form="site-staples-settings")] | //textarea[starts-with(@name,"site_staples_settings[") and not(@form="site-staples-settings")]' )->length, 'every setting explicitly belongs to the configuration form' );
	ui_expect( 1 === $xpath->query( '//input[@type="submit" and @form="site-staples-settings"]' )->length, 'save button submits the configuration form' );
	foreach ( $xpath->query( '//nav//a[starts-with(@href,"#")]' ) as $link ) {
		ui_expect( 1 === $xpath->query( '//*[@id="' . substr( $link->getAttribute( 'href' ), 1 ) . '"]' )->length, 'group link has one unique destination' );
	}
	ui_expect( 1 === $xpath->query( '//*[@id="staples-redirect-editor"]' )->length && 0 === $xpath->query( '//textarea[@id="staples-redirect-rules"]' )->length, 'redirects render a row editor instead of a textarea' );
	ui_expect( 2 === $xpath->query( '//select[@data-redirect-field="status"]' )->length, 'redirect rows and their add template contain native type selectors' );
	ui_expect( 12 === $xpath->query( '//article[contains(@class,"site-staples-card")]' )->length, 'all modules and data removal render as feature cards' );
	ui_expect( 11 === $xpath->query( '//input[@role="switch" and @type="checkbox" and @form="site-staples-settings"]' )->length, 'feature switches retain native checkbox form behavior' );
	ui_expect( 1 === $xpath->query( '//button[@type="reset" and @form="site-staples-settings"]' )->length, 'discard uses the native configuration reset' );
	ui_expect( 0 === $xpath->query( '//details | //summary' )->length, 'feature options are always visible without disclosure controls' );
	ui_expect( 9 === $xpath->query( '//fieldset[@data-feature-options]' )->length, 'configurable features use option fieldsets for native disabled behavior' );
	ui_expect( 0 === $xpath->query( '//*[@id="site-staples-unsaved"]' )->length, 'unsaved warning cannot shift tab content' );
	ui_expect( 1 === $xpath->query( '//footer//span[@id="site-staples-save-status" and @role="status"]' )->length, 'save feedback has a persistent footer status' );

	if ( ! $enabled ) {
		ui_expect( 0 === $xpath->query( '//input[contains(@name,"[enabled]") and @checked]' )->length, 'all feature toggles render disabled by default' );
		ui_expect( 1 === $xpath->query( '//form' )->length, 'disabled feature action tools do not render' );
	} else {
		ui_expect( 2 === $xpath->query( '//input[@name="confirm" and @required]' )->length, 'import and cleanup each require explicit confirmation' );
		ui_expect( 1 === $xpath->query( '//input[@name="action" and @value="site_staples_revision_preview"]/ancestor::article//input[@id="staples-revisions"]' )->length, 'cleanup tools sit alongside revision settings' );
		ui_expect( 1 === $xpath->query( '//input[@name="action" and @value="site_staples_import_preview"]/ancestor::article//input[@id="staples-transfer"]' )->length, 'import tools sit alongside transfer settings' );
		ui_expect( 0 === $xpath->query( '//form[@action="options.php"]//input[@name="action" or @name="confirm" or @name="preview_token"]' )->length, 'settings save cannot submit a cleanup or import action' );

		ui_expect( 2 === $xpath->query( '//input[@name="preview_token"]' )->length, 'confirmation actions carry saved preview tokens' );
		ui_expect( str_contains( $html, 'Remove configuration on uninstall' ) && str_contains( $html, 'Selected post types' ) && ! str_contains( $html, '{&quot;enabled' ), 'import preview describes changes in user-facing language' );
		ui_expect( 1 === $xpath->query( '//input[@type="file" and @required and @accept=".json,application/json"]' )->length, 'import file has a labeled required JSON selector' );
	}
}
echo "$checks rendered interface checks passed. No browser or assistive-technology simulation.\n";
