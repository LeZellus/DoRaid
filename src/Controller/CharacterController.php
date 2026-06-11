<?php

namespace App\Controller;

use App\Entity\Character;
use App\Form\CharacterType;
use App\Repository\CharacterRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
#[Route('/personnages')]
class CharacterController extends AbstractController
{
    #[Route('', name: 'app_character_index')]
    public function index(CharacterRepository $repo): Response
    {
        $characters = $repo->findByUser($this->getUser());

        $byServer = [];
        foreach ($characters as $character) {
            $byServer[$character->getServer()->getName()][] = $character;
        }

        return $this->render('character/index.html.twig', [
            'byServer' => $byServer,
        ]);
    }

    #[Route('/creer', name: 'app_character_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $character = new Character();
        $form = $this->createForm(CharacterType::class, $character);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $character->setUser($this->getUser());
            $em->persist($character);
            $em->flush();

            $this->addFlash('success', 'Personnage créé avec succès !');
            return $this->redirectToRoute('app_character_index');
        }

        return $this->render('character/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{id}/supprimer', name: 'app_character_delete', methods: ['POST'])]
    public function delete(Character $character, EntityManagerInterface $em, Request $request): Response
    {
        if ($character->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        if ($this->isCsrfTokenValid('delete_character_' . $character->getId(), $request->request->get('_token'))) {
            $em->remove($character);
            $em->flush();
            $this->addFlash('success', 'Personnage supprimé.');
        }

        return $this->redirectToRoute('app_character_index');
    }
}
