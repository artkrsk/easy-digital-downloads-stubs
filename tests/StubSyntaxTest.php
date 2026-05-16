<?php

namespace EasyDigitalDownloadsStubs\Tests;

use PHPUnit\Framework\TestCase;

class StubSyntaxTest extends TestCase {

	private string $stubsFile;

	protected function setUp(): void {
		$this->stubsFile = __DIR__ . '/../easy-digital-downloads-stubs.php';
	}

	public function testStubFileExists(): void {
		$this->assertFileExists( $this->stubsFile, 'Stub file should exist' );
	}

	public function testStubFileIsReadable(): void {
		$this->assertFileIsReadable( $this->stubsFile, 'Stub file should be readable' );
	}

	public function testStubFileHasValidSyntax(): void {
		$output   = array();
		$exitCode = 0;
		exec( 'php -l ' . escapeshellarg( $this->stubsFile ) . ' 2>&1', $output, $exitCode );

		$this->assertEquals( 0, $exitCode, 'Stub file should have valid PHP syntax: ' . implode( "\n", $output ) );
	}

	/**
	 * Stray code statements (code outside class/function bodies) must not survive
	 * post-processing.
	 */
	public function testNoStrayCodeStatements(): void {
		$stubContent = file_get_contents( $this->stubsFile );
		$this->assertNotFalse( $stubContent, 'Stub file should be readable' );

		// Pattern: $variable = ... $this->method() at namespace level (outside class).
		$this->assertDoesNotMatchRegularExpression(
			'/^\s*\$\w+\s*=.*\$this->/m',
			$stubContent,
			'Should not have stray $this references at namespace level'
		);

		// Pattern: $variable = apply_filters() at top level.
		$this->assertDoesNotMatchRegularExpression(
			'/^\s*\$\w+\s*=\s*apply_filters\(/m',
			$stubContent,
			'Should not have stray apply_filters calls at namespace level'
		);
	}

	public function testEddVersionConstant(): void {
		$this->assertTrue( defined( 'EDD_VERSION' ), 'EDD_VERSION should be defined' );

		/** @var string $version */
		$version = EDD_VERSION;

		$this->assertMatchesRegularExpression(
			'/^\d+\.\d+(\.\d+)?(\.\d+)?/',
			$version,
			'EDD_VERSION should be in semantic version format'
		);
	}

	/**
	 * All standard EDD constants are defined by addSelfContainedConstants().
	 */
	public function testAllEddConstantsExist(): void {
		$requiredConstants = array(
			'EDD_VERSION',
			'EDD_PLUGIN_FILE',
			'EDD_PLUGIN_DIR',
			'EDD_PLUGIN_URL',
			'EDD_PLUGIN_BASE',
		);

		foreach ( $requiredConstants as $constant ) {
			$this->assertTrue(
				defined( $constant ),
				"$constant should be defined"
			);
		}
	}

	/**
	 * Core EDD classes used by every integration should resolve.
	 */
	public function testCoreClassesExist(): void {
		$classes = array(
			'EDD_Customer' => 'Customer entity',
			'EDD_Payment'  => 'Legacy payment entity (still used in EDD 3.x order pipelines)',
		);

		foreach ( $classes as $class => $description ) {
			$this->assertTrue(
				class_exists( $class ),
				"$class ($description) should exist"
			);
		}
	}

	/**
	 * Common EDD helper functions used in customer / order pipelines.
	 */
	public function testCoreFunctionsExist(): void {
		$functions = array(
			'edd_get_option',
			'edd_get_order',
			'edd_get_order_meta',
			'edd_update_order_meta',
			'edd_build_order',
			'edd_get_payment_meta',
			'edd_sanitize_amount',
			'edd_insert_payment_note',
		);

		foreach ( $functions as $function ) {
			$this->assertTrue(
				function_exists( $function ),
				"$function() should exist"
			);
		}
	}

	/**
	 * EDD Software Licensing core symbols, when SL stubs are generated.
	 */
	public function testSoftwareLicensingSymbols(): void {
		if ( ! defined( 'EDD_SL_VERSION' ) ) {
			$this->markTestSkipped( 'EDD Software Licensing stubs not generated' );
		}

		$this->assertTrue(
			class_exists( 'EDD_Software_Licensing' ),
			'EDD_Software_Licensing class should exist'
		);

		$this->assertTrue(
			class_exists( 'EDD_SL_Download' ),
			'EDD_SL_Download class should exist'
		);

		$this->assertTrue(
			class_exists( 'EDD_SL_License' ),
			'EDD_SL_License class should exist'
		);

		$this->assertTrue(
			function_exists( 'edd_software_licensing' ),
			'edd_software_licensing() should exist'
		);
	}

	/**
	 * EDD ConvertKit core symbol, when ConvertKit stubs are generated.
	 */
	public function testConvertKitSymbol(): void {
		if ( ! defined( 'EDD_CONVERTKIT_VERSION' ) ) {
			$this->markTestSkipped( 'EDD ConvertKit stubs not generated' );
		}

		$this->assertTrue(
			class_exists( 'EDD_ConvertKit' ),
			'EDD_ConvertKit class should exist'
		);
	}
}
