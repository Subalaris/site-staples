<?php
/** Lint every first-party and bundled PHP file. */
$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( dirname( __DIR__ ), FilesystemIterator::SKIP_DOTS ) );
$count = 0;
foreach ( $iterator as $file ) {
	if ( 'php' !== $file->getExtension() || str_contains( $file->getPathname(), '/vendor/' ) ) {
		continue;
	}
	passthru( escapeshellarg( PHP_BINARY ) . ' -l ' . escapeshellarg( $file->getPathname() ), $exit );
	if ( 0 !== $exit ) {
		exit( $exit );
	}
	++$count;
}
echo "Linted $count PHP files.\n";
