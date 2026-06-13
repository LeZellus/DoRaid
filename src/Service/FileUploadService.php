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

    public function replace(string $directory, ?string $existing, UploadedFile $newFile, string $basename = ''): string
    {
        if ($existing && file_exists($directory . '/' . $existing)) {
            unlink($directory . '/' . $existing);
        }

        return $this->upload($newFile, $directory, $basename);
    }
}
