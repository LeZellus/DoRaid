<?php

namespace App\Controller;

use App\Entity\Character;
use App\Exception\BusinessRuleException;
use App\Form\CharacterEditType;
use App\Form\CharacterType;
use App\Repository\CharacterRepository;
use App\Service\CharacterService;
use App\Traits\BusinessRuleFlashTrait;
use App\Traits\CsrfGuardTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
#[Route('/personnages')]
class CharacterController extends AbstractController
{
    use BusinessRuleFlashTrait;
    use CsrfGuardTrait;

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
            $existing = $em->getRepository(Character::class)->findOneBy([
                'name'   => $character->getName(),
                'server' => $character->getServer(),
            ]);

            if ($existing) {
                $form->get('name')->addError(new FormError(
                    sprintf('Un personnage nommé "%s" existe déjà sur %s.', $character->getName(), $character->getServer()->getName())
                ));
            } else {
                $character->setUser($this->getUser());
                $em->persist($character);
                $em->flush();

                $this->addFlash('success', 'Personnage créé avec succès !');
                return $this->redirectToRoute('app_character_index');
            }
        }

        return $this->render('character/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{id}/modifier', name: 'app_character_edit', methods: ['GET', 'POST'])]
    public function edit(Character $character, Request $request, EntityManagerInterface $em, CharacterService $characterService): Response
    {
        if (!$character->belongsTo($this->getUser())) {
            throw $this->createAccessDeniedException();
        }

        $originalServerId = $character->getServer()->getId();

        $form = $this->createForm(CharacterEditType::class, $character);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $serverChanged = (int) $character->getServer()->getId() !== (int) $originalServerId;

            if ($serverChanged) {
                try {
                    $characterService->clearParticipationsForServerChange($character);
                    $this->addFlash('info', 'Changement de serveur : guilde, candidatures aux raids, commentaires et images d\'énigmes liés à l\'ancien serveur ont été retirés.');
                } catch (BusinessRuleException $e) {
                    $form->get('server')->addError(new FormError($e->getMessage()));
                }
            }

            if (!$form->get('server')->getErrors(true)->count()) {
                $em->flush();
                $this->addFlash('success', 'Personnage mis à jour.');
                return $this->redirectToRoute('app_character_index');
            }
        }

        return $this->render('character/edit.html.twig', [
            'form'      => $form,
            'character' => $character,
        ]);
    }

    #[Route('/{id}/supprimer', name: 'app_character_delete', methods: ['POST'])]
    public function delete(Character $character, CharacterService $characterService, Request $request): Response
    {
        if (!$character->belongsTo($this->getUser())) {
            throw $this->createAccessDeniedException();
        }

        $this->requireCsrfToken('delete_character_' . $character->getId(), $request);

        $this->handleBusinessRule(
            fn() => $characterService->deleteCharacter($character),
            'Personnage supprimé.'
        );

        return $this->redirectToRoute('app_character_index');
    }
}
