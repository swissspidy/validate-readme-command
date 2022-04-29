<?php

namespace WP_CLI\ValidateReadme;

/**
 * Helper class to validate readme files.
 *
 * Based on the original code from WordPress.org.
 *
 * @link https://meta.svn.wordpress.org/sites/trunk/wordpress.org/public_html/wp-content/plugins/plugin-directory/readme/class-validator.php?rev=11807
 */
class Validator {

	/**
	 * Validates readme contents by string.
	 *
	 * @param  string $content The text of the readme.
	 * @return array Array of the readme validation results.
	 */
	public function validate_content( $content ) {

		// Security note: Keep the data: protocol here, Parser accepts a string HOWEVER
		// if a submitted readme.txt URL contents were to contain a file or URL-like string,
		// it could bypass the protections above in validate_url().
		$readme = new Parser( 'data:text/plain,' . rawurlencode( $content ) );

		$errors   = array();
		$warnings = array();
		$notes    = array();

		// Fatal errors.
		if ( empty( $readme->name ) ) {
			$errors[] = sprintf(
			/* translators: 1: 'Plugin Name' section title, 2: 'Plugin Name' */
				'We cannot find a plugin name in your readme. Plugin names look like: %1$s. Please change %2$s to reflect the actual name of your plugin.',
				'`=== Plugin Name ===`',
				'`Plugin Name`'
			);
		}

		// Warnings.
		if ( isset( $readme->warnings['requires_header_ignored'] ) ) {
			$latest_wordpress_version = '5.0';

			$warnings[] = sprintf(
				'The %1$s field was ignored. This field should only contain a valid WordPress version such as %2$s or %3$s.',
				'`Requires at least`',
				'`' . number_format( $latest_wordpress_version, 1 ) . '`',
				'`' . number_format( $latest_wordpress_version - 0.1, 1 ) . '`'
			);
		}

		if ( isset( $readme->warnings['tested_header_ignored'] ) ) {
			$latest_wordpress_version = defined( 'WP_CORE_STABLE_BRANCH' ) ? WP_CORE_STABLE_BRANCH : '5.0';

			$warnings[] = sprintf(
				'The %1$s field was ignored. This field should only contain a valid WordPress version such as %2$s or %3$s.',
				'`Tested up to`',
				'`' . number_format( $latest_wordpress_version, 1 ) . '`',
				'`' . number_format( $latest_wordpress_version + 0.1, 1 ) . '`'
			);
		} elseif ( empty( $readme->tested ) ) {
			$warnings[] = sprintf(
				'The %s field is missing.',
				'`Tested up to`'
			);
		}

		if ( isset( $readme->warnings['requires_php_header_ignored'] ) ) {
			$warnings[] = sprintf(
				'The %1$s field was ignored. This field should only contain a PHP version such as %2$s or %3$s.',
				'`Requires PHP`',
				'`5.2.4`',
				'`7.0`'
			);
		}

		if ( empty( $readme->stable_tag ) ) {
			$warnings[] = sprintf(
				'The %1$s field is missing.  Hint: If you treat %2$s as stable, put %3$s.',
				'`Stable tag`',
				'`/trunk/`',
				'`Stable tag: trunk`'
			);
		}

		if ( isset( $readme->warnings['contributor_ignored'] ) ) {
			$warnings[] = sprintf(
				'One or more contributors listed were ignored. The %s field should only contain WordPress.org usernames. Remember that usernames are case-sensitive.',
				'`Contributors`'
			);
		} elseif ( ! count( $readme->contributors ) ) {
			$warnings[] = sprintf(
				'The %s field is missing.',
				'`Contributors`'
			);
		}

		if ( $readme->short_description_length > 150 ) {
			$warnings[] = 'The short description exceeds the limit of 150 characters';
		}

		// Notes.
		if ( empty( $readme->requires ) ) {
			$notes[] = sprintf(
				'The %s field is missing. It should be defined here, or in your main plugin file.',
				'`Requires at least`'
			);
		}

		foreach ( $readme->upgrade_notice as $version => $notice ) {
			if ( strlen( $notice ) > 150 ) {
				$warnings[] = sprintf(
					'The upgrade notice for "%s" exceeds the limit of 30 characters',
					$version
				);
			}
		}

		if ( empty( $readme->requires_php ) ) {
			$notes[] = sprintf(
				'The %s field is missing. It should be defined here, or in your main plugin file.',
				'`Requires PHP`'
			);
		}

		if ( empty( $readme->sections['faq'] ) ) {
			$notes[] = sprintf(
				'No %s section was found',
				'`== Frequently Asked Questions ==`'
			);
		}

		if ( empty( $readme->sections['changelog'] ) ) {
			$notes[] = sprintf(
				'No %s section was found',
				'`== Changelog ==`'
			);
		}

		if ( empty( $readme->upgrade_notice ) ) {
			$notes[] = sprintf(
				'No %s section was found',
				'`== Upgrade Notice ==`'
			);
		}

		if ( empty( $readme->screenshots ) ) {
			$notes[] = sprintf(
				'No %s section was found',
				'`== Screenshots ==`'
			);
		}

		if ( empty( $readme->donate_link ) ) {
			$notes[] = 'No donate link was found';
		}

		return compact( 'errors', 'warnings', 'notes' );

	}

}
