<?php
/**
 * Toxic Market — Upload Security Tests
 *
 * Run: cd /path/to/toxic-market && php tests/UploadTest.php
 */

$tmpDir = sys_get_temp_dir() . '/toxic-market-upload-tests-' . uniqid();
mkdir($tmpDir, 0750, true);
$testDbPath = $tmpDir . '/test_toxic_market.db';
putenv('TOXIC_DB_PATH=' . $testDbPath);
$_ENV['TOXIC_DB_PATH'] = $testDbPath;
$_SERVER['DOCUMENT_ROOT'] = realpath(__DIR__ . '/../..');

$uploadRoot = $tmpDir . '/uploads';
mkdir($uploadRoot, 0750, true);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/validation.php';

if (!extension_loaded('gd')) {
    fwrite(STDERR, "⚠️ GD extension not available in this PHP build. Upload re-encoding tests skipped.\n");
    echo "Passed: 0\nFailed: 0 (GD skipped)\n";
    exit(0);
}

$passed = 0;
$failed = 0;

function assertTrue(string $name, bool $condition, string $details = ''): void {
    global $passed, $failed;
    if ($condition) {
        echo "✅ {$name}\n";
        $passed++;
    } else {
        echo "❌ {$name}" . ($details ? " — {$details}" : '') . "\n";
        $failed++;
    }
}

function makeTmpFile(string $contents, string $ext): string {
    global $tmpDir;
    $path = $tmpDir . '/test_' . bin2hex(random_bytes(4)) . '.' . $ext;
    file_put_contents($path, $contents);
    return $path;
}

function createTestJpeg(int $w, int $h): string {
    $img = imagecreatetruecolor($w, $h);
    $bg = imagecolorallocate($img, 255, 0, 0);
    imagefill($img, 0, 0, $bg);
    $path = $tmpDir . '/test_' . bin2hex(random_bytes(4)) . '.jpg';
    imagejpeg($img, $path, 90);
    imagedestroy($img);
    return $path;
}

// Mock $_FILES entry factory.
function mockFile(string $path, string $clientName, int $error = UPLOAD_ERR_OK): array {
    return [
        'name' => $clientName,
        'type' => mime_content_type($path) ?: 'application/octet-stream',
        'tmp_name' => $path,
        'size' => filesize($path),
        'error' => $error,
    ];
}

// 1. Valid JPEG upload.
try {
    $path = createTestJpeg(200, 200);
    $file = mockFile($path, 'photo.jpg');
    $result = validateUploadedImage($file, 42);
    assertTrue('Valid JPEG accepted', isset($result['url'], $result['filename']) && str_contains($result['filename'], '_42.jpg'));
    assertTrue('Uploaded file exists', file_exists($result['path']));
    assertTrue('Uploaded file permissions tight', (fileperms($result['path']) & 0777) === 0640);
} catch (Throwable $e) {
    assertTrue('Valid JPEG accepted', false, $e->getMessage());
}

// 2. Non-image file rejected.
try {
    $path = makeTmpFile('<?php echo "shell"; ?>', 'jpg');
    $file = mockFile($path, 'shell.jpg');
    validateUploadedImage($file, 1);
    assertTrue('PHP-in-jpg polyglot rejected', false, 'Expected exception');
} catch (UploadException $e) {
    assertTrue('PHP-in-jpg polyglot rejected', $e->getCode() === 400);
}

// 3. File too large rejected.
try {
    $path = makeTmpFile(str_repeat('x', TOXIC_UPLOAD_MAX_BYTES + 1), 'png');
    $file = mockFile($path, 'huge.png');
    $file['size'] = TOXIC_UPLOAD_MAX_BYTES + 1; // override size to avoid writing huge image
    validateUploadedImage($file, 1);
    assertTrue('Oversized file rejected', false, 'Expected exception');
} catch (UploadException $e) {
    assertTrue('Oversized file rejected', $e->getCode() === 413);
}

// 4. Wrong extension type rejected.
try {
    $path = makeTmpFile('GIF89a\x01\x00\x01\x00\x80\x00\x00\xff\xff\xff\x00\x00\x00!\xf9\x04\x00\x00\x00\x00\x00,\x00\x00\x00\x00\x01\x00\x01\x00\x00\x02\x02D\x01\x00;', 'gif');
    $file = mockFile($path, 'tiny.gif');
    validateUploadedImage($file, 1);
    assertTrue('GIF rejected (not allowed)', false, 'Expected exception');
} catch (UploadException $e) {
    assertTrue('GIF rejected (not allowed)', $e->getCode() === 400 && str_contains($e->getMessage(), 'JPG'));
}

// 5. Too small image rejected.
try {
    $path = createTestJpeg(10, 10);
    $file = mockFile($path, 'tiny.jpg');
    validateUploadedImage($file, 1);
    assertTrue('Tiny image rejected', false, 'Expected exception');
} catch (UploadException $e) {
    assertTrue('Tiny image rejected', $e->getCode() === 400 && str_contains($e->getMessage(), 'small'));
}

// 6. Huge dimension rejected.
try {
    $path = createTestJpeg(5000, 5000);
    $file = mockFile($path, 'huge.jpg');
    validateUploadedImage($file, 1);
    assertTrue('Huge image rejected', false, 'Expected exception');
} catch (UploadException $e) {
    assertTrue('Huge image rejected', $e->getCode() === 400 && str_contains($e->getMessage(), 'large'));
}

// 7. deleteUploadedImage authorization.
if (isset($result)) {
    try {
        $ok = deleteUploadedImage($result['filename'], 42);
        assertTrue('Owner can delete own upload', $ok);
    } catch (Throwable $e) {
        assertTrue('Owner can delete own upload', false, $e->getMessage());
    }

    try {
        deleteUploadedImage($result['filename'], 99);
        assertTrue('Non-owner cannot delete upload', false, 'Expected exception');
    } catch (UploadException $e) {
        assertTrue('Non-owner cannot delete upload', $e->getCode() === 403);
    }
}

// Cleanup
exec('rm -rf ' . escapeshellarg($tmpDir));

echo "\n";
echo "Passed: {$passed}\n";
echo "Failed: {$failed}\n";
exit($failed === 0 ? 0 : 1);
