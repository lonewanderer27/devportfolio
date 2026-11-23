<?php
/**
 * Tests for the Home template (DPH-006 and the order of the Home sections).
 *
 * @package devportfolio
 */

/**
 * @group theme
 */
class Devportfolio_Test_Home_Template extends WP_UnitTestCase {

	private const INTRO = 'I help businesses grow their online presence through strategic content, marketing support and organized admin assistance, so you can focus on running your business.';

	private function theme_dir(): string {
		return dirname( __DIR__ );
	}

	private function template(): string {
		$file = $this->theme_dir() . '/templates/front-page.html';
		$this->assertFileExists( $file );

		return (string) file_get_contents( $file );
	}

	public function test_sections_are_in_order(): void {
		$tpl = $this->template();

		$needles = array(
			'<!-- wp:template-part {"slug":"header"',
			'<!-- wp:devportfolio/stats-strip /-->',
			'id="services"',
			'>What I do</h2>',
			'<p class="has-mid-color',
			'home-section__rule',
			'<!-- wp:devportfolio/service-list /-->',
			'<!-- wp:template-part {"slug":"footer"',
		);

		$last = -1;
		foreach ( $needles as $needle ) {
			$pos = strpos( $tpl, $needle );
			$this->assertNotFalse( $pos, "{$needle} missing." );
			$this->assertGreaterThan( $last, $pos, "{$needle} is out of order." );
			$last = $pos;
		}

		$this->assertStringContainsString( 'class="wp-block-group home-section"', $tpl );
	}

	public function test_home_uses_the_shared_page_shell_for_its_gutters(): void {
		// Same width and side padding as the Documents, Videos and Collaterals pages (page.css).
		$this->assertStringContainsString( '<main class="wp-block-group documents-page home-page"', $this->template() );
	}

	public function test_the_intro_sentence_is_exact_and_appears_only_here(): void {
		$this->assertSame( 1, substr_count( $this->template(), self::INTRO ) );

		$files = array_merge(
			(array) glob( $this->theme_dir() . '/templates/*.html' ),
			(array) glob( $this->theme_dir() . '/parts/*.html' )
		);

		$count = 0;
		foreach ( $files as $file ) {
			$count += substr_count( (string) file_get_contents( $file ), 'I help businesses grow' );
		}

		$this->assertSame( 1, $count, 'The sentence must not be repeated in the hero or anywhere else.' );
	}

	public function test_testimonials_and_tools_follow_selected_work(): void {
		$tpl     = $this->template();
		$needles = array(
			'id="selected-work"',
			'<!-- wp:devportfolio/video-modal /-->',
			'<!-- wp:devportfolio/testimonials /-->',
			'id="software-tools"',
			'>Software tools</h2>',
			'<!-- wp:devportfolio/software-tools /-->',
			'<!-- wp:template-part {"slug":"footer"',
		);

		$last = -1;
		foreach ( $needles as $needle ) {
			$pos = strpos( $tpl, $needle );
			$this->assertNotFalse( $pos, "{$needle} missing." );
			$this->assertGreaterThan( $last, $pos, "{$needle} is out of order." );
			$last = $pos;
		}
	}
}
