<?php

namespace App\Tests\Service;

use App\Service\FileUploadService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class FileUploadServiceTest extends TestCase
{
    private string $uploadDir;

    protected function setUp(): void
    {
        $this->uploadDir = sys_get_temp_dir() . '/doraid_upload_test_' . uniqid('', true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->uploadDir)) {
            foreach (glob($this->uploadDir . '/*') as $file) {
                unlink($file);
            }
            rmdir($this->uploadDir);
        }
    }

    private function makePngUpload(string $name = 'photo.png'): UploadedFile
    {
        // PNG 1x1 minimal valide (suffisant pour guessExtension()/getimagesize(), pas besoin de l'extension GD)
        $bytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');
        $path  = sys_get_temp_dir() . '/' . uniqid('src_', true) . '.png';
        file_put_contents($path, $bytes);

        return new UploadedFile($path, $name, 'image/png', null, true);
    }

    public function testReplaceWithUniqueBasenamesProducesDistinctFilenames(): void
    {
        $service = new FileUploadService();

        $first  = $service->upload($this->makePngUpload(), $this->uploadDir, 'cover-' . uniqid('', true));
        $second = $service->replace($this->uploadDir, $first, $this->makePngUpload(), 'cover-' . uniqid('', true));

        $this->assertNotSame($first, $second);
    }

    public function testReplaceDeletesThePreviousFile(): void
    {
        $service = new FileUploadService();

        $first  = $service->upload($this->makePngUpload(), $this->uploadDir, 'cover-' . uniqid('', true));
        $this->assertFileExists($this->uploadDir . '/' . $first);

        $second = $service->replace($this->uploadDir, $first, $this->makePngUpload(), 'cover-' . uniqid('', true));

        $this->assertFileDoesNotExist($this->uploadDir . '/' . $first);
        $this->assertFileExists($this->uploadDir . '/' . $second);
    }

    public function testReplaceWithSameFixedBasenameOverwritesSilently(): void
    {
        // Documente le bug corrigé : un basename fixe produit toujours le même nom de
        // fichier, donc le contenu est bien remplacé sur disque mais l'URL ne change
        // jamais (cache navigateur). C'est pourquoi GuildController génère désormais un
        // basename unique à chaque upload plutôt que d'utiliser 'cover' en dur.
        $service = new FileUploadService();

        $first  = $service->upload($this->makePngUpload(), $this->uploadDir, 'cover');
        $second = $service->replace($this->uploadDir, $first, $this->makePngUpload(), 'cover');

        $this->assertSame($first, $second);
    }
}
