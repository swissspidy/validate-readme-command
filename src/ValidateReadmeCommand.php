<?php

namespace WP_CLI\ValidateReadme;

use WP_CLI;
use WP_CLI_Command;

class ValidateReadmeCommand extends WP_CLI_Command {


	/**
	 * Greets the world.
	 *
	 * ## OPTIONS
	 *
	 * <readme>
	 * : Readme contents, path to a readme file, or readme URL.
	 *
	 * [--format=<format>]
	 * : Desired output format
	 * ---
	 * default: default
	 * options:
	 *   - default
	 *   - github-actions
	 *
	 * [--strict]
	 * : Whether to perform a strict check by treating all messages as errors.
	 *
	 * ## EXAMPLES
	 *
	 *     # Validate the Hello Dolly readme
	 *     $ wp plugin validate-readme \
	 *            https://plugins.svn.wordpress.org/hello-dolly/trunk/readme.txt
	 *     Success: Hello World!
	 *
	 * @when before_wp_load
	 *
	 * @param array $args       Indexed array of positional arguments.
	 * @param array $assoc_args Associative array of associative arguments.
	 */
	public function __invoke( $args, $assoc_args ) {
		$format = WP_CLI\Utils\get_flag_value( $assoc_args, 'format', 'default' );
		$strict = WP_CLI\Utils\get_flag_value( $assoc_args, 'strict', false );

		$gha_format = 'github-actions' === $format;
		$filename   = 'readme.txt';

		if ( empty( $args[0] ) ) {
			WP_CLI::error( 'No readme provided' );
		}

		if ( realpath( $args[0] ) ) {
			$filename       = realpath( $args[0] );
			$readme_content = file_get_contents( $filename );
		} elseif ( is_array( WP_CLI\Utils\parse_url( $args[0], -1, false ) )
			&& WP_CLI\Utils\parse_url( $args[0], PHP_URL_SCHEME, false )
		) {
			$response = WP_CLI\Utils\http_request( 'GET', $args[0] );
			if ( 0 !== strpos( $response->status_code, 20 ) ) {
				WP_CLI::error( 'Incorrect readme URL provided' );
			}

			$readme_content = $response->body;
		} else {
			$readme_content = $args[0];
		}

		if ( empty( $readme_content ) ) {
			WP_CLI::error( 'Incorrect readme provided' );
		}

		$result = ( new Validator() )->validate_content( $readme_content );

		if ( $strict ) {
			array_push( $result['errors'], ...$result['warnings'], ...$result['notes'] );
			$result['warnings'] = [];
			$result['notes']    = [];
		}

		foreach ( $result['errors'] as $error ) {
			if ( $gha_format ) {
				WP_CLI::line( "::error file=$filename::$error" );
			} else {
				WP_CLI::error( $error, false );
			}
		}

		foreach ( $result['warnings'] as $warning ) {
			if ( $gha_format ) {
				WP_CLI::line( "::warning file=$filename::$warning" );
			} else {
				WP_CLI::warning( $warning );
			}
		}

		foreach ( $result['notes'] as $note ) {
			if ( $gha_format ) {
				WP_CLI::line( "::notice file=$filename::$note" );
			} else {
				WP_CLI::warning( $note );
			}
		}

		if ( ! empty( $result['errors'] ) ) {
			WP_CLI::error( 'Readme validated with errors.' );
		} else {
			WP_CLI::success( 'Readme successfully validated.' );
		}
	}
}
