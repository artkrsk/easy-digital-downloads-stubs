#!/usr/bin/env php
<?php

require_once __DIR__ . '/vendor/autoload.php';

use StubsGenerator\{StubsGenerator, Finder};
use Dotenv\Dotenv;

// Helper function for colored output
function color( string $text, string $color ): string {
	$colors = array(
		'green'  => "\033[32m",
		'red'    => "\033[31m",
		'yellow' => "\033[33m",
		'reset'  => "\033[0m",
	);

	return ( $colors[ $color ] ?? '' ) . $text . $colors['reset'];
}

// Extract Version: header from a plugin main file. Returns null if the file is missing or the header can't be parsed.
function extractPluginVersion( string $mainFile ): ?string {
	if ( ! file_exists( $mainFile ) ) {
		return null;
	}

	$content = file_get_contents( $mainFile );

	if ( preg_match( '/^\s*\*\s*Version:\s*(.+)$/m', $content, $matches ) ) {
		return trim( $matches[1] );
	}

	return null;
}

// Resolve the main PHP file for a plugin directory. Some EDD plugins use
// {slug}/{slug}.php, others (e.g. edd-software-licensing) use a different file name.
function findPluginMainFile( string $pluginPath ): ?string {
	$candidates = array(
		$pluginPath . '/' . basename( $pluginPath ) . '.php',
		$pluginPath . '/edd-software-licensing.php',
		$pluginPath . '/edd-convertkit.php',
		$pluginPath . '/easy-digital-downloads.php',
	);

	foreach ( $candidates as $candidate ) {
		if ( file_exists( $candidate ) ) {
			return $candidate;
		}
	}

	// Fall back to scanning the directory for any PHP file with a "Plugin Name:" header.
	if ( is_dir( $pluginPath ) ) {
		foreach ( glob( $pluginPath . '/*.php' ) as $file ) {
			$content = file_get_contents( $file );
			if ( false !== strpos( $content, 'Plugin Name:' ) ) {
				return $file;
			}
		}
	}

	return null;
}

// Load .env configuration (optional - CI sets env vars directly)
$dotenv = Dotenv::createImmutable( __DIR__ );
$dotenv->safeLoad();

// Resolve paths from env (env vars beat .env)
function resolvePath( string $envVar ): ?string {
	$value = getenv( $envVar );
	if ( false === $value || '' === $value ) {
		$value = $_ENV[ $envVar ] ?? null;
	}
	return ( null === $value || '' === $value ) ? null : $value;
}

$eddPath           = resolvePath( 'EDD_PATH' );
$eddSlPath         = resolvePath( 'EDD_SL_PATH' );
$eddConvertkitPath = resolvePath( 'EDD_CONVERTKIT_PATH' );

if ( empty( $eddPath ) ) {
	echo color( "Error: EDD_PATH environment variable is required.\n", 'red' );
	echo "Please create .env file or set the environment variable.\n";
	echo "See .env.example for template.\n";
	exit( 1 );
}

if ( ! is_dir( $eddPath ) ) {
	echo color( "Error: Easy Digital Downloads source not found at $eddPath\n", 'red' );
	echo "Please update EDD_PATH in .env file.\n";
	exit( 1 );
}

echo color( "Generating stubs from: $eddPath\n", 'yellow' );

$includeSl         = $eddSlPath && is_dir( $eddSlPath );
$includeConvertkit = $eddConvertkitPath && is_dir( $eddConvertkitPath );

if ( $includeSl ) {
	echo color( "Including EDD Software Licensing from: $eddSlPath\n", 'yellow' );
} else {
	echo color( "EDD_SL_PATH not provided or not found, skipping EDD Software Licensing\n", 'yellow' );
}

if ( $includeConvertkit ) {
	echo color( "Including EDD ConvertKit from: $eddConvertkitPath\n", 'yellow' );
} else {
	echo color( "EDD_CONVERTKIT_PATH not provided or not found, skipping EDD ConvertKit\n", 'yellow' );
}

// 1. Generate stubs
$finder = Finder::create()
	->in( $eddPath )
	->exclude( array( 'vendor', 'tests', 'node_modules', 'build', 'assets', 'languages', 'libraries', 'samples', 'templates', 'Polyfills', 'views' ) )
	// EDD-SL bundles a copy of Parsedown directly in includes/ — skip it (markdown
	// renderer with PHP 8 deprecation warnings, irrelevant to the EDD type surface).
	->notName( 'Parsedown.php' )
	->sortByName();

if ( $includeSl ) {
	$finder->in( $eddSlPath );
}

if ( $includeConvertkit ) {
	$finder->in( $eddConvertkitPath );
}

$generator = new StubsGenerator( StubsGenerator::DEFAULT );
$result    = $generator->generate( $finder );
$content   = $result->prettyPrint();

// 2. Remove stray code statements (code outside functions/classes)
$content = removeStrayCodeStatements( $content );

// 2.5. Strip `abstract` from method declarations so concrete child classes that the
// stub generator emits without method bodies don't trigger PHP's "must implement"
// fatal at parse time. Signatures + return types are preserved so PHPStan still has
// type info.
$content = neutralizeAbstractMethods( $content );

// 3. Extract versions from source
$eddVersion           = extractPluginVersion( findPluginMainFile( $eddPath ) ?? '' );
$eddSlVersion         = $includeSl ? extractPluginVersion( findPluginMainFile( $eddSlPath ) ?? '' ) : null;
$eddConvertkitVersion = $includeConvertkit ? extractPluginVersion( findPluginMainFile( $eddConvertkitPath ) ?? '' ) : null;

if ( null === $eddVersion ) {
	echo color( "Error: Could not extract version from EDD main plugin file.\n", 'red' );
	exit( 1 );
}

// 4. Add self-contained constants with extracted versions
$content = addSelfContainedConstants( $content, $eddVersion, $eddSlVersion, $eddConvertkitVersion );

// 4.5. Add empty stubs for parent classes / interfaces / traits referenced from the
// stub file but missing from the scan (typically Doctrine\DBAL\* and other vendored
// libraries that EDD requires but we don't include in the Finder scope).
$content = fixMissingTypeStubs( $content );

// 5. Write final output
file_put_contents( __DIR__ . '/easy-digital-downloads-stubs.php', $content );

echo color( "✓ Stubs generated successfully\n", 'green' );
echo color( "  EDD: $eddVersion\n", 'green' );
if ( $eddSlVersion ) {
	echo color( "  EDD Software Licensing: $eddSlVersion\n", 'green' );
}
if ( $eddConvertkitVersion ) {
	echo color( "  EDD ConvertKit: $eddConvertkitVersion\n", 'green' );
}

// ---------------------------------------------------------------------------
// Helper functions
// ---------------------------------------------------------------------------

/**
 * Remove stray code statements that appear in namespace blocks outside of
 * class/function definitions. StubsGenerator occasionally includes these.
 */
function removeStrayCodeStatements( string $content ): string {
	$lines  = explode( "\n", $content );
	$output = array();

	foreach ( $lines as $line ) {
		// Skip stray code that uses $this outside object context.
		if ( preg_match( '/^\s*\$\w+\s*=.*\$this->/', $line ) ) {
			continue;
		}

		// Skip stray apply_filters calls at top level.
		if ( preg_match( '/^\s*\$\w+\s*=\s*apply_filters\(/', $line ) ) {
			continue;
		}

		// Skip standalone `define(...)` and `\define(...)` calls at namespace level —
		// these come from EDD's main plugin file and would conflict with our own
		// self-contained constants block (which uses defined() guards).
		if ( preg_match( '/^\s*\\\\?define\s*\(/', $line ) ) {
			continue;
		}

		// Skip stray variable assignments at namespace level — typically template-style
		// procedural code (metabox views, admin includes) that references variables only
		// defined by the including context.
		if ( preg_match( '/^\s{0,4}\$\w+\s*=/', $line ) ) {
			continue;
		}

		$output[] = $line;
	}

	$content = implode( "\n", $output );

	// Remove empty namespace blocks that only contain doc comments.
	$content = preg_replace(
		'/namespace\s+[\w\\\\]+\s*\{\s*\/\*\*[^*]*\*+(?:[^*\/][^*]*\*+)*\/\s*\}/s',
		'',
		$content
	);

	// Clean up triple+ blank lines.
	$content = preg_replace( '/\n{3,}/', "\n\n", $content );

	return $content;
}

/**
 * Iteratively detect classes / interfaces / traits the stub file references but doesn't
 * define (typically Doctrine\DBAL\* and other vendored deps not in our Finder scope),
 * and append empty stubs for them so the file can be `require_once`'d without
 * triggering "Class X not found" fatals.
 *
 * Adapted from arts/elementor-stubs's fixMissingParentStubs pattern.
 */
function fixMissingTypeStubs( string $content ): string {
	$max_passes = 30;

	for ( $pass = 0; $pass < $max_passes; $pass++ ) {
		$tmp = tempnam( sys_get_temp_dir(), 'edd_stubs_' );
		file_put_contents( $tmp, $content );

		$preload = array(
			// Compose autoloader first so it has a chance to resolve symbols PHP looks up
			// while parsing the stubs — matches what tests/bootstrap.php does.
			__DIR__ . '/vendor/autoload.php',
			__DIR__ . '/vendor/php-stubs/wordpress-stubs/wordpress-stubs.php',
			__DIR__ . '/vendor/php-stubs/wp-cli-stubs/wp-cli-stubs.php',
		);

		$requires = '';
		foreach ( $preload as $stub ) {
			if ( file_exists( $stub ) ) {
				$requires .= sprintf( 'require_once %s; ', var_export( $stub, true ) );
			}
		}
		$check_script = $requires . sprintf( 'require_once %s;', var_export( $tmp, true ) );

		$output_lines = array();
		exec(
			'php -d memory_limit=2G -r ' . escapeshellarg( $check_script ) . ' 2>&1',
			$output_lines,
			$exit_code
		);
		unlink( $tmp );

		$output = implode( "\n", $output_lines );

		// `php -r` exit code is unreliable when fatals occur inside require_once'd files
		// (PHP still reports 0). Detect failures from the output instead.
		$has_error = ( 0 !== $exit_code )
			|| ( false !== strpos( $output, 'Fatal error' ) )
			|| ( false !== strpos( $output, 'PHP Fatal' ) );

		if ( ! $has_error ) {
			break;
		}
		$changed = false;

		// Match: Class "X" not found, Interface "X" not found, Trait "X" not found.
		if ( preg_match_all( '/(Class|Interface|Trait) "([^"]+)" not found/', $output, $matches, PREG_SET_ORDER ) ) {
			$seen = array();
			foreach ( $matches as $match ) {
				$kind = strtolower( $match[1] );
				$fqcn = $match[2];
				$key  = $kind . ':' . $fqcn;
				if ( isset( $seen[ $key ] ) ) {
					continue;
				}
				$seen[ $key ] = true;

				$parts      = explode( '\\', ltrim( $fqcn, '\\' ) );
				$short_name = array_pop( $parts );
				$namespace  = implode( '\\', $parts );

				if ( '' === $namespace ) {
					// Wrap global-namespace stubs in `namespace { ... }` so they don't
					// collide with the braced-namespace syntax the rest of the stubs use
					// ("No code may exist outside of namespace {}" otherwise).
					$content .= "\nnamespace {\n\t{$kind} {$short_name} {}\n}\n";
				} else {
					$content .= "\nnamespace {$namespace} {\n\t{$kind} {$short_name} {}\n}\n";
				}
				$changed = true;
				echo color( "  → Added empty {$kind} stub for missing reference: {$fqcn}\n", 'yellow' );
			}
		}

		if ( ! $changed ) {
			// Can't auto-resolve further; surface the remaining error to the operator.
			echo color( "Warning: stub file still has parse errors after $pass passes:\n", 'yellow' );
			echo $output . "\n";
			break;
		}
	}

	return $content;
}

/**
 * Convert `abstract <visibility> function foo(...): T;` declarations into
 * `<visibility> function foo(...): T {}`. Preserves visibility, parameter list, and
 * return type so PHPStan's bootstrap of the stubs still sees a typed signature, but
 * removes the contract obligation PHP enforces at class-parse time.
 */
function neutralizeAbstractMethods( string $content ): string {
	return preg_replace_callback(
		'/abstract\s+((?:public|protected|private)(?:\s+static)?\s+function\s+\w+\s*\([^)]*\)(?:\s*:\s*[?\w\\\\|]+)?)\s*;/',
		function ( $matches ) {
			return $matches[1] . ' {}';
		},
		$content
	);
}

/**
 * Inject the EDD / EDD SL / EDD ConvertKit constants at the top of the stubs file so
 * consumers don't have to define them separately to satisfy `defined(...)` checks.
 */
function addSelfContainedConstants( string $content, string $eddVersion, ?string $eddSlVersion = null, ?string $eddConvertkitVersion = null ): string {
	// Strip ONLY empty top-level namespace blocks (the placeholders StubsGenerator
	// sometimes emits). Anything substantive in `namespace { ... }` — like EDD's huge
	// global-namespace block containing every `edd_*` function — must be preserved,
	// so we never run a greedy regex that could swallow it.
	$content = preg_replace( '/namespace \{\s*\}/', '', $content );
	$content = preg_replace( '/^<\?php.*?\n/s', "<?php\n\n", $content );

	$constants = <<<CONSTANTS
namespace {
	// Easy Digital Downloads constants
	if (!defined('EDD_VERSION')) {
		define('EDD_VERSION', '{$eddVersion}');
	}
	if (!defined('EDD_PLUGIN_FILE')) {
		define('EDD_PLUGIN_FILE', __FILE__);
	}
	if (!defined('EDD_PLUGIN_DIR')) {
		define('EDD_PLUGIN_DIR', plugin_dir_path(EDD_PLUGIN_FILE));
	}
	if (!defined('EDD_PLUGIN_URL')) {
		define('EDD_PLUGIN_URL', plugins_url('/', EDD_PLUGIN_FILE));
	}
	if (!defined('EDD_PLUGIN_BASE')) {
		define('EDD_PLUGIN_BASE', plugin_basename(EDD_PLUGIN_FILE));
	}

CONSTANTS;

	if ( $eddSlVersion ) {
		$constants .= <<<SL_CONSTANTS

	// EDD Software Licensing constants
	if (!defined('EDD_SL_VERSION')) {
		define('EDD_SL_VERSION', '{$eddSlVersion}');
	}
	if (!defined('EDD_SL_PLUGIN_FILE')) {
		define('EDD_SL_PLUGIN_FILE', __FILE__);
	}
	if (!defined('EDD_SL_PLUGIN_DIR')) {
		define('EDD_SL_PLUGIN_DIR', plugin_dir_path(EDD_SL_PLUGIN_FILE));
	}
	if (!defined('EDD_SL_PLUGIN_URL')) {
		define('EDD_SL_PLUGIN_URL', plugins_url('/', EDD_SL_PLUGIN_FILE));
	}

SL_CONSTANTS;
	}

	if ( $eddConvertkitVersion ) {
		$constants .= <<<CK_CONSTANTS

	// EDD ConvertKit constants
	if (!defined('EDD_CONVERTKIT_VERSION')) {
		define('EDD_CONVERTKIT_VERSION', '{$eddConvertkitVersion}');
	}

CK_CONSTANTS;
	}

	$constants .= "}\n\n";

	return preg_replace( '/^(namespace )/m', $constants . '$1', $content, 1 );
}
