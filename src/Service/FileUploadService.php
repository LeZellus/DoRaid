<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;

class FileUploadService
{
    private const MAX_PX   = 300;
    private const WEBP_IMG = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    public function upload(UploadedFile $file, string $directory, string $basename = '', int $maxPx = self::MAX_PX): string
    {
        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        $ext = strtolower($file->guessExtension() ?? 'bin');

        if (in_array($ext, self::WEBP_IMG, true) && function_exists('imagewebp')) {
            $filename = ($basename !== '' ? $basename : uniqid('', true)) . '.webp';
            $this->resizeToWebP($file->getPathname(), $directory . '/' . $filename, $maxPx);
            return $filename;
        }

        $filename = ($basename !== '' ? $basename : uniqid('', true)) . '.' . $ext;
        $file->move($directory, $filename);

        return $filename;
    }

    public function replace(string $directory, ?string $existing, UploadedFile $newFile, string $basename = '', int $maxPx = self::MAX_PX): string
    {
        if ($existing && file_exists($directory . '/' . $existing)) {
            unlink($directory . '/' . $existing);
        }

        return $this->upload($newFile, $directory, $basename, $maxPx);
    }

    private function resizeToWebP(string $src, string $dest, int $maxPx): void
    {
        $info = @getimagesize($src);
        if (!$info) {
            copy($src, $dest);
            return;
        }

        [$w, $h, $type] = $info;

        $gdSrc = match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($src),
            IMAGETYPE_PNG  => @imagecreatefrompng($src),
            IMAGETYPE_GIF  => @imagecreatefromgif($src),
            IMAGETYPE_WEBP => @imagecreatefromwebp($src),
            default        => null,
        };

        if (!$gdSrc) {
            copy($src, $dest);
            return;
        }

        [$newW, $newH] = $this->scaleDimensions($w, $h, $maxPx);

        $gdDst = imagecreatetruecolor($newW, $newH);

        // Preserve PNG/WebP transparency
        imagealphablending($gdDst, false);
        imagesavealpha($gdDst, true);
        $transparent = imagecolorallocatealpha($gdDst, 0, 0, 0, 127);
        imagefilledrectangle($gdDst, 0, 0, $newW, $newH, $transparent);

        imagecopyresampled($gdDst, $gdSrc, 0, 0, 0, 0, $newW, $newH, $w, $h);
        imagewebp($gdDst, $dest, 85);

        imagedestroy($gdSrc);
        imagedestroy($gdDst);
    }

    /** @return array{int, int} */
    private function scaleDimensions(int $w, int $h, int $max): array
    {
        if ($w <= $max && $h <= $max) {
            return [$w, $h];
        }

        if ($w >= $h) {
            return [$max, (int) round($h * $max / $w)];
        }

        return [(int) round($w * $max / $h), $max];
    }
}
