<?php
/**
 * SSMUNIE — Helpers uploads / images (B3).
 * Photos : validation MIME (finfo) + redimensionnement 400x400 / 200x200 (GD)
 *          + compression JPEG qualité 85 + hash SHA-256.
 * Fichiers stockés hors BDD (uploads/).
 */

declare(strict_types=1);

if (!function_exists('ssmunie_upload_root')) {
    function ssmunie_upload_root(): string
    {
        return dirname(__DIR__) . '/uploads';
    }
}

if (!function_exists('ssmunie_file_mime')) {
    /** MIME réel via finfo (détecte les MIME falsifiés). */
    function ssmunie_file_mime(string $path): string
    {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($path);
        return is_string($mime) ? $mime : '';
    }
}

if (!function_exists('ssmunie_allowed_image_mime')) {
    function ssmunie_allowed_image_mime(string $mime): bool
    {
        return in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true);
    }
}

if (!function_exists('ssmunie_save_photo')) {
    /**
     * Traite une image source → photo.jpg (400x400) + mini.jpg (200x200).
     * Centre-crop carré + JPEG qualité 85 + fond blanc.
     * Retourne ['ok'=>bool, 'error'=>string, 'hash'=>string, 'size'=>int].
     */
    function ssmunie_save_photo(string $srcPath, string $destPhoto, string $destMini): array
    {
        $info = @getimagesize($srcPath);
        if (!$info) {
            return ['ok' => false, 'error' => 'invalid_image'];
        }
        $mime = $info['mime'] ?? '';
        $img = null;
        switch ($mime) {
            case 'image/jpeg': $img = @imagecreatefromjpeg($srcPath); break;
            case 'image/png':  $img = @imagecreatefrompng($srcPath); break;
            case 'image/webp': $img = @imagecreatefromwebp($srcPath); break;
            case 'image/gif':  $img = @imagecreatefromgif($srcPath); break;
            default: return ['ok' => false, 'error' => 'unsupported_image'];
        }
        if (!$img) {
            return ['ok' => false, 'error' => 'invalid_image'];
        }

        $srcW = imagesx($img);
        $srcH = imagesy($img);
        $size = min($srcW, $srcH);
        $sx   = (int) floor(($srcW - $size) / 2);
        $sy   = (int) floor(($srcH - $size) / 2);

        $render = function (int $w, int $h, string $dst) use ($img, $sx, $sy, $size): bool {
            $canvas = imagecreatetruecolor($w, $h);
            $white  = imagecolorallocate($canvas, 255, 255, 255);
            imagefill($canvas, 0, 0, $white);
            imagecopyresampled($canvas, $img, 0, 0, $sx, $sy, $w, $h, $size, $size);
            $ok = imagejpeg($canvas, $dst, 85);
            imagedestroy($canvas);
            return (bool) $ok;
        };

        if (!@$render(400, 400, $destPhoto) || !@$render(200, 200, $destMini)) {
            imagedestroy($img);
            return ['ok' => false, 'error' => 'resize_failed'];
        }
        imagedestroy($img);

        $hash = hash_file('sha256', $destPhoto) ?: null;
        $sizeBytes = (int) @filesize($destPhoto);
        return ['ok' => true, 'error' => '', 'hash' => $hash, 'size' => $sizeBytes];
    }
}
