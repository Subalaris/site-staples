<?php
/** Produce a runtime-only ZIP with a predictable WordPress plugin directory. */
$root = dirname( __DIR__ );
if ( ! class_exists( 'ZipArchive' ) ) {
	fwrite( STDERR, "PHP Zip extension is required.\n" );
	exit( 1 );
}
$dist = $root . '/dist';
if ( ! is_dir( $dist ) ) {
	mkdir( $dist );
}
preg_match( '/^ \* Version: ([0-9]+\.[0-9]+\.[0-9]+)$/m', file_get_contents( $root . '/site-staples.php' ), $match );
if ( empty( $match[1] ) ) {
	throw new RuntimeException( 'Missing release version in plugin header.' );
}
$zip_path = $dist . '/site-staples-' . $match[1] . '.zip';
$zip = new ZipArchive();
$zip->open( $zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE );
$files = array( 'site-staples.php', 'uninstall.php', 'readme.txt', 'README.md', 'CHANGELOG.md', 'LICENSE' );
foreach ( array( 'includes', 'lib', 'languages', 'docs', 'assets' ) as $dir ) {
	$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root . '/' . $dir, FilesystemIterator::SKIP_DOTS ) );
	foreach ( $iterator as $file ) {
		if ( $file->isFile() ) {
			$files[] = substr( $file->getPathname(), strlen( $root ) + 1 );
		}
	}
}
sort( $files );
foreach ( $files as $relative ) {
	if ( ! is_file( $root . '/' . $relative ) ) {
		throw new RuntimeException( "Missing release file: $relative" );
	}
	if ( 'README.md' === $relative ) {
		$readme = str_replace( 'THIRD-PARTY.md', 'THIRD-PARTY.txt', file_get_contents( $root . '/' . $relative ) );
		$zip->addFromString( 'site-staples/' . $relative, $readme );
	} else {
		$zip->addFile( $root . '/' . $relative, 'site-staples/' . $relative );
	}
}
// Use the permitted text extension for the dependency notice in the release.
$zip->addFile( $root . '/THIRD-PARTY.md', 'site-staples/THIRD-PARTY.txt' );
$zip->close();
file_put_contents( $zip_path . '.sha256', hash_file( 'sha256', $zip_path ) . '  ' . basename( $zip_path ) . "\n" );
echo $zip_path . "\n";
