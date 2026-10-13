<?php
/**
 * Loader, activation and uninstall tests.
 *
 * @package CustomPostTypesDP
 */

/**
 * @group loader
 */
class CPTDP_Test_Loader extends CPTDP_Test_Case {

	public function test_plugin_constants(): void {
		$this->assertSame( dirname( __DIR__ ) . '/', CPTDP_PATH );
		$this->assertMatchesRegularExpression( '/^\d+\.\d+\.\d+$/', CPTDP_VERSION );
		$this->assertStringContainsString( 'custom-post-types-dp', CPTDP_URL );
	}

	public function test_loader_requires_every_module_except_index(): void {
		$included = array_map( 'realpath', get_included_files() );

		foreach ( array_merge( glob( CPTDP_PATH . 'includes/post-types/*.php' ), glob( CPTDP_PATH . 'includes/settings/*.php' ) ) as $file ) {
			if ( 'index.php' === basename( $file ) ) {
				continue;
			}

			$this->assertContains( realpath( $file ), $included, basename( $file ) . ' not loaded' );
		}
	}

	public function test_loading_modules_twice_is_safe(): void {
		CPTDP_Loader::load_modules();

		$this->assertTrue( function_exists( 'cptdp_register_video' ) );
	}

	public function test_loader_is_hooked_on_plugins_loaded(): void {
		$this->assertNotFalse( has_action( 'plugins_loaded', array( 'CPTDP_Loader', 'load_modules' ) ) );
	}

	public function test_registration_runs_on_init(): void {
		$this->assertNotFalse( has_action( 'init', 'cptdp_register_post_types' ) );
	}

	public function test_registration_hook_fires_module_callbacks(): void {
		$callbacks = array(
			'cptdp_register_service',
			'cptdp_register_statistic',
			'cptdp_register_software_tool',
			'cptdp_register_video',
			'cptdp_register_document',
			'cptdp_register_collateral',
			'cptdp_register_video_category',
			'cptdp_register_document_category',
			'cptdp_register_collateral_category',
			'cptdp_register_aspect_ratio',
		);

		foreach ( $callbacks as $callback ) {
			$this->assertNotFalse( has_action( 'cptdp_register_post_types', $callback ), $callback );
		}
	}

	public function test_every_expected_type_and_taxonomy_exists(): void {
		foreach ( array( 'service', 'statistic', 'software_tool', 'video', 'document', 'collateral' ) as $post_type ) {
			$this->assertTrue( post_type_exists( $post_type ), $post_type );
		}

		foreach ( array( 'video_category', 'aspect_ratio', 'document_category', 'collateral_category' ) as $taxonomy ) {
			$this->assertTrue( taxonomy_exists( $taxonomy ), $taxonomy );
		}
	}

	public function test_activation_hooks_are_registered(): void {
		$basename = plugin_basename( CPTDP_PATH . 'custom-post-types-dp.php' );

		$this->assertNotFalse( has_action( 'activate_' . $basename, 'cptdp_activate' ) );
		$this->assertNotFalse( has_action( 'deactivate_' . $basename, 'flush_rewrite_rules' ) );
	}

	public function test_activation_registers_types_and_flushes_rewrite_rules(): void {
		$this->set_permalink_structure( '/%postname%/' );
		delete_option( 'rewrite_rules' );

		cptdp_activate();

		$rules = get_option( 'rewrite_rules' );

		$this->assertIsArray( $rules );
		foreach ( array( 'services', 'statistics', 'software-tools', 'videos', 'documents', 'collaterals' ) as $slug ) {
			$this->assertArrayHasKey( $slug . '/?$', $rules, $slug );
		}
		$this->assertArrayHasKey( 'video-category/([^/]+)/?$', $rules );
	}

	public function test_uninstall_deletes_contact_option(): void {
		update_option( 'cptdp_contact', array( 'email' => 'me@example.com' ) );

		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			define( 'WP_UNINSTALL_PLUGIN', 'custom-post-types-dp/custom-post-types-dp.php' );
		}

		include CPTDP_PATH . 'uninstall.php';

		$this->assertFalse( get_option( 'cptdp_contact' ) );
	}
}
