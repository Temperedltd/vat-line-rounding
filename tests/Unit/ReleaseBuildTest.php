<?php
/**
 * Tests for release build helper behaviour.
 *
 * @package TemperedVATLineRounding
 */

declare(strict_types=1);

// phpcs:disable WordPress.WP.AlternativeFunctions, WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec

final class ReleaseBuildTest extends Tempered_VLR_Test_Case {
	private string $temp_dir;

	protected function setUp(): void {
		parent::setUp();

		$this->temp_dir = sys_get_temp_dir() . '/tempered-vlr-release-test-' . bin2hex( random_bytes( 6 ) );

		mkdir( $this->temp_dir, 0777, true );
	}

	protected function tearDown(): void {
		$this->remove_directory( $this->temp_dir );

		parent::tearDown();
	}

	public function test_extracts_plugin_header_version(): void {
		$plugin_file = $this->temp_dir . '/vat-line-rounding.php';

		file_put_contents(
			$plugin_file,
			"<?php\n/**\n * Plugin Name: Fixture\n * Version: 1.2.3\n */\n"
		);

		$result = $this->run_release_script( array( 'plugin-version', $plugin_file ) );

		self::assertSame( 0, $result['exit_code'], $result['output'] );
		self::assertSame( '1.2.3', trim( $result['output'] ) );
	}

	public function test_rejects_unsafe_plugin_header_versions(): void {
		$plugin_file = $this->temp_dir . '/vat-line-rounding.php';

		file_put_contents(
			$plugin_file,
			"<?php\n/**\n * Plugin Name: Fixture\n * Version: 1.2.3$(touch owned)\n */\n"
		);

		$result = $this->run_release_script( array( 'plugin-version', $plugin_file ) );

		self::assertNotSame( 0, $result['exit_code'], $result['output'] );
		self::assertStringContainsString( 'Unsafe Version header', $result['output'] );
	}

	public function test_detects_plugin_header_version_changes(): void {
		$current_file  = $this->temp_dir . '/current.php';
		$previous_file = $this->temp_dir . '/previous.php';

		file_put_contents(
			$current_file,
			"<?php\n/**\n * Version: 1.2.3\n */\n"
		);
		file_put_contents(
			$previous_file,
			"<?php\n/**\n * Version: 1.2.2\n */\n"
		);

		$result = $this->run_release_script( array( 'version-changed', $current_file, $previous_file ) );

		self::assertSame( 0, $result['exit_code'], $result['output'] );
		self::assertSame( 'true', trim( $result['output'] ) );
	}

	public function test_extracts_matching_changelog_release_notes(): void {
		$changelog_file = $this->temp_dir . '/CHANGELOG.md';

		file_put_contents(
			$changelog_file,
			"# Changelog\n\n" .
			"## [Unreleased]\n\n" .
			"### Added\n\n" .
			"- Future work.\n\n" .
			"## [1.2.3] - 2026-06-19\n\n" .
			"### Added\n\n" .
			"- Built production release ZIPs.\n\n" .
			"### Fixed\n\n" .
			"- Kept vendor files out of release artifacts.\n\n" .
			"## [1.2.2] - 2026-06-18\n\n" .
			"### Added\n\n" .
			"- Older release note.\n"
		);

		$result = $this->run_release_script( array( 'release-notes', $changelog_file, '1.2.3' ) );

		self::assertSame( 0, $result['exit_code'], $result['output'] );
		self::assertStringContainsString( '### Added', $result['output'] );
		self::assertStringContainsString( '- Built production release ZIPs.', $result['output'] );
		self::assertStringContainsString( '### Fixed', $result['output'] );
		self::assertStringNotContainsString( 'Future work', $result['output'] );
		self::assertStringNotContainsString( 'Older release note', $result['output'] );
	}

	public function test_build_zip_contains_only_production_files(): void {
		$source_dir = $this->temp_dir . '/source';
		$output_dir = $this->temp_dir . '/dist';

		mkdir( $source_dir . '/includes', 0777, true );
		mkdir( $source_dir . '/tests', 0777, true );
		mkdir( $source_dir . '/vendor/package', 0777, true );
		mkdir( $source_dir . '/docs', 0777, true );
		mkdir( $source_dir . '/.phpunit.cache', 0777, true );

		file_put_contents( $source_dir . '/vat-line-rounding.php', "<?php\n" );
		file_put_contents( $source_dir . '/includes/class-fixture.php', "<?php\n" );
		file_put_contents( $source_dir . '/README.md', "# Readme\n" );
		file_put_contents( $source_dir . '/SECURITY.md', "# Security\n" );
		file_put_contents( $source_dir . '/CHANGELOG.md', "# Changelog\n" );
		file_put_contents( $source_dir . '/composer.json', "{}\n" );
		file_put_contents( $source_dir . '/tests/ReleaseBuildTest.php', "<?php\n" );
		file_put_contents( $source_dir . '/vendor/package/autoload.php', "<?php\n" );
		file_put_contents( $source_dir . '/docs/internal.md', "# Internal\n" );
		file_put_contents( $source_dir . '/.phpunit.cache/test-result', "cache\n" );

		$result = $this->run_release_script( array( 'build-zip', $source_dir, $output_dir, '1.2.3' ) );

		self::assertSame( 0, $result['exit_code'], $result['output'] );

		$zip_file = trim( $result['output'] );

		self::assertFileExists( $zip_file );
		self::assertSame(
			array(
				'vat-line-rounding/CHANGELOG.md',
				'vat-line-rounding/README.md',
				'vat-line-rounding/SECURITY.md',
				'vat-line-rounding/includes/class-fixture.php',
				'vat-line-rounding/vat-line-rounding.php',
			),
			$this->list_zip_files( $zip_file )
		);
	}

	/**
	 * Run the release helper.
	 *
	 * @param array<int,string> $arguments Script arguments.
	 * @return array{exit_code:int,output:string}
	 */
	private function run_release_script( array $arguments ): array {
		$command = array_merge(
			array( 'bash', dirname( __DIR__, 2 ) . '/scripts/release-build.sh' ),
			$arguments
		);

		$escaped_command = implode( ' ', array_map( 'escapeshellarg', $command ) ) . ' 2>&1';
		$output          = array();
		$exit_code       = 0;

		exec( $escaped_command, $output, $exit_code );

		return array(
			'exit_code' => $exit_code,
			'output'    => implode( "\n", $output ),
		);
	}

	/**
	 * List ZIP file paths.
	 *
	 * @param string $zip_file ZIP file path.
	 * @return array<int,string>
	 */
	private function list_zip_files( string $zip_file ): array {
		$zip = new ZipArchive();

		self::assertTrue( $zip->open( $zip_file ) );

		$files = array();

		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- ZipArchive defines this public property.
		for ( $index = 0; $index < $zip->numFiles; $index++ ) {
			$name = $zip->getNameIndex( $index );

			if ( false !== $name && ! str_ends_with( $name, '/' ) ) {
				$files[] = $name;
			}
		}

		$zip->close();
		sort( $files );

		return $files;
	}

	/**
	 * Remove a directory tree.
	 *
	 * @param string $directory Directory path.
	 * @return void
	 */
	private function remove_directory( string $directory ): void {
		if ( ! is_dir( $directory ) ) {
			return;
		}

		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $directory, FilesystemIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::CHILD_FIRST
		);

		foreach ( $iterator as $path ) {
			if ( $path->isDir() ) {
				rmdir( $path->getPathname() );
				continue;
			}

			unlink( $path->getPathname() );
		}

		rmdir( $directory );
	}
}
