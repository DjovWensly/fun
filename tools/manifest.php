<?php
/**
 * Writes data/manifest.json: a fingerprint of every site file, so check.php can tell which files
 * on the server are missing or still an old version after an upload.
 * Run it after changing files:   php tools/manifest.php
 * Line endings are ignored (FTP and Windows can turn LF into CRLF), so only real changes count.
 */
$root = dirname(__DIR__);
$skip = '~^(README\.md|DEPLOIEMENT-INFINITYFREE\.md|install\.sql|config\.sample\.php|config\.php|check\.php|\.gitignore|\.htaccess|tools/|data/manifest\.json|img/)~';
$files = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    $rel = str_replace('\\', '/', substr($f->getPathname(), strlen($root) + 1));
    if (strpos($rel, '.git/') === 0 || preg_match($skip, $rel) || !preg_match('~\.(php|js|css|json)$~', $rel)) {
        continue;
    }
    $files[$rel] = sha1(str_replace("\r", '', (string)file_get_contents($f->getPathname())));
}
ksort($files);
file_put_contents($root . '/data/manifest.json', json_encode(['generated' => gmdate('Y-m-d H:i'), 'files' => $files], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
echo count($files) . " files written to data/manifest.json\n";
