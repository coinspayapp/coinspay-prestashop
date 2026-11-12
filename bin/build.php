<?php
/**
 * Builds dist/coinspay.zip: the coinspay/ folder at the root of the archive, an index.php in
 * every folder (PrestaShop's guard against folder listing), the license and the guides.
 *
 * Run: php bin/build.php (needs the zip extension).
 */
$root = dirname(__DIR__);
$build = $root . '/dist/build';
$target = $build . '/coinspay';
$zipFile = $root . '/dist/coinspay.zip';

$index = <<<'PHP'
<?php
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Location: ../');
exit;

PHP;

function remove_tree($path) {
	if (!file_exists($path)) {
		return;
	}
	if (is_dir($path) && !is_link($path)) {
		foreach (scandir($path) as $item) {
			if ($item !== '.' && $item !== '..') {
				remove_tree($path . '/' . $item);
			}
		}
		rmdir($path);
	} else {
		unlink($path);
	}
}

function copy_tree($from, $to) {
	mkdir($to, 0755, true);
	foreach (scandir($from) as $item) {
		if ($item === '.' || $item === '..') {
			continue;
		}
		if (is_dir($from . '/' . $item)) {
			copy_tree($from . '/' . $item, $to . '/' . $item);
		} else {
			copy($from . '/' . $item, $to . '/' . $item);
		}
	}
}

function add_index($dir, $index) {
	if (!file_exists($dir . '/index.php')) {
		file_put_contents($dir . '/index.php', $index);
	}
	foreach (scandir($dir) as $item) {
		if ($item !== '.' && $item !== '..' && is_dir($dir . '/' . $item)) {
			add_index($dir . '/' . $item, $index);
		}
	}
}

remove_tree($build);
@unlink($zipFile);
copy_tree($root . '/coinspay', $target);
copy($root . '/LICENSE', $target . '/LICENSE');
mkdir($target . '/docs', 0755, true);
foreach (array('install-en.md', 'install-ru.md') as $doc) {
	copy($root . '/docs/' . $doc, $target . '/docs/' . $doc);
}
add_index($target, $index);

$zip = new ZipArchive();
if ($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
	fwrite(STDERR, "cannot create $zipFile\n");
	exit(1);
}
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($target, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
$zip->addEmptyDir('coinspay');
foreach ($files as $file) {
	$name = 'coinspay/' . str_replace('\\', '/', substr($file->getPathname(), strlen($target) + 1));
	if ($file->isDir()) {
		$zip->addEmptyDir($name);
	} else {
		$zip->addFile($file->getPathname(), $name);
	}
}
$zip->close();
echo 'dist/coinspay.zip: ' . filesize($zipFile) . " bytes\n";
