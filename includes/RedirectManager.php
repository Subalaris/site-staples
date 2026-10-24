<?php
/** Bounded rule editing, search and pagination; feature enablement stays in settings. @package SiteStaples */

namespace SiteStaples;

final class RedirectManager {
	const PAGE_SIZE = 25;

	public function register() {
		add_action( 'wp_ajax_site_staples_redirects', array( $this, 'handle' ) );
	}

	public function listing( $search = '', $page = 1 ) {
		$rules = Settings::get()['redirects']['rules'];
		$matches = array();
		foreach ( $rules as $rule ) {
			if ( '' === $search || false !== stripos( $rule['from'], $search ) || false !== stripos( $rule['to'], $search ) ) {
				$matches[] = $rule;
			}
		}
		$pages = max( 1, (int) ceil( count( $matches ) / self::PAGE_SIZE ) );
		$page = max( 1, min( $pages, $page ) );
		return array( 'rules' => array_slice( $matches, ( $page - 1 ) * self::PAGE_SIZE, self::PAGE_SIZE ), 'page' => $page, 'pages' => $pages, 'total' => count( $rules ), 'matched' => count( $matches ), 'version' => RedirectStore::current_token() );
	}

	/** Never accept an array of form rows: each request changes exactly one rule. */
	public function mutate( $operation, $version, $original, $rule = null ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new \WP_Error( 'permission', __( 'You cannot manage redirects.', 'site-staples' ) );
		}
		$locked = RedirectStore::lock();
		if ( is_wp_error( $locked ) ) {
			return $locked;
		}
		try {
			if ( ! is_string( $version ) || $version !== RedirectStore::current_token() ) {
				return new \WP_Error( 'stale', __( 'Redirects changed in another tab. Reload the rules before saving; your entered paths have been kept.', 'site-staples' ) );
			}
			$settings = Settings::get();
			$before = $settings;
			$rules = $settings['redirects']['rules'];
			$index = null;
			foreach ( $rules as $key => $item ) {
				if ( $item['from'] === $original ) {
					$index = $key;
					break;
				}
			}
			if ( ! in_array( $operation, array( 'add', 'update', 'delete' ), true ) || ( 'add' !== $operation && null === $index ) ) {
				return new \WP_Error( 'missing', __( 'This redirect no longer exists. Reload the rules.', 'site-staples' ) );
			}
			if ( 'delete' === $operation ) {
				array_splice( $rules, $index, 1 );
			} elseif ( 'add' === $operation ) {
				$rules[] = $rule;
			} else {
				$rules[ $index ] = $rule;
			}
			$settings['redirects']['rules'] = $rules;
			return Settings::save( $settings, $before );
		} finally {
			RedirectStore::unlock();
		}
	}

	public function handle() {
		check_ajax_referer( 'site_staples_redirects', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) || 'POST' !== sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ?? '' ) ) ) {
			wp_send_json_error( array( 'message' => __( 'You cannot manage redirects.', 'site-staples' ) ), 403 );
		}
		$input = array();
		foreach ( array( 'operation', 'version', 'original', 'from', 'to', 'status', 'search', 'page' ) as $field ) {
			$input[ $field ] = isset( $_POST[ $field ] ) && is_string( $_POST[ $field ] ) ? sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) : '';
		}
		$operation = $input['operation'];
		if ( ! in_array( $operation, array( 'list', 'add', 'update', 'delete' ), true ) || strlen( $input['search'] ) > 1024 ) {
			wp_send_json_error( array( 'message' => __( 'Invalid redirect request.', 'site-staples' ) ), 400 );
		}
		if ( in_array( $operation, array( 'add', 'update' ), true ) && ! in_array( $input['status'], array( '301', '302' ), true ) ) {
			wp_send_json_error( array( 'message' => RedirectRules::error()->get_error_message() ), 400 );
		}
		if ( 'list' !== $operation ) {
			$rule = array( 'from' => trim( $input['from'] ), 'to' => trim( $input['to'] ), 'status' => (int) $input['status'] );
			$result = $this->mutate( $operation, $input['version'], $input['original'], $rule );
			if ( is_wp_error( $result ) ) {
				wp_send_json_error( array( 'message' => $result->get_error_message(), 'code' => $result->get_error_code() ), 409 );
			}
		}
		wp_send_json_success( $this->listing( $input['search'], absint( $input['page'] ) ) );
	}
}
