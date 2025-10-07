<?php

namespace WP_CLI\ValidateReadme;

/**
 * WordPress.org Plugin Readme Parser.
 *
 * Based on the original code from WordPress.org, which in turn is
 * based on Ryan McCue's parser.
 *
 * @link https://meta.svn.wordpress.org/sites/trunk/wordpress.org/public_html/wp-content/plugins/plugin-directory/readme/class-parser.php?rev=11807
 * @link https://github.com/rmccue/WordPress-Readme-Parser
 */
class Parser {


	/**
	 * @var string
	 */
	public $name = '';

	/**
	 * @var array
	 */
	public $tags = array();

	/**
	 * @var string
	 */
	public $requires = '';

	/**
	 * @var string
	 */
	public $tested = '';

	/**
	 * @var string
	 */
	public $requires_php = '';

	/**
	 * @var array
	 */
	public $contributors = array();

	/**
	 * @var string
	 */
	public $stable_tag = '';

	/**
	 * @var string
	 */
	public $donate_link = '';

	/**
	 * @var string
	 */
	public $short_description = '';

	/**
	 * @var int
	 */
	public $short_description_length = 0;

	/**
	 * @var string
	 */
	public $license = '';

	/**
	 * @var string
	 */
	public $license_uri = '';

	/**
	 * @var array
	 */
	public $sections = array();

	/**
	 * @var array
	 */
	public $upgrade_notice = array();

	/**
	 * @var array
	 */
	public $screenshots = array();

	/**
	 * @var array
	 */
	public $faq = array();

	/**
	 * Warning flags which indicate specific parsing failures have occurred.
	 *
	 * @var array
	 */
	public $warnings = array();

	/**
	 * These are the readme sections that we expect.
	 *
	 * @var array
	 */
	private $expected_sections = array(
		'description',
		'installation',
		'faq',
		'screenshots',
		'changelog',
		'upgrade_notice',
		'other_notes',
	);

	/**
	 * We alias these sections, from => to
	 *
	 * @var array
	 */
	private $alias_sections = array(
		'frequently_asked_questions' => 'faq',
		'change_log'                 => 'changelog',
		'screenshot'                 => 'screenshots',
	);

	/**
	 * These are the valid header mappings for the header.
	 *
	 * @var array
	 */
	private $valid_headers = array(
		'tested'            => 'tested',
		'tested up to'      => 'tested',
		'requires'          => 'requires',
		'requires at least' => 'requires',
		'requires php'      => 'requires_php',
		'tags'              => 'tags',
		'contributors'      => 'contributors',
		'donate link'       => 'donate_link',
		'stable tag'        => 'stable_tag',
		'license'           => 'license',
		'license uri'       => 'license_uri',
	);

	/**
	 * These plugin tags are ignored.
	 *
	 * @var array
	 */
	private $ignore_tags = array(
		'plugin',
		'wordpress',
	);

	/**
	 * Parser constructor.
	 *
	 * @param string $readme A Filepath, URL, or contents of a readme to parse.
	 *
	 *                       Note: data:text/plain streams are URLs and need to pass through
	 *                       the parse_readme() function, not the parse_readme_contents() function, so
	 *                       that they can be turned from a URL into plain text via the stream.
	 */
	public function __construct( $readme ) {
		if ( preg_match( '!^https?://!i', $readme )
			|| preg_match( '!^data:text/plain!i', $readme )
			|| (
			// If it's longer than the Filesystem path limit or contains newlines, it's not worth a file_exists() check.
			strlen( $readme ) <= PHP_MAXPATHLEN
			&& false === strpos( $readme, "\n" )
			&& file_exists( $readme ) )
		) {
			$this->parse_readme( $readme );
		} elseif ( $readme ) {
			$this->parse_readme_contents( $readme );
		}
	}

	/**
	 * @param  string $file_or_url
	 * @return bool
	 */
	protected function parse_readme( $file_or_url ) {
		$context = stream_context_create(
			array(
				'http' => array(
					'user_agent' => 'WordPress.org Plugin Readme Parser',
				),
			)
		);

		$contents = file_get_contents( $file_or_url, false, $context );

		return $this->parse_readme_contents( $contents );
	}

	/**
	 * @param  string $content The contents of the readme to parse.
	 * @return bool
	 */
	protected function parse_readme_contents( $content ) {
		if ( preg_match( '!!u', $content ) ) {
			$contents = preg_split( '!\R!u', $content );
		} else {
			$contents = preg_split( '!\R!', $content ); // regex failed due to invalid UTF8 in $contents, see #2298
		}
		$contents = array_map( array( $this, 'strip_newlines' ), $contents );

		// Strip UTF8 BOM if present.
		if ( 0 === strpos( $contents[0], "\xEF\xBB\xBF" ) ) {
			$contents[0] = substr( $contents[0], 3 );
		}

		// Convert UTF-16 files.
		if ( 0 === strpos( $contents[0], "\xFF\xFE" ) ) {
			foreach ( $contents as $i => $line ) {
				$contents[ $i ] = mb_convert_encoding( $line, 'UTF-8', 'UTF-16' );
			}
		}

		$line       = $this->get_first_nonwhitespace( $contents );
		$this->name = $this->sanitize_text( trim( $line, "#= \t\0\x0B" ) );

		// Strip Github style header\n==== underlines.
		if ( ! empty( $contents ) && '' === trim( $contents[0], '=-' ) ) {
			array_shift( $contents );
		}

		// Handle readme's which do `=== Plugin Name ===\nMy SuperAwesomePlugin Name\n...`
		if ( 'plugin name' === strtolower( $this->name ) ) {
			$this->name = $this->get_first_nonwhitespace( $contents );
			$line       = $this->name;

			// Ensure that the line read wasn't an actual header or description.
			if ( strlen( $line ) > 50 || preg_match( '~^(' . implode( '|', array_keys( $this->valid_headers ) ) . ')\s*:~i', $line ) ) {
				$this->name = false;
				array_unshift( $contents, $line );
			}
		}

		// Parse headers.
		$headers = array();

		$line = $this->get_first_nonwhitespace( $contents );
		do {
			$value = null;
			if ( false === strpos( $line, ':' ) ) {

				// Some plugins have line-breaks within the headers.
				if ( empty( $line ) ) {
					break;
				}

				continue;
			}

			list( $key, $value ) = explode( ':', trim( $line ), 2 );
			$key                 = strtolower( trim( $key, " \t*-\r\n" ) );
			if ( isset( $this->valid_headers[ $key ] ) ) {
				$headers[ $this->valid_headers[ $key ] ] = trim( $value );
			}
		} while ( ( $line = array_shift( $contents ) ) !== null ); // phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition
		array_unshift( $contents, $line );

		if ( ! empty( $headers['tags'] ) ) {
			$this->tags = explode( ',', $headers['tags'] );
			$this->tags = array_map( 'trim', $this->tags );
			$this->tags = array_filter( $this->tags );
			$this->tags = array_diff( $this->tags, $this->ignore_tags );
			$this->tags = array_slice( $this->tags, 0, 5 );
		}
		if ( ! empty( $headers['requires'] ) ) {
			$this->requires = $this->sanitize_requires_version( $headers['requires'] );
		}
		if ( ! empty( $headers['tested'] ) ) {
			$this->tested = $this->sanitize_tested_version( $headers['tested'] );
		}
		if ( ! empty( $headers['requires_php'] ) ) {
			$this->requires_php = $this->sanitize_requires_php( $headers['requires_php'] );
		}
		if ( ! empty( $headers['contributors'] ) ) {
			$this->contributors = explode( ',', $headers['contributors'] );
			$this->contributors = array_map( 'trim', $this->contributors );
		}
		if ( ! empty( $headers['stable_tag'] ) ) {
			$this->stable_tag = $this->sanitize_stable_tag( $headers['stable_tag'] );
		}
		if ( ! empty( $headers['donate_link'] ) ) {
			$this->donate_link = $headers['donate_link'];
		}
		if ( ! empty( $headers['license'] ) ) {
			// Handle the many cases of "License: GPLv2 - http://..."
			if ( empty( $headers['license_uri'] ) && preg_match( '!(https?://\S+)!i', $headers['license'], $url ) ) {
				$headers['license_uri'] = $url[1];
				$headers['license']     = trim( str_replace( $url[1], '', $headers['license'] ), " -*\t\n\r" );
			}
			$this->license = $headers['license'];
		}
		if ( ! empty( $headers['license_uri'] ) ) {
			$this->license_uri = $headers['license_uri'];
		}

		// Parse the short description.
		while ( ( $line = array_shift( $contents ) ) !== null ) {  // phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition
			$trimmed = trim( $line );
			if ( empty( $trimmed ) ) {
				$this->short_description .= "\n";
				continue;
			}
			if ( ( '=' === $trimmed[0] && isset( $trimmed[1] ) && '=' === $trimmed[1] )
				|| ( '#' === $trimmed[0] && isset( $trimmed[1] ) && '#' === $trimmed[1] )
			) {

				// Stop after any Markdown heading.
				array_unshift( $contents, $line );
				break;
			}

			$this->short_description .= $line . "\n";
		}
		$this->short_description = trim( $this->short_description );

		/*
		* Parse the rest of the body.
		* Pre-fill the sections, we'll filter out empty sections later.
		*/
		$this->sections = array_fill_keys( $this->expected_sections, '' );
		$current        = '';
		$section_name   = '';
		while ( ( $line = array_shift( $contents ) ) !== null ) {  // phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition
			$trimmed = trim( $line );
			if ( empty( $trimmed ) ) {
				$current .= "\n";
				continue;
			}

			// Stop only after a ## Markdown header, not a ###.
			if ( ( '=' === $trimmed[0] && isset( $trimmed[1] ) && '=' === $trimmed[1] )
				|| ( '#' === $trimmed[0] && isset( $trimmed[1] ) && '#' === $trimmed[1] && isset( $trimmed[2] ) && '#' !== $trimmed[2] )
			) {

				if ( ! empty( $section_name ) ) {
					$this->sections[ $section_name ] .= trim( $current );
				}

				$current       = '';
				$section_title = trim( $line, "#= \t" );
				$section_name  = strtolower( str_replace( ' ', '_', $section_title ) );

				if ( isset( $this->alias_sections[ $section_name ] ) ) {
					$section_name = $this->alias_sections[ $section_name ];
				}

				// If we encounter an unknown section header, include the provided Title, we'll filter it to other_notes later.
				if ( ! in_array( $section_name, $this->expected_sections, true ) ) {
					$current     .= '<h3>' . $section_title . '</h3>';
					$section_name = 'other_notes';
				}
				continue;
			}

			$current .= $line . "\n";
		}

		if ( ! empty( $section_name ) ) {
			$this->sections[ $section_name ] .= trim( $current );
		}

		// Filter out any empty sections.
		$this->sections = array_filter( $this->sections );

		// Use the short description for the description section if not provided.
		if ( empty( $this->sections['description'] ) ) {
			$this->sections['description'] = $this->short_description;
		}

		// Suffix the Other Notes section to the description.
		if ( ! empty( $this->sections['other_notes'] ) ) {
			$this->sections['description'] .= "\n" . $this->sections['other_notes'];
			unset( $this->sections['other_notes'] );
		}

		// Parse out the Upgrade Notice section into it's own data.
		if ( isset( $this->sections['upgrade_notice'] ) ) {
			$this->upgrade_notice = $this->parse_section( $this->sections['upgrade_notice'] );
			$this->upgrade_notice = array_map( array( $this, 'sanitize_text' ), $this->upgrade_notice );
			unset( $this->sections['upgrade_notice'] );
		}

		// Display FAQs as a definition list.
		if ( isset( $this->sections['faq'] ) ) {
			$this->faq             = $this->parse_section( $this->sections['faq'] );
			$this->sections['faq'] = '';
		}

		// Markdownify!
		$this->sections       = array_map( array( $this, 'parse_markdown' ), $this->sections );
		$this->upgrade_notice = array_map( array( $this, 'parse_markdown' ), $this->upgrade_notice );
		$this->faq            = array_map( array( $this, 'parse_markdown' ), $this->faq );

		// Use the first line of the description for the short description if not provided.
		if ( ! $this->short_description && ! empty( $this->sections['description'] ) ) {
			$this->short_description = array_filter( explode( "\n", $this->sections['description'] ) )[0];
		}

		// Sanitize and trim the short_description to match requirements.
		$this->short_description = $this->sanitize_text( $this->short_description );
		$this->short_description = $this->parse_markdown( $this->short_description );
		$this->short_description = trim( strip_tags( $this->short_description ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags

		$this->short_description_length = strlen( $this->short_description );

		$this->short_description = $this->trim_length( $this->short_description );

		if ( isset( $this->sections['screenshots'] ) ) {
			preg_match_all( '#<li>(.*?)</li>#is', $this->sections['screenshots'], $screenshots, PREG_SET_ORDER );
			if ( $screenshots ) {
				$i = 1; // Screenshots start from 1.
				foreach ( $screenshots as $ss ) {
					$this->screenshots[ $i++ ] = $this->filter_text( $ss[1] );
				}
			}
			unset( $this->sections['screenshots'] );
		}

		if ( ! empty( $this->faq ) ) {
			// If the FAQ contained data we couldn't parse, we'll treat it as freeform and display it before any questions which are found.
			if ( isset( $this->faq[''] ) ) {
				$this->sections['faq'] .= $this->faq[''];
				unset( $this->faq[''] );
			}

			if ( $this->faq ) {
				$this->sections['faq'] .= "\n<dl>\n";
				foreach ( $this->faq as $question => $answer ) {
					$question_slug          = rawurlencode( strtolower( trim( $question ) ) );
					$this->sections['faq'] .= "<dt id='$question_slug'><h3>{$question}</h3></dt>\n<dd>{$answer}</dd>\n";
				}
				$this->sections['faq'] .= "\n</dl>\n";
			}
		}

		// Filter the HTML.
		$this->sections = array_map( array( $this, 'filter_text' ), $this->sections );

		return true;
	}

	/**
	 * @access protected
	 *
	 * @param  string[] $contents
	 * @return string
	 */
	protected function get_first_nonwhitespace( &$contents ) {
		while ( ( $line = array_shift( $contents ) ) !== null ) {  // phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition
			$trimmed = trim( $line );
			if ( ! empty( $trimmed ) ) {
				break;
			}
		}

		return $line;
	}

	/**
	 * @access protected
	 *
	 * @param  string $line
	 * @return string
	 */
	protected function strip_newlines( $line ) {
		return rtrim( $line, "\r\n" );
	}

	/**
	 * @access protected
	 *
	 * @param  string $desc
	 * @param  int    $length
	 * @return string
	 */
	protected function trim_length( $desc, $length = 150 ) {
		// Apply the length restriction without counting html entities.
		$str_length = mb_strlen( html_entity_decode( $desc, \ENT_QUOTES | \ENT_SUBSTITUTE ) ?: $desc );

		if ( $str_length > $length ) {
			$desc = mb_substr( $desc, 0, $length );

			// If not a full sentence...
			if ( '.' !== mb_substr( $desc, -1 ) ) {
				// ..and one ends within 20% of the end, trim it to that.
				$pos = mb_strrpos( $desc, '.' );
				if ( $pos > ( 0.8 * $length ) ) {
					$desc = mb_substr( $desc, 0, $pos + 1 );
				} else {
					// ..else mark it as being trimmed.
					$desc .= ' &hellip;';
				}
			}
		}

		return trim( $desc );
	}

	/**
	 * @access protected
	 *
	 * @param  string $text
	 * @return string
	 */
	protected function filter_text( $text ) {
		$text = trim( $text );

		// wpautop() will eventually replace all \n's with <br>s, and that isn't what we want (The text may be line-wrapped in the readme, we don't want that, we want paragraph-wrapped text)
		// TODO: This incorrectly also applies within `<code>` tags which we don't want either.
		// $text = preg_replace( "/(?<![> ])\n/", ' ', $text );
		return trim( $text );
	}

	/**
	 * @access protected
	 *
	 * @param  string $text
	 * @return string
	 */
	protected function sanitize_text( $text ) {
		// not fancy
		return trim( strip_tags( $text ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags
	}

	/**
	 * Sanitize the provided stable tag to something we expect.
	 *
	 * @param  string $stable_tag the raw Stable Tag line from the readme.
	 * @return string The sanitized $stable_tag.
	 */
	protected function sanitize_stable_tag( $stable_tag ) {
		$stable_tag = trim( $stable_tag );
		$stable_tag = trim( $stable_tag, '"\'' ); // "trunk"
		$stable_tag = preg_replace( '!^/?tags/!i', '', $stable_tag ); // "tags/1.2.3"
		$stable_tag = preg_replace( '![^a-z0-9_.-]!i', '', $stable_tag );

		// If the stable_tag begins with a ., we treat it as 0.blah.
		if ( 0 === strpos( $stable_tag, '.' ) ) {
			$stable_tag = "0{$stable_tag}";
		}

		return $stable_tag;
	}

	/**
	 * Sanitizes the Requires PHP header to ensure that it's a valid version header.
	 *
	 * @param  string $version
	 * @return string The sanitized $version
	 */
	protected function sanitize_requires_php( $version ) {
		$version = trim( $version );

		// x.y or x.y.z
		if ( $version && ! preg_match( '!^\d+(\.\d+){1,2}$!', $version ) ) {
			$this->warnings['requires_php_header_ignored'] = true;
			// Ignore the readme value.
			$version = '';
		}

		return $version;
	}

	/**
	 * Sanitizes the Tested header to ensure that it's a valid version header.
	 *
	 * @param  string $version
	 * @return string The sanitized $version
	 */
	protected function sanitize_tested_version( $version ) {
		$version = trim( $version );

		if ( $version ) {

			// Handle the edge-case of 'WordPress 5.0' and 'WP 5.0' for historical purposes.
			$strip_phrases = [
				'WordPress',
				'WP',
			];
			$version       = trim( str_ireplace( $strip_phrases, '', $version ) );

			// Strip off any -alpha, -RC, -beta suffixes, as these complicate comparisons and are rarely used.
			list( $version, ) = explode( '-', $version );

			if ( // x.y or x.y.z
				! preg_match( '!^\d+\.\d(\.\d+)?$!', $version )
				// Allow plugins to mark themselves as compatible with Stable+0.1 (trunk/master) but not higher
				|| ( defined( 'WP_CORE_STABLE_BRANCH' )
				&& version_compare( (float) $version, (float) WP_CORE_STABLE_BRANCH + 0.1, '>' ) )
			) {
				$this->warnings['tested_header_ignored'] = true;
				// Ignore the readme value.
				$version = '';
			}
		}

		return $version;
	}

	/**
	 * Sanitizes the Requires at least header to ensure that it's a valid version header.
	 *
	 * @param  string $version
	 * @return string The sanitized $version
	 */
	protected function sanitize_requires_version( $version ) {
		$version = trim( $version );

		if ( $version ) {

			// Handle the edge-case of 'WordPress 5.0' and 'WP 5.0' for historical purposes.
			$strip_phrases = [
				'WordPress',
				'WP',
				'or higher',
				'and above',
				'+',
			];
			$version       = trim( str_ireplace( $strip_phrases, '', $version ) );

			// Strip off any -alpha, -RC, -beta suffixes, as these complicate comparisons and are rarely used.
			list( $version, ) = explode( '-', $version );

			// TODO: Allow plugins to mark themselves as requiring Stable+0.1 (trunk/master) but not higher
			// Don't have WP_CORE_STABLE_BRANCH here, so would need to grab latest version from API.
			if ( // x.y or x.y.z
				! preg_match( '!^\d+\.\d(\.\d+)?$!', $version )
			) {
				$this->warnings['requires_header_ignored'] = true;
				// Ignore the readme value.
				$version = '';
			}
		}

		return $version;
	}

	/**
	 * Parses a slice of lines from the file into an array of Heading => Content.
	 *
	 * We assume that every heading encountered is a new item, and not a sub heading.
	 * We support headings which are either `= Heading`, `# Heading` or `** Heading`.
	 *
	 * @param  string|array $lines The lines of the section to parse.
	 * @return array
	 */
	protected function parse_section( $lines ) {
		$key    = '';
		$value  = '';
		$return = array();

		if ( ! is_array( $lines ) ) {
			$lines = explode( "\n", $lines );
		}
		$trimmed_lines = array_map( 'trim', $lines );

		/*
		* The heading style being matched in the block. Can be 'heading' or 'bold'.
		* Standard Markdown headings (## .. and == ... ==) are used, but if none are present.
		* full line bolding will be used as a heading style.
		*/
		$heading_style = 'bold';
		foreach ( $trimmed_lines as $trimmed ) {
			if ( $trimmed && ( '#' === $trimmed[0] || '=' === $trimmed[0] ) ) {
				$heading_style = 'heading';
				break;
			}
		}

		$line_count = count( $lines );
		for ( $i = 0; $i < $line_count; $i++ ) {
			$line    = &$lines[ $i ];
			$trimmed = &$trimmed_lines[ $i ];
			if ( ! $trimmed ) {
				$value .= "\n";
				continue;
			}

			$is_heading = false;
			if ( 'heading' === $heading_style && ( '#' === $trimmed[0] || '=' === $trimmed[0] ) ) {
				$is_heading = true;
			} elseif ( 'bold' === $heading_style && ( 0 === strpos( $trimmed, '**' ) && substr( $trimmed, -2 ) === '**' ) ) {
				$is_heading = true;
			}

			if ( $is_heading ) {
				if ( $value ) {
					$return[ $key ] = trim( $value );
				}

				$value = '';
				// Trim off the first character of the line, as we know that's the heading style we're expecting to remove.
				$key = trim( $line, $trimmed[0] . " \t" );
				continue;
			}

			$value .= $line . "\n";
		}

		if ( $key || $value ) {
			$return[ $key ] = trim( $value );
		}

		return $return;
	}

	/**
	 * @param  string $text
	 * @return string
	 */
	protected function parse_markdown( $text ) {
		static $markdown = null;

		if ( is_null( $markdown ) ) {
			$markdown = new Markdown();
		}

		return $markdown->transform( $text );
	}
}
