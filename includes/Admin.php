<?php
/** Native Settings API page and explicit action previews. @package SiteStaples */

namespace SiteStaples;

final class Admin {
	public function register() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_init', array( $this, 'settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( SITE_STAPLES_FILE ), array( $this, 'links' ) );
	}

	private function labels() {
		return array(
			'comments' => __( 'Disable comments', 'site-staples' ),
			'svg' => __( 'Safe SVG support', 'site-staples' ),
			'duplicate' => __( 'Duplicate posts and pages', 'site-staples' ),
			'media' => __( 'Replace media', 'site-staples' ),
			'revisions' => __( 'Revision limits', 'site-staples' ),
			'transfer' => __( 'Settings import/export', 'site-staples' ),
			'image_sizes' => __( 'Generated image sizes', 'site-staples' ),
			'redirects' => __( 'Simple redirects', 'site-staples' ),
			'maintenance' => __( 'Maintenance mode', 'site-staples' ),
			'admin_cleanup' => __( 'Dashboard & toolbar', 'site-staples' ),
			'columns' => __( 'Content list columns', 'site-staples' ),
			'uninstall' => __( 'Data removal', 'site-staples' ),
		);
	}

	public function menu() {
		add_options_page( __( 'Site Staples', 'site-staples' ), __( 'Site Staples', 'site-staples' ), 'manage_options', 'site-staples', array( $this, 'render' ) );
	}

	public function links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'options-general.php?page=site-staples' ) ) . '">' . esc_html__( 'Settings', 'site-staples' ) . '</a>' );
		return $links;
	}

	/** Load the small enhancement only on this plugin's settings screen. */
	public function assets( $hook ) {
		if ( 'settings_page_site-staples' !== $hook ) {
			return;
		}
		$url = plugin_dir_url( SITE_STAPLES_FILE ) . 'assets/';
		wp_enqueue_style( 'site-staples-admin', $url . 'admin.css', array(), (string) filemtime( dirname( SITE_STAPLES_FILE ) . '/assets/admin.css' ) );
		wp_enqueue_script( 'site-staples-redirects', $url . 'redirects.js', array(), (string) filemtime( dirname( SITE_STAPLES_FILE ) . '/assets/redirects.js' ), true );
		wp_enqueue_script( 'site-staples-admin', $url . 'admin.js', array( 'site-staples-redirects' ), (string) filemtime( dirname( SITE_STAPLES_FILE ) . '/assets/admin.js' ), true );
	}

	private function groups() {
		return array(
			'content' => array( 'label' => __( 'Content', 'site-staples' ), 'features' => array( 'duplicate', 'comments', 'revisions' ) ),
			'media' => array( 'label' => __( 'Media', 'site-staples' ), 'features' => array( 'svg', 'media', 'image_sizes' ) ),
			'site' => array( 'label' => __( 'Site behavior', 'site-staples' ), 'features' => array( 'redirects', 'maintenance' ) ),
			'admin' => array( 'label' => __( 'Admin workspace', 'site-staples' ), 'features' => array( 'admin_cleanup', 'columns' ) ),
			'plugin' => array( 'label' => __( 'Plugin settings', 'site-staples' ), 'features' => array( 'transfer', 'uninstall' ) ),
		);
	}

	public function settings() {
		register_setting( 'site_staples', Settings::OPTION, array( 'type' => 'object', 'sanitize_callback' => array( Settings::class, 'sanitize' ), 'default' => Settings::defaults(), 'show_in_rest' => false ) );
	}

	private function descriptions() {
		return array(
			'media' => __( 'Replace an image while keeping its attachment ID and URL.', 'site-staples' ),
			'transfer' => __( 'Move your Site Staples configuration between sites.', 'site-staples' ),
			'uninstall' => __( 'Choose what happens to plugin settings when Site Staples is deleted.', 'site-staples' ),
			'comments' => __( 'Turn off comments across the site or for selected content types.', 'site-staples' ),
			'svg' => __( 'Allow sanitized SVG uploads for trusted users.', 'site-staples' ),
			'duplicate' => __( 'Create an editable draft from an existing post or page.', 'site-staples' ),
			'revisions' => __( 'Limit saved revisions and preview cleanup of older revisions.', 'site-staples' ),
			'image_sizes' => __( 'Choose which image sizes WordPress generates for future uploads.', 'site-staples' ),
			'redirects' => __( 'Send visitors from an old local path to a new one.', 'site-staples' ),
			'maintenance' => __( 'Show a temporary maintenance message to visitors.', 'site-staples' ),
			'admin_cleanup' => __( 'Choose which dashboard widgets and toolbar items each role sees.', 'site-staples' ),
			'columns' => __( 'Add useful information to post and page lists.', 'site-staples' ),
		);
	}

	/** Decorative line artwork from the supplied dashboard template. */
	private function icon( $key ) {
		$icons = array(
			'layers' => '<path d="m12 3 9 5-9 5-9-5 9-5ZM3 12l9 5 9-5M3 16l9 5 9-5"/>',
			'content' => '<rect x="5" y="3" width="14" height="18" rx="2"/><path d="M9 8h6M9 12h6M9 16h4"/>',
			'media' => '<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="8" cy="9" r="1.5"/><path d="m3 17 6-5 4 4 3-3 5 4"/>',
			'behavior' => '<path d="m13 3-8 10h6l-1 8 9-11h-6l1-7Z"/>',
			'workspace' => '<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 9h18M9 9v11"/>',
			'settings' => '<path d="M4 7h16M4 17h16"/><circle cx="9" cy="7" r="3" fill="currentColor" stroke="none"/><circle cx="15" cy="17" r="3" fill="currentColor" stroke="none"/>',
			'copy' => '<rect x="8" y="8" width="12" height="13" rx="2"/><path d="M15 8V4a1 1 0 0 0-1-1H4a1 1 0 0 0-1 1v10a1 1 0 0 0 1 1h4"/>',
			'comments' => '<path d="M20 11V5a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h2v4l5-4h3M3 3l18 18"/>',
			'history' => '<path d="M3 10a9 9 0 1 1 2 8M3 4v6h6M12 7v5l3 2"/>',
			'shield' => '<path d="m12 3 8 3v6c0 4-4 7-8 9-4-2-8-5-8-9V6l8-3Z"/><path d="m8 12 3 3 5-6"/>',
		);
		$aliases = array( 'duplicate' => 'copy', 'revisions' => 'history', 'svg' => 'shield', 'image_sizes' => 'media', 'redirects' => 'behavior', 'maintenance' => 'shield', 'admin_cleanup' => 'workspace', 'columns' => 'content', 'transfer' => 'settings', 'uninstall' => 'settings', 'site' => 'behavior', 'admin' => 'workspace', 'plugin' => 'settings' );
		$key = $aliases[ $key ] ?? $key;
		return '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">' . ( $icons[ $key ] ?? $icons['settings'] ) . '</svg>';
	}

	private function group_descriptions() {
		return array(
			'content' => __( 'A smoother workflow for your posts and pages.', 'site-staples' ),
			'media' => __( 'Practical controls for uploads, image sizes and file replacement.', 'site-staples' ),
			'site' => __( 'Manage where visitors go and what they see.', 'site-staples' ),
			'admin' => __( 'Make your WordPress workspace work for you.', 'site-staples' ),
			'plugin' => __( 'Transfer your configuration and manage plugin data.', 'site-staples' ),
		);
	}

	private function feature_header( $key, $enabled ) {
		echo '<header class="site-staples-cardhead"><span class="site-staples-feature-icon">' . $this->icon( $key ) . '</span><div class="site-staples-feature-text"><div class="site-staples-titleline"><h3 id="staples-label-' . esc_attr( $key ) . '">' . esc_html( $this->labels()[ $key ] ) . '</h3>';
		if ( 'uninstall' !== $key ) {
			echo '<span class="site-staples-badge" data-feature-state data-on="' . esc_attr__( 'Enabled', 'site-staples' ) . '" data-off="' . esc_attr__( 'Disabled', 'site-staples' ) . '" data-enabled="' . ( $enabled ? 'true' : 'false' ) . '">' . esc_html( $enabled ? __( 'Enabled', 'site-staples' ) : __( 'Disabled', 'site-staples' ) ) . '</span>';
		}
		echo '</div><p>' . esc_html( $this->descriptions()[ $key ] ) . '</p></div>';
		if ( 'uninstall' !== $key ) {
			echo '<label class="site-staples-switch-control"><span class="screen-reader-text">' . esc_html( $this->labels()[ $key ] ) . '</span><input class="site-staples-switch" type="checkbox" role="switch" form="site-staples-settings" id="staples-' . esc_attr( $key ) . '" name="' . esc_attr( Settings::OPTION . '[' . $key . '][enabled]' ) . '" value="1" ' . checked( $enabled, true, false ) . '></label>';
		}
		echo '</header>';
	}

	private function checkbox( $name, $checked, $label, $id = '' ) {
		echo '<label' . ( $id ? ' for="' . esc_attr( $id ) . '"' : '' ) . '><input type="checkbox" form="site-staples-settings" name="' . esc_attr( Settings::OPTION . $name ) . '" value="1" ' . checked( $checked, true, false ) . ( $id ? ' id="' . esc_attr( $id ) . '"' : '' ) . '> ' . esc_html( $label ) . '</label>';
	}

	private function types( $key, $values ) {
		echo '<fieldset><legend class="screen-reader-text">' . esc_html__( 'Selected post types', 'site-staples' ) . '</legend>';
		foreach ( Settings::post_types() as $type ) {
			echo '<label><input type="checkbox" form="site-staples-settings" name="' . esc_attr( Settings::OPTION . '[' . $key . '][post_types][]' ) . '" value="' . esc_attr( $type->name ) . '" ' . checked( in_array( $type->name, $values, true ), true, false ) . '> ' . esc_html( $type->labels->name ) . '</label><br>';
		}
		echo '</fieldset>';
	}

	private function list_checkbox( $module, $list, $item, $selected, $label ) {
		echo '<label><input type="checkbox" form="site-staples-settings" name="' . esc_attr( Settings::OPTION . '[' . $module . '][' . $list . '][]' ) . '" value="' . esc_attr( $item ) . '" ' . checked( in_array( $item, $selected, true ), true, false ) . '> ' . esc_html( $label ) . '</label><br>';
	}

	private function roles( $module, $selected, $legend ) {
		echo '<fieldset><legend>' . esc_html( $legend ) . '</legend>';
		foreach ( wp_roles()->roles as $role => $info ) {
			$this->list_checkbox( $module, 'roles', $role, $selected, translate_user_role( $info['name'] ) );
		}
		echo '</fieldset><br>';
	}

	public function field( $args ) {
		$key = $args['key'];
		$settings = Settings::get();
		$this->feature_header( $key, 'uninstall' === $key ? false : $settings[ $key ]['enabled'] );
		if ( 'uninstall' === $key ) {
			echo '<div class="site-staples-cardbody">';
			$this->checkbox( '[uninstall]', $settings['uninstall'], __( 'Remove Site Staples configuration when the plugin is deleted', 'site-staples' ), 'staples-uninstall' );
			echo '<p class="description">' . esc_html__( 'Deactivation always preserves settings. Uninstall never deletes posts, comments, revisions, or media.', 'site-staples' ) . '</p></div>';
			return;
		}
		$value = $settings[ $key ];
		$configurable = ! in_array( $key, array( 'media', 'transfer' ), true );
		if ( $configurable ) {
			echo '<fieldset class="site-staples-options" data-feature-options><legend class="screen-reader-text">';
			/* translators: %s: feature name. */
			echo esc_html( sprintf( __( 'Options for %s', 'site-staples' ), $this->labels()[ $key ] ) ) . '</legend><div class="site-staples-options-content">';
		}
		if ( ! $configurable ) {
			echo '<div class="site-staples-cardbody">';
		}
		switch ( $key ) {
			case 'image_sizes':
				$sizes = Settings::image_sizes();
				echo '<fieldset><legend>' . esc_html__( 'Do not generate these sizes', 'site-staples' ) . '</legend>';
				foreach ( $sizes as $name => $size ) {
					/* translators: 1: image size name, 2: width, 3: height, 4: resize mode. */
					$label = sprintf( __( '%1$s — %2$d × %3$d (%4$s)', 'site-staples' ), $name, $size['width'], $size['height'], empty( $size['crop'] ) ? __( 'proportional', 'site-staples' ) : __( 'cropped', 'site-staples' ) );
					$this->list_checkbox( 'image_sizes', 'disabled_sizes', $name, $value['disabled_sizes'], $label );
				}
				foreach ( array_diff( $value['disabled_sizes'], array_keys( $sizes ) ) as $name ) {
					/* translators: %s: a previously registered image size. */
					$this->list_checkbox( 'image_sizes', 'disabled_sizes', $name, $value['disabled_sizes'], sprintf( __( '%s (not currently registered)', 'site-staples' ), $name ) );
				}
				echo '</fieldset><p class="description">' . esc_html__( 'Applies to future uploads, regeneration, and file replacement. Existing files remain. Fewer sizes can reduce responsive image choices and make visitors download larger images. Themes may fall back to the full image. 0 means an unconstrained dimension; full-size originals and WordPress big-image scaling are preserved.', 'site-staples' ) . '</p>';
				break;
			case 'redirects':
				echo '<div id="staples-redirect-editor" data-max-rows="' . esc_attr( RedirectRules::MAX_RULES ) . '"><input type="hidden" form="site-staples-settings" name="' . esc_attr( Settings::OPTION . '[redirects][rows_present]' ) . '" value="1"><div data-redirect-rows>';
				$rules = $value['rules'];
				if ( count( $rules ) < RedirectRules::MAX_RULES ) {
					$rules[] = array( 'from' => '', 'to' => '', 'status' => 302 );
				}
				foreach ( $rules as $index => $rule ) {
					$this->redirect_row( $index, $rule );
				}
				echo '</div><template data-redirect-template>';
				$this->redirect_row( '__INDEX__', array( 'from' => '', 'to' => '', 'status' => 302 ) );
				echo '</template><button type="button" class="button" data-redirect-add hidden>' . esc_html__( 'Add redirect', 'site-staples' ) . '</button></div>';
				echo '<p id="staples-redirect-help" class="description">' . esc_html__( 'Enter local paths such as /old-page/ and /new-page/. Start with 302 (temporary) while testing; browsers can cache 301 (permanent). Maximum 100 exact local redirects. Leave both paths blank to ignore a row. No wildcards, query strings, external destinations, or protected WordPress routes. Include the site prefix on subdirectory sites. Only GET/HEAD visitors redirect; incoming query strings are discarded. Local chains resolve in one hop.', 'site-staples' ) . '</p>';
				break;
			case 'maintenance':
				echo '<p><label for="staples-maintenance-title">' . esc_html__( 'Visitor page title (maximum 120 characters)', 'site-staples' ) . '</label><br><input form="site-staples-settings" class="regular-text" type="text" maxlength="120" id="staples-maintenance-title" name="' . esc_attr( Settings::OPTION . '[maintenance][title]' ) . '" value="' . esc_attr( $value['title'] ) . '"></p>';
				echo '<p><label for="staples-maintenance-message">' . esc_html__( 'Visitor message (plain text, maximum 2000 characters)', 'site-staples' ) . '</label><br><textarea form="site-staples-settings" class="large-text" rows="3" maxlength="2000" id="staples-maintenance-message" name="' . esc_attr( Settings::OPTION . '[maintenance][message]' ) . '">' . esc_textarea( $value['message'] ) . '</textarea></p>';
				$this->roles( 'maintenance', $value['roles'], __( 'Additional roles that can view the site', 'site-staples' ) );
				echo '<p class="description">' . esc_html__( 'Visitors receive HTTP 503 and a one-hour Retry-After header, including on REST requests. Administrators retain access. Login, admin screens, AJAX, cron, XML-RPC, and static files remain available. This does not make private content secure. Cached pages may bypass WordPress; purge or disable page/CDN caches before enabling. Blank text uses the translated default message.', 'site-staples' ) . '</p>';
				break;
			case 'admin_cleanup':
				$this->roles( 'admin_cleanup', $value['roles'], __( 'Apply cleanup to these roles', 'site-staples' ) );
				foreach ( array( 'widgets' => Settings::widget_labels(), 'toolbar' => Settings::toolbar_labels() ) as $list => $items ) {
					echo '<fieldset><legend>' . esc_html( 'widgets' === $list ? __( 'Hide dashboard widgets', 'site-staples' ) : __( 'Hide toolbar items', 'site-staples' ) ) . '</legend>';
					foreach ( $items as $item => $label ) {
						$this->list_checkbox( 'admin_cleanup', $list, $item, $value[ $list ], $label );
					}
					echo '</fieldset><br>';
				}
				echo '<p class="description">' . esc_html__( 'Presentation only: capabilities and direct access remain unchanged. A user with any selected role is affected. Toolbar choices also apply on the front end. The account menu and maintenance indicator remain available.', 'site-staples' ) . '</p>';
				break;
			case 'columns':
				$this->types( $key, $value['post_types'] );
				foreach ( array( 'show_id' => __( 'Content ID', 'site-staples' ), 'show_thumbnail' => __( 'Featured-image thumbnail', 'site-staples' ), 'show_modified' => __( 'Last modified date and time', 'site-staples' ) ) as $flag => $label ) {
					$this->checkbox( '[columns][' . $flag . ']', $value[ $flag ], $label );
					echo '<br>';
				}
				echo '<p class="description">' . esc_html__( 'Adds the selected information to supported content lists. Columns do not change editing permissions or create new image sizes.', 'site-staples' ) . '</p>';
				break;
			case 'comments':
				echo '<fieldset><legend>' . esc_html__( 'Comment scope', 'site-staples' ) . '</legend>';
				foreach ( array( 'all' => __( 'Entire site', 'site-staples' ), 'selected' => __( 'Selected post types', 'site-staples' ) ) as $scope => $label ) {
					echo '<label><input type="radio" form="site-staples-settings" name="' . esc_attr( Settings::OPTION . '[comments][scope]' ) . '" value="' . esc_attr( $scope ) . '" ' . checked( $value['scope'], $scope, false ) . '> ' . esc_html( $label ) . '</label><br>';
				}
				echo '</fieldset><br>';
				$this->types( $key, $value['post_types'] );
				echo '<p class="description">' . esc_html__( 'Blocks comments, pingbacks, and trackbacks, including on existing content. Existing comments stay in the database and return when disabled. Type selections apply only to “Selected post types”.', 'site-staples' ) . '</p>';
				break;
			case 'svg':
				echo '<fieldset><legend>' . esc_html__( 'Additional roles allowed to upload SVGs', 'site-staples' ) . '</legend>';
				foreach ( wp_roles()->roles as $role => $info ) {
					if ( 'administrator' === $role ) {
						continue;
					}
					echo '<label><input type="checkbox" form="site-staples-settings" name="' . esc_attr( Settings::OPTION . '[svg][roles][]' ) . '" value="' . esc_attr( $role ) . '" ' . checked( in_array( $role, $value['roles'], true ), true, false ) . '> ' . esc_html( translate_user_role( $info['name'] ) ) . '</label><br>';
				}
				echo '</fieldset><p class="description">' . esc_html__( 'Administrators with upload permission are allowed by default. Selected roles still need upload_files. SVGs are sanitized, limited to 2 MB, and stripped of CSS and external references. PHP DOM and Fileinfo are required. Multisite upload restrictions still apply.', 'site-staples' ) . '</p>';
				if ( ! class_exists( 'DOMDocument' ) || ! class_exists( 'finfo' ) ) {
					echo '<p><strong>' . esc_html__( 'SVG uploads are unavailable: enable the PHP DOM and Fileinfo extensions.', 'site-staples' ) . '</strong></p>';
				}
				break;
			case 'duplicate':
				$this->types( $key, $value['post_types'] );
				echo '<p class="description">' . esc_html__( 'Adds “Duplicate as draft” to the selected content lists. Copies content, terms, featured image and safe metadata. Protected integration data and likely unique identifiers are excluded.', 'site-staples' ) . '</p>';
				break;
			case 'media':
				echo '<p class="description">' . esc_html__( 'Adds “Replace file” to attachment edit screens. Supports local JPEG, PNG, GIF, WebP and AVIF files with the same type and extension. Keeps the ID and URL. Dimensions may change and cached copies may take time to refresh.', 'site-staples' ) . '</p>';
				break;
			case 'revisions':
				echo '<label for="staples-revision-limit">' . esc_html__( 'Maximum revisions per post', 'site-staples' ) . '</label> <input form="site-staples-settings" class="small-text" id="staples-revision-limit" name="' . esc_attr( Settings::OPTION . '[revisions][limit]' ) . '" type="number" min="0" max="1000" value="' . esc_attr( $value['limit'] ) . '">';
				echo '<p class="description">' . esc_html__( '0 disables future normal revisions. Autosaves are preserved. Saving settings does not clean up existing revisions; WordPress trims them when a future revision is saved. Use the separate preview below for an explicit cleanup.', 'site-staples' ) . '</p>';
				break;
			case 'transfer':
				echo '<p class="description">' . esc_html__( 'Save this toggle to reveal export and import tools. Imports require a preview and confirmation and change configuration only.', 'site-staples' ) . '</p>';
				break;
		}
		if ( $configurable ) {
			echo '</div></fieldset>';
		} else {
			echo '</div>';
		}
		if ( $value['enabled'] && in_array( $key, array( 'revisions', 'transfer' ), true ) ) {
			echo '<div class="site-staples-tools">';
			if ( 'revisions' === $key ) {
				$this->revision_tools();
			} else {
				$this->transfer_tools();
			}
			echo '</div>';
		}
	}


	private function redirect_row( $index, $rule ) {
		$prefix = Settings::OPTION . '[redirects][rules_rows][' . $index . ']';
		echo '<div class="site-staples-redirect-row" data-redirect-row>';
		foreach ( array( 'from' => __( 'From path', 'site-staples' ), 'to' => __( 'To path', 'site-staples' ) ) as $field => $label ) {
			echo '<label>' . esc_html( $label ) . '<input type="text" form="site-staples-settings" data-redirect-field="' . esc_attr( $field ) . '" name="' . esc_attr( $prefix . '[' . $field . ']' ) . '" value="' . esc_attr( $rule[ $field ] ) . '" placeholder="' . ( 'from' === $field ? '/old-page/' : '/new-page/' ) . '" maxlength="1024" aria-describedby="staples-redirect-help" spellcheck="false" autocomplete="off"></label>';
		}
		echo '<label>' . esc_html__( 'Redirect type', 'site-staples' ) . '<select form="site-staples-settings" data-redirect-field="status" name="' . esc_attr( $prefix . '[status]' ) . '">';
		foreach ( array( 302 => __( '302 — Temporary', 'site-staples' ), 301 => __( '301 — Permanent', 'site-staples' ) ) as $status => $label ) {
			echo '<option value="' . esc_attr( $status ) . '"' . ( $status === $rule['status'] ? ' selected' : '' ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select></label><button type="button" class="button" data-redirect-remove hidden>' . esc_html__( 'Remove', 'site-staples' ) . '</button></div>';
	}

	/** Small native POST form for an action independent of the settings save. */
	private function action_form( $action, $label, $upload = false, $extra = null ) {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"' . ( $upload ? ' enctype="multipart/form-data"' : '' ) . '>';
		echo '<input type="hidden" name="action" value="' . esc_attr( $action ) . '">';
		Guard::nonce( $action );
		if ( $upload ) {
			echo '<p><label for="staples-import">' . esc_html__( 'Settings JSON file (maximum 256 KB)', 'site-staples' ) . '</label><br><input id="staples-import" type="file" name="settings_file" accept=".json,application/json" required></p>';
		}
		if ( $extra ) {
			$extra();
		}
		submit_button( $label, 'secondary', 'submit', true, array( 'id' => $action . '-submit' ) );
		echo '</form>';
	}

	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$settings = Settings::get();
		echo '<div class="wrap site-staples-admin"><h1 class="screen-reader-text">' . esc_html__( 'Site Staples', 'site-staples' ) . '</h1><header class="site-staples-brandrow"><div class="site-staples-brand"><img class="site-staples-brand-logo" src="' . esc_url( plugin_dir_url( SITE_STAPLES_FILE ) . 'assets/logo.svg' ) . '" alt="' . esc_attr__( 'Site Staples', 'site-staples' ) . '" width="975" height="124"><span class="site-staples-version">' . esc_html( SITE_STAPLES_VERSION ) . '</span></div></header>';
		if ( is_multisite() ) {
			echo '<div class="notice notice-info inline"><p>' . esc_html__( 'Settings apply to this site only. Network defaults and site overrides are not available. SVG uploads remain subject to network file-type policy.', 'site-staples' ) . '</p></div>';
		}
		$notices = array( 'imported' => __( 'Configuration imported. No cleanup or content action was performed.', 'site-staples' ), 'revision_batch' => __( 'Revision cleanup batch finished. Review the remaining count below.', 'site-staples' ) );
		$code = isset( $_GET['staples_notice'] ) && is_string( $_GET['staples_notice'] ) ? sanitize_key( $_GET['staples_notice'] ) : '';
		if ( isset( $notices[ $code ] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $notices[ $code ] ) . '</p></div>';
		}
		settings_errors();
		echo '<div class="site-staples-tabs-layout">';
		echo '<nav class="site-staples-navigation" aria-label="' . esc_attr__( 'Feature groups', 'site-staples' ) . '"><p class="site-staples-nav-label">' . esc_html__( 'Workspace', 'site-staples' ) . '</p><ul>';
		foreach ( $this->groups() as $key => $group ) {
			echo '<li><a id="staples-tab-' . esc_attr( $key ) . '" href="#staples-group-' . esc_attr( $key ) . '">' . $this->icon( $key ) . '<span>' . esc_html( $group['label'] ) . '</span><span class="site-staples-nav-arrow" aria-hidden="true">›</span></a></li>';
		}
		echo '</ul><p class="site-staples-nav-note"><strong>' . esc_html__( 'Lightweight by design.', 'site-staples' ) . '</strong>' . esc_html__( 'Every feature starts disabled. You decide what belongs on your site.', 'site-staples' ) . '</p></nav><div class="site-staples-panels">';
		// Explicit form ownership keeps configuration together without nesting action forms.
		echo '<form id="site-staples-settings" method="post" action="options.php">';
		settings_fields( 'site_staples' );
		echo '</form>';
		foreach ( $this->groups() as $key => $group ) {
			$enabled = 0;
			$total = 0;
			foreach ( $group['features'] as $feature ) {
				if ( 'uninstall' !== $feature ) {
					++$total;
					$enabled += $settings[ $feature ]['enabled'] ? 1 : 0;
				}
			}
			echo '<section id="staples-group-' . esc_attr( $key ) . '" class="site-staples-group" aria-labelledby="staples-heading-' . esc_attr( $key ) . '"><header class="site-staples-sectionhead"><div><h2 id="staples-heading-' . esc_attr( $key ) . '" tabindex="-1">' . esc_html( $group['label'] ) . '</h2><p>' . esc_html( $this->group_descriptions()[ $key ] ) . '</p></div>';
			/* translators: 1: enabled features, 2: available features in this group. */
			echo '<span class="site-staples-count" data-enabled-count data-template="' . esc_attr__( '%1$d of %2$d enabled', 'site-staples' ) . '">' . esc_html( sprintf( __( '%1$d of %2$d enabled', 'site-staples' ), $enabled, $total ) ) . '</span></header>';
			foreach ( $group['features'] as $feature ) {
				echo '<article class="site-staples-card" aria-labelledby="staples-label-' . esc_attr( $feature ) . '">';
				$this->field( array( 'key' => $feature ) );
				echo '</article>';
			}
			echo '</section>';
		}
		echo '<p class="site-staples-bottomnote">' . $this->icon( 'shield' ) . esc_html__( 'Changes apply after saving. Cleanup always requires a separate action.', 'site-staples' ) . '</p>';
		echo '<footer class="site-staples-savebar"><span id="site-staples-save-status" role="status" data-clean="' . esc_attr__( 'No unsaved changes', 'site-staples' ) . '" data-dirty="' . esc_attr__( 'You have unsaved changes', 'site-staples' ) . '">' . esc_html__( 'Changes apply after saving.', 'site-staples' ) . '</span><button type="reset" class="button" id="site-staples-discard" form="site-staples-settings" hidden>' . esc_html__( 'Discard', 'site-staples' ) . '</button>';
		submit_button( __( 'Save changes', 'site-staples' ), 'primary', 'submit', false, array( 'form' => 'site-staples-settings', 'id' => 'site-staples-save' ) );
		echo '</footer>';
		echo '</div></div></div>';
	}

	private function confirmation( $state, $label ) {
		echo '<input type="hidden" name="preview_token" value="' . esc_attr( $state['token'] ) . '"><p><label><input type="checkbox" name="confirm" value="1" required> ' . esc_html( $label ) . '</label></p>';
		echo '<p><a href="' . esc_url( admin_url( 'options-general.php?page=site-staples' ) ) . '">' . esc_html__( 'Back to settings', 'site-staples' ) . '</a></p>';
	}

	private function revision_tools() {
		echo '<h3 id="staples-revision-tools" tabindex="-1">' . esc_html__( 'Revision cleanup', 'site-staples' ) . '</h3><p>' . esc_html__( 'Preview old normal revisions before removing any. Autosaves are excluded. Cleanup runs in batches of up to 100; continue each batch explicitly. Back up your database before cleanup.', 'site-staples' ) . '</p>';
		$this->action_form( 'site_staples_revision_preview', __( 'Preview cleanup', 'site-staples' ) );
		$state = get_transient( Modules\Revisions::transient_key() );
		if ( $state ) {
			echo '<h4>' . esc_html__( 'Cleanup preview', 'site-staples' ) . '</h4><p>';
			/* translators: 1: revisions at preview, 2: already removed, 3: current remaining candidates. */
			echo esc_html( sprintf( __( 'Preview: %1$d revisions to remove. Removed in this run: %2$d. Remaining: %3$d.', 'site-staples' ), $state['count'], $state['deleted'], $state['remaining'] ?? $state['count'] ) );
			echo '</p>';
			if ( ( $state['remaining'] ?? $state['count'] ) > 0 ) {
				$this->action_form( 'site_staples_revision_cleanup', __( 'Remove next batch', 'site-staples' ), false, function () use ( $state ) {
					$this->confirmation( $state, __( 'I confirm permanent deletion of these old revisions, up to 100 in this batch.', 'site-staples' ) );
				} );
			}
		}
	}

	private function transfer_tools() {
		echo '<h3 id="staples-transfer-tools" tabindex="-1">' . esc_html__( 'Settings import/export tools', 'site-staples' ) . '</h3><p>' . esc_html__( 'Transfer versioned configuration only. Imports never run revision cleanup, replace files, or duplicate content. Roles and post types must exist on this site.', 'site-staples' ) . '</p>';
		$this->action_form( 'site_staples_export', __( 'Export settings', 'site-staples' ) );
		$this->action_form( 'site_staples_import_preview', __( 'Preview import', 'site-staples' ), true );
		$state = get_transient( Modules\Transfer::transient_key() );
		if ( ! $state ) {
			return;
		}
		echo '<h4>' . esc_html__( 'Import preview', 'site-staples' ) . '</h4><table class="widefat striped"><thead><tr><th scope="col">' . esc_html__( 'Setting', 'site-staples' ) . '</th><th scope="col">' . esc_html__( 'Current', 'site-staples' ) . '</th><th scope="col">' . esc_html__( 'Imported', 'site-staples' ) . '</th></tr></thead><tbody>';
		$current = Settings::get();
		foreach ( $state['settings'] as $key => $value ) {
			if ( $value === $current[ $key ] ) {
				continue;
			}
			$before = is_array( $current[ $key ] ) ? $current[ $key ] : array( 'uninstall' => $current[ $key ] );
			$after = is_array( $value ) ? $value : array( 'uninstall' => $value );
			$fields = array( 'enabled' => __( 'Enabled', 'site-staples' ), 'scope' => __( 'Scope', 'site-staples' ), 'roles' => __( 'Additional roles', 'site-staples' ), 'post_types' => __( 'Post types', 'site-staples' ), 'limit' => __( 'Maximum revisions', 'site-staples' ), 'uninstall' => __( 'Remove configuration on uninstall', 'site-staples' ), 'disabled_sizes' => __( 'Sizes not generated', 'site-staples' ), 'rules' => __( 'Redirect rules', 'site-staples' ), 'widgets' => __( 'Hidden dashboard widgets', 'site-staples' ), 'toolbar' => __( 'Hidden toolbar items', 'site-staples' ), 'title' => __( 'Visitor title', 'site-staples' ), 'message' => __( 'Visitor message', 'site-staples' ), 'show_id' => __( 'Content ID', 'site-staples' ), 'show_thumbnail' => __( 'Featured image', 'site-staples' ), 'show_modified' => __( 'Last modified', 'site-staples' ) );
			foreach ( $after as $field => $field_value ) {
				if ( $before[ $field ] !== $field_value ) {
					echo '<tr><th scope="row">' . esc_html( $this->labels()[ $key ] . ': ' . $fields[ $field ] ) . '</th><td>' . esc_html( $this->value_label( $before[ $field ], $field ) ) . '</td><td>' . esc_html( $this->value_label( $field_value, $field ) ) . '</td></tr>';
				}
			}
		}
		if ( $state['settings'] === $current ) {
			echo '<tr><td colspan="3">' . esc_html__( 'No configuration changes.', 'site-staples' ) . '</td></tr>';
		}
		echo '</tbody></table>';
		$this->action_form( 'site_staples_import_apply', __( 'Apply imported configuration', 'site-staples' ), false, function () use ( $state ) {
			$this->confirmation( $state, __( 'I confirm the configuration changes shown above, including feature and uninstall settings.', 'site-staples' ) );
		} );
	}
	/** Human-readable preview values use the same vocabulary as the settings. */
	private function value_label( $value, $field ) {
		if ( is_bool( $value ) ) {
			return $value ? __( 'Yes', 'site-staples' ) : __( 'No', 'site-staples' );
		}
		if ( 'scope' === $field ) {
			return 'all' === $value ? __( 'Entire site', 'site-staples' ) : __( 'Selected post types', 'site-staples' );
		}
		if ( 'rules' === $field ) {
			$lines = array();
			foreach ( $value as $rule ) {
				$lines[] = $rule['status'] . ' ' . $rule['from'] . ' → ' . $rule['to'];
			}
			return $lines ? implode( '; ', $lines ) : __( 'None', 'site-staples' );
		}
		if ( is_array( $value ) ) {
			$labels = array();
			foreach ( $value as $item ) {
				if ( 'roles' === $field ) {
					$labels[] = translate_user_role( wp_roles()->roles[ $item ]['name'] ?? $item );
				} elseif ( 'post_types' === $field ) {
					$labels[] = get_post_type_object( $item )->labels->name ?? $item;
				} elseif ( 'widgets' === $field || 'toolbar' === $field ) {
					$items = 'widgets' === $field ? Settings::widget_labels() : Settings::toolbar_labels();
					$labels[] = $items[ $item ] ?? $item;
				} else {
					$labels[] = $item;
				}
			}
			return $labels ? implode( ', ', $labels ) : __( 'None', 'site-staples' );
		}
		return (string) $value;
	}

}
