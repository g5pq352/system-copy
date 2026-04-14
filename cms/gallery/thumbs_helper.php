<?php
require_once realpath(__DIR__ . '/../..') . '/config/config.php';

// thumbs_helper.php - 權限優化與縮圖搬移回傳值修正版
// --------------------------------------------------
// 統一處理 uploads 路徑、中央縮圖、垃圾桶結構
// --------------------------------------------------

// 統一使用的權限模式
const DIR_PERM = 0775; 

$UPLOAD_BASE = realpath($rootPath . '/uploads');
if ($UPLOAD_BASE === false) {
    $UPLOAD_BASE = $rootPath . '/uploads';
}

// 【資源隔離】如果是在子網站環境，則將上傳路徑指向獨立子目錄
if (defined('SITE_ID') && SITE_ID > 0) {
    $UPLOAD_BASE .= '/site_' . SITE_ID;
}

if (!is_dir($UPLOAD_BASE)) {
    mkdir($UPLOAD_BASE, DIR_PERM, true);
}

// 中央縮圖根目錄： uploads/thumbs
$THUMBS_BASE = $UPLOAD_BASE . '/thumbs';
if (!is_dir($THUMBS_BASE)) {
    mkdir($THUMBS_BASE, DIR_PERM, true);
}

// 垃圾桶根目錄： uploads/_trash
$TRASH_BASE = $UPLOAD_BASE . '/_trash';
if (!is_dir($TRASH_BASE)) {
    mkdir($TRASH_BASE, DIR_PERM, true);
}

/**
 * 統一把相對路徑整理乾淨
 */
function normalize_rel_path(string $path): string
{
    $path = str_replace('\\', '/', $path);
    $path = preg_replace('#/+#', '/', $path);
    $path = str_replace('..', '', $path);
    return trim($path, '/');
}

/**
 * 取得原圖的絕對路徑
 */
function original_abs(string $rel): string
{
    global $UPLOAD_BASE;
    $rel = normalize_rel_path($rel);
    return rtrim($UPLOAD_BASE, '/') . '/' . $rel;
}

/**
 * 取得中央縮圖的絕對路徑
 */
function thumb_abs(string $rel): string
{
    global $THUMBS_BASE;
    $rel = normalize_rel_path($rel);
    return rtrim($THUMBS_BASE, '/') . '/' . $rel;
}

/**
 * 確保某個檔案所在的資料夾存在
 */
function ensure_dir_for(string $filePath): void
{
    $dir = dirname($filePath);
    if (!is_dir($dir)) {
        // 使用 DIR_PERM 常數
        mkdir($dir, DIR_PERM, true);
    }
}

/**
 * 為某個「原圖」建立中央縮圖 (不變)
 */
function create_thumb_for(string $srcAbs, string $relPath, int $maxW = 400): void
{
    $srcAbsReal = realpath($srcAbs) ?: $srcAbs;
    if (!is_file($srcAbsReal)) return;

    [$w, $h] = @getimagesize($srcAbsReal) ?: [0, 0];
    if ($w <= 0) return;

    if ($w <= $maxW) {
        $newW = $w;
        $newH = $h;
    } else {
        $ratio = $maxW / $w;
        $newW  = $maxW;
        $newH  = (int)($h * $ratio);
    }


    $ext = strtolower(pathinfo($srcAbsReal, PATHINFO_EXTENSION));

    switch ($ext) {
        case 'jpg':
        case 'jpeg':
            $img = @imagecreatefromjpeg($srcAbsReal);
            break;
        case 'png':
            $img = @imagecreatefrompng($srcAbsReal);
            break;
        case 'gif':
            $img = @imagecreatefromgif($srcAbsReal);
            break;
        case 'webp':
            $img = function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($srcAbsReal) : null;
            break;
        default:
            $img = null;
    }

    if (!$img) return;

    $thumb = imagecreatetruecolor($newW, $newH);
    if ($ext === 'png' && function_exists('imagealphablending') && function_exists('imagesavealpha')) {
        imagealphablending($thumb, false);
        imagesavealpha($thumb, true);
        $transparent = imagecolorallocatealpha($thumb, 255, 255, 255, 127);
        imagefilledrectangle($thumb, 0, 0, $newW, $newH, $transparent);
    }

    imagecopyresampled($thumb, $img, 0, 0, 0, 0, $newW, $newH, $w, $h);

    $thumbAbs = thumb_abs($relPath);
    ensure_dir_for($thumbAbs);

    switch ($ext) {
        case 'jpg':
        case 'jpeg':
            imagejpeg($thumb, $thumbAbs, 85);
            break;
        case 'png':
            imagepng($thumb, $thumbAbs);
            break;
        case 'gif':
            imagegif($thumb, $thumbAbs);
            break;
        case 'webp':
            if (function_exists('imagewebp')) {
                imagewebp($thumb, $thumbAbs, 85);
            }
            break;
    }

    @imagedestroy($thumb);
    @imagedestroy($img);
}

/**
 * 刪除中央縮圖（若存在）
 */
function delete_thumb_for(string $relPath): void
{
    $t = thumb_abs($relPath);
    if (is_file($t)) {
        @unlink($t);
    }
}

/**
 * 搬移中央縮圖：oldRel → newRel
 * @return bool 成功或失敗
 */
function move_thumb(string $oldRel, string $newRel): bool
{
    $oldAbs = thumb_abs($oldRel);
    // 原始縮圖不存在，視為成功
    if (!is_file($oldAbs)) return true; 

    $newAbs = thumb_abs($newRel);
    ensure_dir_for($newAbs);
    
    // 檢查 rename 結果
    if (!rename($oldAbs, $newAbs)) {
        error_log("Error moving thumbnail: From {$oldAbs} to {$newAbs}");
        return false;
    }
    return true;
}

/**
 * 判斷副檔名是否為圖片
 */
function is_image_ext(string $ext): bool
{
    $ext = strtolower($ext);
    return in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true);
}