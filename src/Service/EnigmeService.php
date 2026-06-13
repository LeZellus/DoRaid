<?php

namespace App\Service;

use App\Entity\Character;
use App\Entity\Enigme;
use App\Entity\EnigmeComment;
use App\Entity\EnigmeImage;
use App\Exception\BusinessRuleException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class EnigmeService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly FileUploadService $fileUpload,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {}

    public function uploadImage(Enigme $enigme, UploadedFile $file, Character $addedBy): void
    {
        if ($enigme->getImages()->count() >= 20) {
            throw new BusinessRuleException('Limite de 20 images par énigme atteinte.');
        }

        $uploadDir = $this->projectDir . '/public/uploads/enigmes/' . $enigme->getId();
        $filename  = $this->fileUpload->upload($file, $uploadDir, uniqid('img_', true));

        $this->em->persist(
            (new EnigmeImage())->setEnigme($enigme)->setFilePath($filename)->setAddedBy($addedBy)
        );
        $enigme->touch();
        $this->em->flush();
        $this->em->refresh($enigme);
    }

    public function addComment(Enigme $enigme, string $content, Character $author): void
    {
        $this->em->persist(
            (new EnigmeComment())->setEnigme($enigme)->setContent($content)->setAuthor($author)
        );
        $enigme->touch();
        $this->em->flush();
        $this->em->refresh($enigme);
    }

    public function toggleResolved(Enigme $enigme): bool
    {
        $enigme->setResolved(!$enigme->isResolved());
        $enigme->touch();
        $this->em->flush();

        return $enigme->isResolved();
    }
}
