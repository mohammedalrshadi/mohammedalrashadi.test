<?php
// ============================================================
// IMAGE OPTIMIZER HELPER
// api/helpers/image_optimizer.php
//
// High-performance image optimization for Hostinger shared hosting:
//   - GD-based conversion to WebP with smart quality selection:
//     * Lossless WebP / quality 95 vs lossy 82 comparison for diagrams/PNGs
//     * Lossy 82 for photos/JPEGs
//     * Strict size preservation: NEVER save an output larger than input
//   - Downscaling: longest side capped at 1920px (never upscales)
//   - Responsive variants: creates -480.webp and -960.webp variants
//   - EXIF orientation applied before metadata stripping
//   - Memory guard: rejects >40MP or when width*height*5 exceeds 70% memory_limit
//   - Animation preservation: detects animated WebP & animated GIF, keeps as-is
//   - Atomic writes: writes to temp file and renames
//   - Clean variant deletion helper for all delete/purge endpoints
//   - Safe registry recording into media_assets (nullable dimensions)
// ============================================================

if (!defined('UPLOAD_DIR')) {
    require_once dirname(__DIR__) . '/config.php';
}

/**
 * Returns system capabilities regarding GD, WebP, JPEG, PNG, EXIF, and memory.
 *
 * @return array<string, mixed>
 */
function imageOptimizerCapabilities(): array {
    $gdLoaded = extension_loaded('gd');
    $gdInfo = $gdLoaded ? gd_info() : [];

    $webpSupport = !empty($gdInfo['WebP Support']);
    $jpegSupport = !empty($gdInfo['JPEG Support']);
    $pngSupport  = !empty($gdInfo['PNG Support']);
    $gifSupport  = !empty($gdInfo['GIF Read Support']) && !empty($gdInfo['GIF Create Support']);
    $exifLoaded  = extension_loaded('exif');

    $memRaw   = ini_get('memory_limit') ?: '128M';
    $memBytes = parseMemoryLimit($memRaw);

    return [
        'gd_loaded'    => $gdLoaded,
        'gd_version'   => $gdInfo['GD Version'] ?? 'None',
        'webp_support' => $webpSupport,
        'jpeg_support' => $jpegSupport,
        'png_support'  => $pngSupport,
        'gif_support'  => $gifSupport,
        'exif_loaded'  => $exifLoaded,
        'memory_limit' => $memRaw,
        'memory_bytes' => $memBytes,
        'max_pixels'   => 40_000_000,
        'can_optimize' => ($gdLoaded && $webpSupport),
    ];
}

/**
 * Parses PHP memory_limit string (e.g., '128M', '1G', '-1') into bytes.
 *
 * @param  string|null $val
 * @return int Bytes, or PHP_INT_MAX if unlimited (-1)
 */
function parseMemoryLimit(?string $val = null): int {
    if ($val === null) {
        $val = ini_get('memory_limit') ?: '128M';
    }

    $val = trim($val);
    if ($val === '-1') {
        return PHP_INT_MAX;
    }

    $last = strtolower(substr($val, -1));
    $num = (int) $val;

    switch ($last) {
        case 'g':
            $num *= 1024 * 1024 * 1024;
            break;
        case 'm':
            $num *= 1024 * 1024;
            break;
        case 'k':
            $num *= 1024;
            break;
    }

    return $num > 0 ? $num : 128 * 1024 * 1024;
}

/**
 * Guards image decoding against memory exhaustion and pixel flood attacks.
 *
 * @param  int $width
 * @param  int $height
 * @throws RuntimeException If dimensions exceed memory guard rules
 */
function guardImageMemory(int $width, int $height): void {
    if ($width <= 0 || $height <= 0) {
        throw new RuntimeException('أبعاد الصورة غير صالحة.');
    }

    // 1. Megapixels ceiling (40 Megapixels)
    $pixels = (float) $width * (float) $height;
    if ($pixels > 40_000_000) {
        throw new RuntimeException('أبعاد الصورة كبيرة جداً (أكثر من 40 ميجابكسل).');
    }

    // 2. Memory budget check (~70% of available memory_limit)
    $memoryLimit = parseMemoryLimit();
    if ($memoryLimit !== PHP_INT_MAX && $memoryLimit > 0) {
        // GD RGBA truecolor memory estimation: width * height * 5 bytes
        $estimatedBytes = $pixels * 5;
        $budget = $memoryLimit * 0.70;

        if ($estimatedBytes > $budget) {
            throw new RuntimeException('أبعاد الصورة تتطلب ذاكرة معالجة تتجاوز الحد المتاح للخادم.');
        }
    }
}

/**
 * Detects if a WebP file is animated by inspecting the VP8X animation bit or ANIM chunk.
 *
 * @param  string $filepath Absolute path to file
 * @return bool
 */
function isAnimatedWebp(string $filepath): bool {
    if (!is_file($filepath) || filesize($filepath) < 30) {
        return false;
    }

    $fh = @fopen($filepath, 'rb');
    if (!$fh) {
        return false;
    }

    $header = fread($fh, 30);
    if (strlen($header) < 30) {
        fclose($fh);
        return false;
    }

    // Must be RIFF....WEBP
    if (substr($header, 0, 4) !== 'RIFF' || substr($header, 8, 4) !== 'WEBP') {
        fclose($fh);
        return false;
    }

    // VP8X header contains feature flags at offset 20
    if (substr($header, 12, 4) === 'VP8X') {
        $flags = ord($header[20]);
        // Bit 1 (0x02) indicates animation
        if (($flags & 0x02) !== 0) {
            fclose($fh);
            return true;
        }
    }

    // Also check for ANIM chunk in initial 4KB
    fseek($fh, 0);
    $chunk = fread($fh, 4096);
    fclose($fh);

    return (strpos($chunk, 'ANIM') !== false);
}

/**
 * Detects if a GIF file contains multiple graphic frames (animated GIF).
 *
 * @param  string $filepath Absolute path to file
 * @return bool
 */
function isAnimatedGif(string $filepath): bool {
    if (!is_file($filepath) || filesize($filepath) < 32) {
        return false;
    }

    $fh = @fopen($filepath, 'rb');
    if (!$fh) {
        return false;
    }

    $count = 0;
    // Inspect in chunks of 64KB for Graphic Control Extension block: \x00\x21\xF9\x04
    while (!feof($fh) && $count < 2) {
        $chunk = fread($fh, 65536);
        if ($chunk === false) {
            break;
        }
        $count += substr_count($chunk, "\x00\x21\xF9\x04");
    }
    fclose($fh);

    return ($count > 1);
}

/**
 * Reads EXIF orientation and applies rotation/flipping before metadata is dropped.
 * Silently skips if exif extension is unavailable or file lacks valid EXIF.
 *
 * @param  \GdImage|resource $image
 * @param  string            $filepath
 * @return \GdImage|resource
 */
function applyExifOrientation($image, string $filepath) {
    if (!function_exists('exif_read_data')) {
        return $image;
    }

    $exif = @exif_read_data($filepath, 'IFD0');
    if ($exif === false || empty($exif['Orientation'])) {
        return $image;
    }

    $orientation = (int) $exif['Orientation'];
    switch ($orientation) {
        case 2:
            imageflip($image, IMG_FLIP_HORIZONTAL);
            break;
        case 3:
            $rotated = imagerotate($image, 180, 0);
            if ($rotated !== false) {
                freeGdImage($image);
                $image = $rotated;
            }
            break;
        case 4:
            imageflip($image, IMG_FLIP_VERTICAL);
            break;
        case 5:
            $rotated = imagerotate($image, -90, 0);
            if ($rotated !== false) {
                freeGdImage($image);
                $image = $rotated;
                imageflip($image, IMG_FLIP_HORIZONTAL);
            }
            break;
        case 6:
            // 90 degrees CW (in GD counter-clockwise is positive, so -90 or 270)
            $rotated = imagerotate($image, -90, 0);
            if ($rotated !== false) {
                freeGdImage($image);
                $image = $rotated;
            }
            break;
        case 7:
            $rotated = imagerotate($image, 90, 0);
            if ($rotated !== false) {
                freeGdImage($image);
                $image = $rotated;
                imageflip($image, IMG_FLIP_HORIZONTAL);
            }
            break;
        case 8:
            // 90 degrees CCW (270 CW)
            $rotated = imagerotate($image, 90, 0);
            if ($rotated !== false) {
                freeGdImage($image);
                $image = $rotated;
            }
            break;
    }

    return $image;
}

/**
 * Safely frees a GD image resource or unsets object without triggering deprecation warnings in PHP 8.5+.
 *
 * @param  \GdImage|resource|null &$image
 * @return void
 */
function freeGdImage(&$image): void {
    if ($image === null) {
        return;
    }

    if (PHP_VERSION_ID < 80500 && is_resource($image)) {
        @imagedestroy($image);
    }
    $image = null;
}

/**
 * Safely creates a GD image from path based on MIME type, preserving alpha channels.
 *
 * @param  string $filepath
 * @param  string $mimeType
 * @return \GdImage|resource
 * @throws RuntimeException If decode fails
 */
function createGdImageFromMime(string $filepath, string $mimeType) {
    switch ($mimeType) {
        case 'image/jpeg':
            $img = @imagecreatefromjpeg($filepath);
            break;
        case 'image/png':
            $img = @imagecreatefrompng($filepath);
            if ($img !== false) {
                imagealphablending($img, false);
                imagesavealpha($img, true);
            }
            break;
        case 'image/webp':
            $img = @imagecreatefromwebp($filepath);
            if ($img !== false) {
                imagealphablending($img, false);
                imagesavealpha($img, true);
            }
            break;
        case 'image/gif':
            $img = @imagecreatefromgif($filepath);
            if ($img !== false) {
                imagealphablending($img, false);
                imagesavealpha($img, true);
            }
            break;
        default:
            $img = false;
            break;
    }

    if ($img === false) {
        throw new RuntimeException('فشل في فك تشفير بيانات الصورة عبر محرك الرسومات.');
    }

    return $img;
}

/**
 * Resamples a GD image to target dimensions while maintaining transparency.
 *
 * @param  \GdImage|resource $srcImage
 * @param  int               $dstWidth
 * @param  int               $dstHeight
 * @param  int               $srcWidth
 * @param  int               $srcHeight
 * @return \GdImage|resource
 */
function resampleGdImage($srcImage, int $dstWidth, int $dstHeight, int $srcWidth, int $srcHeight) {
    $dst = imagecreatetruecolor($dstWidth, $dstHeight);
    if ($dst === false) {
        throw new RuntimeException('فشل تخصيص مساحة الذاكرة لتحجيم الصورة.');
    }

    imagealphablending($dst, false);
    imagesavealpha($dst, true);
    $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
    imagefilledrectangle($dst, 0, 0, $dstWidth, $dstHeight, $transparent);

    imagecopyresampled($dst, $srcImage, 0, 0, 0, 0, $dstWidth, $dstHeight, $srcWidth, $srcHeight);

    return $dst;
}

/**
 * Encodes a GD image to a WebP file atomically via temporary file.
 *
 * @param  \GdImage|resource $image
 * @param  string            $destFile
 * @param  int               $quality  Quality (0-100) or IMG_WEBP_LOSSLESS
 * @return bool
 */
function saveWebpAtomic($image, string $destFile, int $quality = 82): bool {
    $tmpFile = $destFile . '.tmp.' . bin2hex(random_bytes(6));
    $success = @imagewebp($image, $tmpFile, $quality);

    if ($success && is_file($tmpFile)) {
        if (!rename($tmpFile, $destFile)) {
            @unlink($tmpFile);
            return false;
        }
        return true;
    }

    if (is_file($tmpFile)) {
        @unlink($tmpFile);
    }
    return false;
}

/**
 * Core image optimization pipeline.
 *
 * Process:
 *   1. Reads dimensions with getimagesize() before memory allocation.
 *   2. Enforces memory guard (megapixels & RAM threshold).
 *   3. Checks animation: if animated WebP or animated GIF, caps at 5MB and keeps as-is.
 *   4. Decodes image safely, applies EXIF rotation, strips metadata upon re-encoding.
 *   5. Limits longest edge to max 1920px (never upscales).
 *   6. Smart quality: for PNG/diagrams compares lossless vs lossy 82; for photos uses lossy 82.
 *   7. Output size guarantee: NEVER saves an output larger than the input.
 *   8. Generates -480.webp and -960.webp responsive variants (only when original is larger).
 *
 * @param  string $srcPath  Path to uploaded or existing image file
 * @param  string $destBase Destination base path WITHOUT extension (e.g., /path/uploads/hex16)
 * @param  array  $options  Optional overrides: ['quality' => 82, 'max_dimension' => 1920, 'generate_variants' => true]
 * @return array  Optimization result metadata
 * @throws RuntimeException On unrecoverable validation failure
 */
function optimizeImage(string $srcPath, string $destBase, array $options = []): array {
    if (!is_file($srcPath) || !is_readable($srcPath)) {
        throw new RuntimeException('الملف المصدر غير موجود أو لا يمكن قراءته.');
    }

    $srcSize = filesize($srcPath);
    if ($srcSize === false || $srcSize === 0) {
        throw new RuntimeException('حجم الملف المصدر غير صالح.');
    }

    // 1. Inspect image dimensions and type via getimagesize()
    $imgInfo = @getimagesize($srcPath);
    if ($imgInfo === false) {
        throw new RuntimeException('الملف ليس صورة صالحة أو تالف.');
    }

    $origWidth  = (int) $imgInfo[0];
    $origHeight = (int) $imgInfo[1];

    // 2. MIME type verification via finfo (never trust client header or extension)
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($srcPath);
    if (!$mimeType) {
        $mimeType = $imgInfo['mime'] ?? 'image/jpeg';
    }

    // 3. Memory guard (throws RuntimeException if too large)
    guardImageMemory($origWidth, $origHeight);

    $destDir = dirname($destBase);
    if (!is_dir($destDir)) {
        if (!mkdir($destDir, 0755, true)) {
            throw new RuntimeException('فشل في إنشاء مجلد التخزين.');
        }
    }

    // 4. Animation Check — never flatten animated images
    $isAnimWebp = ($mimeType === 'image/webp') && isAnimatedWebp($srcPath);
    $isAnimGif  = ($mimeType === 'image/gif') && isAnimatedGif($srcPath);

    if ($isAnimWebp || $isAnimGif) {
        // Enforce 5 MB cap on animated files kept as-is
        if ($srcSize > 5 * 1024 * 1024) {
            throw new RuntimeException('حجم الصورة المتحركة يتجاوز الحد المسموح به (5 ميجابايت).');
        }

        $animExt = $isAnimWebp ? 'webp' : 'gif';
        $finalMainPath = $destBase . '.' . $animExt;

        // Copy as-is to destination
        if ($srcPath !== $finalMainPath) {
            if (!copy($srcPath, $finalMainPath)) {
                throw new RuntimeException('فشل في حفظ الصورة المتحركة.');
            }
        }

        $publicUrl = (defined('UPLOAD_URL_PATH') ? UPLOAD_URL_PATH : '/uploads/') . basename($finalMainPath);

        return [
            'main_path'   => $finalMainPath,
            'main_url'    => $publicUrl,
            'variants'    => [],
            'width'       => $origWidth,
            'height'      => $origHeight,
            'mime'        => $mimeType,
            'size'        => filesize($finalMainPath),
            'is_original' => true,
            'is_animated' => true,
        ];
    }

    // Check GD & WebP capability
    $caps = imageOptimizerCapabilities();
    if (!$caps['can_optimize']) {
        throw new RuntimeException('خادم الويب لا يدعم معالجة صور WebP عبر مكتبة GD.');
    }

    // 5. Decode source image
    $image = createGdImageFromMime($srcPath, $mimeType);

    // 6. Apply EXIF orientation before dropping metadata
    $image = applyExifOrientation($image, $srcPath);

    // Dimensions after EXIF rotation
    $currentWidth  = imagesx($image);
    $currentHeight = imagesy($image);

    // 7. Downscaling check: max 1920 longest side (never upscale)
    $maxDim = isset($options['max_dimension']) ? (int) $options['max_dimension'] : 1920;
    if ($maxDim > 0 && max($currentWidth, $currentHeight) > $maxDim) {
        if ($currentWidth >= $currentHeight) {
            $mainWidth  = $maxDim;
            $mainHeight = (int) round(($currentHeight / $currentWidth) * $maxDim);
        } else {
            $mainHeight = $maxDim;
            $mainWidth  = (int) round(($currentWidth / $currentHeight) * $maxDim);
        }
        $mainImage = resampleGdImage($image, $mainWidth, $mainHeight, $currentWidth, $currentHeight);
        freeGdImage($image);
        $image = $mainImage;
    } else {
        $mainWidth  = $currentWidth;
        $mainHeight = $currentHeight;
    }

    // 8. Smart Quality Encoding
    // For PNG/diagrams: compare lossless WebP vs lossy 82 WebP, keep smaller.
    // For photos/JPEG: use lossy 82.
    $targetWebpPath = $destBase . '.webp';
    $isPngOrDiagram = ($mimeType === 'image/png' || $mimeType === 'image/gif');

    $encodedFilesToClean = [];

    if ($isPngOrDiagram) {
        $losslessTmp = $destBase . '.lossless.webp.tmp';
        $lossyTmp    = $destBase . '.lossy.webp.tmp';

        $losslessQuality = defined('IMG_WEBP_LOSSLESS') ? IMG_WEBP_LOSSLESS : 95;
        $losslessOk = @imagewebp($image, $losslessTmp, $losslessQuality);
        $lossyOk    = @imagewebp($image, $lossyTmp, 82);

        $losslessSize = ($losslessOk && is_file($losslessTmp)) ? filesize($losslessTmp) : PHP_INT_MAX;
        $lossySize    = ($lossyOk && is_file($lossyTmp)) ? filesize($lossyTmp) : PHP_INT_MAX;

        if ($losslessSize <= $lossySize && $losslessOk) {
            rename($losslessTmp, $targetWebpPath);
            if (is_file($lossyTmp)) @unlink($lossyTmp);
        } elseif ($lossyOk) {
            rename($lossyTmp, $targetWebpPath);
            if (is_file($losslessTmp)) @unlink($losslessTmp);
        } else {
            if (is_file($losslessTmp)) @unlink($losslessTmp);
            if (is_file($lossyTmp)) @unlink($lossyTmp);
            throw new RuntimeException('فشل في تشفير ملف WebP.');
        }
    } else {
        $lossyQuality = isset($options['quality']) ? (int) $options['quality'] : 82;
        if (!saveWebpAtomic($image, $targetWebpPath, $lossyQuality)) {
            throw new RuntimeException('فشل في حفظ ملف WebP المحسّن.');
        }
    }

    $webpSize = is_file($targetWebpPath) ? filesize($targetWebpPath) : PHP_INT_MAX;

    // 9. Strict Size Comparison: NEVER save an output larger than the input!
    $isOriginalKept = false;
    $finalMainPath = $targetWebpPath;
    $finalMime = 'image/webp';

    // Extract original extension safely
    $origExt = strtolower(pathinfo($srcPath, PATHINFO_EXTENSION));
    if (!in_array($origExt, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
        $origExt = ($mimeType === 'image/png') ? 'png' : (($mimeType === 'image/gif') ? 'gif' : 'jpg');
    }

    if ($webpSize > $srcSize) {
        // The original image is smaller! Keep the original file as the primary file.
        $isOriginalKept = true;
        $finalMainPath  = $destBase . '.' . $origExt;
        $finalMime      = $mimeType;

        if ($srcPath !== $finalMainPath) {
            copy($srcPath, $finalMainPath);
        }
        // Remove the larger webp if different
        if ($targetWebpPath !== $finalMainPath && is_file($targetWebpPath)) {
            @unlink($targetWebpPath);
        }
    }

    // 10. Generate Responsive Variants (-960.webp and -480.webp)
    $variants = [];
    $generateVariants = $options['generate_variants'] ?? true;

    if ($generateVariants) {
        $variantTargets = [
            960 => $destBase . '-960.webp',
            480 => $destBase . '-480.webp',
        ];

        foreach ($variantTargets as $targetW => $variantPath) {
            // Never upscale: only generate variant if main image is wider than target
            if ($mainWidth > $targetW) {
                $targetH = (int) round(($mainHeight / $mainWidth) * $targetW);
                $variantImg = resampleGdImage($image, $targetW, $targetH, $mainWidth, $mainHeight);

                $vQuality = ($isPngOrDiagram && defined('IMG_WEBP_LOSSLESS')) ? IMG_WEBP_LOSSLESS : 80;
                if (saveWebpAtomic($variantImg, $variantPath, $vQuality)) {
                    $vSize = filesize($variantPath);

                    // Never save a variant larger than the original source input
                    if ($vSize > $srcSize) {
                        @unlink($variantPath);
                    } else {
                        $variants[$targetW] = [
                            'path'   => $variantPath,
                            'url'    => (defined('UPLOAD_URL_PATH') ? UPLOAD_URL_PATH : '/uploads/') . basename($variantPath),
                            'width'  => $targetW,
                            'height' => $targetH,
                            'size'   => $vSize,
                        ];
                    }
                }
                freeGdImage($variantImg);
            }
        }
    }

    freeGdImage($image);

    $publicUrl = (defined('UPLOAD_URL_PATH') ? UPLOAD_URL_PATH : '/uploads/') . basename($finalMainPath);

    return [
        'main_path'   => $finalMainPath,
        'main_url'    => $publicUrl,
        'variants'    => $variants,
        'width'       => $mainWidth,
        'height'      => $mainHeight,
        'mime'        => $finalMime,
        'size'        => filesize($finalMainPath),
        'is_original' => $isOriginalKept,
        'is_animated' => false,
    ];
}

/**
 * Deletes an uploaded image along with all its responsive variants (-480, -960)
 * and sibling WebP or original formats.
 *
 * @param  string      $reference       Filename or /uploads/... URL
 * @param  string|null $customUploadDir Optional custom upload directory
 * @return array<string> Array of deleted file paths
 */
function deleteImageWithVariants(string $reference, ?string $customUploadDir = null): array {
    $uploadDir = $customUploadDir ?? (defined('UPLOAD_DIR') ? UPLOAD_DIR : null);
    if (!$uploadDir || !is_dir($uploadDir)) {
        return [];
    }

    $realUploadDir = realpath($uploadDir);
    if (!$realUploadDir) {
        return [];
    }

    $path = parse_url(trim($reference), PHP_URL_PATH) ?: trim($reference);
    $basename = basename($path);

    // Extract core base without -480, -960, and extension
    // e.g. "abc123-480.webp" -> "abc123", "cover.jpg" -> "cover"
    if (!preg_match('/^([a-zA-Z0-9_\-]+?)(?:-(?:480|960))?\.(?:jpe?g|png|gif|webp)$/i', $basename, $m)) {
        return [];
    }

    $coreBase = $m[1];
    $candidates = [
        $coreBase . '.webp',
        $coreBase . '-960.webp',
        $coreBase . '-480.webp',
        $coreBase . '.jpg',
        $coreBase . '.jpeg',
        $coreBase . '.png',
        $coreBase . '.gif',
    ];

    // Include the original requested basename if somehow not covered
    if (!in_array($basename, $candidates, true)) {
        $candidates[] = $basename;
    }

    $deleted = [];
    foreach ($candidates as $cand) {
        $target = $uploadDir . DIRECTORY_SEPARATOR . $cand;
        $realTarget = realpath($target);

        if ($realTarget && is_file($realTarget)) {
            // Path traversal defense
            if (str_starts_with($realTarget, $realUploadDir . DIRECTORY_SEPARATOR)) {
                if (@unlink($realTarget)) {
                    $deleted[] = $realTarget;
                }
            }
        }
    }

    return $deleted;
}

/**
 * Checks if the media_assets table has width and height columns.
 * Caches result per request to avoid repeated metadata queries.
 *
 * @param  PDO $pdo
 * @return bool
 */
function mediaAssetsHasDimensions(PDO $pdo, bool $forceCheck = false): bool {
    static $hasDimensions = null;
    if ($hasDimensions !== null && !$forceCheck) {
        return $hasDimensions;
    }

    try {
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $stmt = $pdo->query("PRAGMA table_info(media_assets)");
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $colNames = array_column($columns, 'name');
            $hasDimensions = in_array('width', $colNames, true) && in_array('height', $colNames, true);
        } else {
            $stmt = $pdo->prepare("SHOW COLUMNS FROM media_assets LIKE 'width'");
            $stmt->execute();
            $hasDimensions = (bool) $stmt->fetch();
        }
    } catch (Throwable $e) {
        $hasDimensions = false;
    }

    return $hasDimensions;
}

/**
 * Records an uploaded image into media_assets registry without creating duplicate rows.
 * Degrades gracefully if width/height columns are not yet present.
 * NEVER stores dimensions in caption.
 *
 * @param  PDO   $pdo
 * @param  array $data Keys: ['file_name', 'file_path', 'file_size', 'mime_type', 'width', 'height', 'uploaded_by']
 * @param  bool  $forceCheck Whether to bypass cached column existence check
 * @return int|null Inserted or updated record ID
 */
function recordMediaAsset(PDO $pdo, array $data, bool $forceCheck = false): ?int {
    $fileName   = basename($data['file_path'] ?? $data['file_name'] ?? '');
    $filePath   = $data['file_path'] ?? ('/uploads/' . $fileName);
    $fileSize   = (int) ($data['file_size'] ?? 0);
    $mimeType   = $data['mime_type'] ?? '';
    $width      = isset($data['width']) ? (int) $data['width'] : null;
    $height     = isset($data['height']) ? (int) $data['height'] : null;
    $uploadedBy = !empty($data['uploaded_by']) ? (int) $data['uploaded_by'] : null;

    if ($fileName === '') {
        return null;
    }

    $hasDim = mediaAssetsHasDimensions($pdo, $forceCheck);

    try {
        // Check if row already exists for this file_path or file_name
        $checkStmt = $pdo->prepare("SELECT id FROM media_assets WHERE file_path = ? OR file_name = ? LIMIT 1");
        $checkStmt->execute([$filePath, $fileName]);
        $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            $id = (int) $existing['id'];
            if ($hasDim) {
                $upStmt = $pdo->prepare(
                    "UPDATE media_assets 
                     SET file_size = ?, mime_type = ?, width = ?, height = ?, updated_at = CURRENT_TIMESTAMP 
                     WHERE id = ?"
                );
                $upStmt->execute([$fileSize, $mimeType, $width, $height, $id]);
            } else {
                $upStmt = $pdo->prepare(
                    "UPDATE media_assets 
                     SET file_size = ?, mime_type = ?, updated_at = CURRENT_TIMESTAMP 
                     WHERE id = ?"
                );
                $upStmt->execute([$fileSize, $mimeType, $id]);
            }
            return $id;
        }

        // Insert new record
        if ($hasDim) {
            $inStmt = $pdo->prepare(
                "INSERT INTO media_assets (file_name, file_path, file_size, mime_type, width, height, uploaded_by, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)"
            );
            $inStmt->execute([$fileName, $filePath, $fileSize, $mimeType, $width, $height, $uploadedBy]);
        } else {
            $inStmt = $pdo->prepare(
                "INSERT INTO media_assets (file_name, file_path, file_size, mime_type, uploaded_by, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)"
            );
            $inStmt->execute([$fileName, $filePath, $fileSize, $mimeType, $uploadedBy]);
        }

        return (int) $pdo->lastInsertId();
    } catch (Throwable $e) {
        error_log('[recordMediaAsset] Database note: ' . $e->getMessage());
        return null;
    }
}
