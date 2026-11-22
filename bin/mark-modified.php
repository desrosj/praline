<?php
/**
 * Marks every bundled Markdown Alternate file as changed, as GPL-2.0 §2a
 * asks: Strauss rewrites their namespaces. Run by `composer prefix`.
 *
 * @package Praline
 */

// A build script: from the command line only, never over the web.
if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

$marker = 'Modified by Praline';
$notice = '/* ' . $marker . ' on ' . gmdate( 'Y-m-d' ) . ': namespaces prefixed with Strauss. Original: https://github.com/ProgressPlanner/markdown-alternate (GPL-2.0-or-later). */';
$root   = dirname( __DIR__ ) . '/vendor-prefixed/joost/markdown-alternate/src';

if ( ! is_dir( $root ) ) {
	fwrite( STDERR, "No bundled Markdown Alternate at $root\n" );
	exit( 1 );
}

$count = 0;
foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ) ) as $file ) {
	if ( 'php' !== $file->getExtension() ) {
		continue;
	}
	$source = file_get_contents( $file->getPathname() );
	if ( false === strpos( $source, $marker ) ) {
		$source = preg_replace( '/^<\?php\r?\n/', "<?php\n" . $notice . "\n", $source, 1, $replaced );
		if ( 1 !== $replaced ) {
			fwrite( STDERR, "Can't mark {$file->getPathname()}: it doesn't start with <?php\n" );
			exit( 1 );
		}
		if ( false === file_put_contents( $file->getPathname(), $source ) ) {
			fwrite( STDERR, "Can't write {$file->getPathname()}\n" );
			exit( 1 );
		}
	}
	++$count;
}

if ( 0 === $count ) {
	fwrite( STDERR, "No PHP files under $root\n" );
	exit( 1 );
}
echo "Marked $count bundled Markdown Alternate files.\n";
