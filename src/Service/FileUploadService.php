<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;

class FileUploadService
{
    public function upload(UploadedFile $file, string $directory, string $basename = ''): string
    {
        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }
        $ext      = $file->guessExtension() ?? 'bin';
        $filename = ($basename !== '' ? $basename : uniqid('', true)) . '.' . $ext;
        $file->move($directory, $filename);

        return $filename;
    }
}
