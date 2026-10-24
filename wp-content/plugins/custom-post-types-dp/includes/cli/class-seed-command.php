<?php
/**
 * `wp cptdp seed` command.
 *
 * @package CustomPostTypesDP
 */

defined( 'ABSPATH' ) || exit;

/**
 * Seed content from the CSVs in data/seeders/.
 */
class CPTDP_Seed_Command {

	/**
	 * Run registered seeders, or list them.
	 *
	 * Seeders are idempotent: an item whose title already exists (any status,
	 * Trash included) is skipped, never modified.
	 *
	 * ## OPTIONS
	 *
	 * [<name>]
	 * : Seeder to run, or `list` to show the registered seeders.
	 *
	 * [--all]
	 * : Run every registered seeder, in registration order.
	 *
	 * [--dry-run]
	 * : Report what would be created and write nothing.
	 *
	 * ## EXAMPLES
	 *
	 *     wp cptdp seed list
	 *     wp cptdp seed software-tools
	 *     wp cptdp seed --all --dry-run
	 *
	 * @param string[]             $args       Positional args.
	 * @param array<string, mixed> $assoc_args Flags.
	 */
	public function __invoke( array $args, array $assoc_args ): void {
		$seeders = cptdp_get_seeders();
		$name    = $args[0] ?? '';
		$all     = (bool) WP_CLI\Utils\get_flag_value( $assoc_args, 'all', false );
		$dry_run = (bool) WP_CLI\Utils\get_flag_value( $assoc_args, 'dry-run', false );

		if ( 'list' === $name ) {
			$rows = array();
			foreach ( $seeders as $key => $seeder ) {
				$rows[] = array(
					'name'  => $key,
					'label' => $seeder['label'],
					'csv'   => basename( $seeder['csv'] ),
				);
			}
			WP_CLI\Utils\format_items( 'table', $rows, array( 'name', 'label', 'csv' ) );
			return;
		}

		if ( $all ) {
			$names = array_keys( $seeders );
		} elseif ( '' === $name ) {
			WP_CLI::error( 'Name a seeder, or pass --all. See: wp cptdp seed list' );
		} else {
			$names = array( $name );
		}

		$created = 0;
		$skipped = 0;

		foreach ( $names as $seeder_name ) {
			$result = cptdp_run_seeder( $seeder_name, $dry_run );

			if ( is_wp_error( $result ) ) {
				WP_CLI::error( "{$seeder_name}: " . $result->get_error_message() );
			}

			foreach ( $result['created'] as $title ) {
				WP_CLI::log( sprintf( '%s %s: %s', $dry_run ? 'would create' : 'created     ', $seeder_name, $title ) );
			}
			foreach ( $result['skipped'] as $title ) {
				WP_CLI::log( sprintf( 'skipped      %s: %s', $seeder_name, $title ) );
			}

			$created += count( $result['created'] );
			$skipped += count( $result['skipped'] );
		}

		WP_CLI::success(
			sprintf(
				'%1$d %2$s, %3$d skipped%4$s',
				$created,
				$dry_run ? 'would be created' : 'created',
				$skipped,
				$dry_run ? ' (dry run, nothing written)' : ''
			)
		);
	}
}

WP_CLI::add_command( 'cptdp seed', 'CPTDP_Seed_Command' );
