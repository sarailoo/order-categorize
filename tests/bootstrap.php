<?php
/**
 * PHPUnit bootstrap file.
 *
 * @package OrderCategorize\Tests
 */

declare(strict_types=1);

putenv( 'TESTS_PATH=' . __DIR__ ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_putenv
putenv( 'LIBRARY_PATH=' . dirname( __DIR__ ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_putenv
$vendor = dirname( dirname( __DIR__ ) ) . '/vendor/';
if ( ! realpath( $vendor ) ) {
	die( 'Please install via Composer before running tests.' );
}
if ( ! defined( 'PHPUNIT_COMPOSER_INSTALL' ) ) {
	define( 'PHPUNIT_COMPOSER_INSTALL', $vendor . 'autoload.php' );
}

require_once $vendor . '/antecedent/patchwork/Patchwork.php';
require_once $vendor . 'autoload.php';
unset( $vendor );
