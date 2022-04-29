<?php

namespace WP_CLI\ValidateReadme;

use WP_CLI;

if ( ! class_exists( '\WP_CLI' ) ) {
	return;
}

$wpcli_validate_readme_autoloader = __DIR__ . '/vendor/autoload.php';

if ( file_exists( $wpcli_validate_readme_autoloader ) ) {
	require_once $wpcli_validate_readme_autoloader;
}

WP_CLI::add_command( 'plugin validate-readme', ValidateReadmeCommand::class );
