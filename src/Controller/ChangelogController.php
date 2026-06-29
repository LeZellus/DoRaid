<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ChangelogController extends AbstractController
{
    #[Route('/mises-a-jour', name: 'app_changelog')]
    public function index(): Response
    {
        $entries = [
            [
                'version' => '1.3.0',
                'date'    => '29 juin 2026',
                'latest'  => true,
                'changes' => [
                    ['type' => 'new',  'text' => 'Système de feedback : signalez un bug ou proposez une suggestion directement depuis le site'],
                    ['type' => 'new',  'text' => 'Page de mises à jour (vous y êtes !)'],
                    ['type' => 'new',  'text' => 'Champ Guildatons sur les personnages — visible dans les listes de raids et sur le dashboard'],
                    ['type' => 'new',  'text' => 'Rappel hebdomadaire par notification si les guildatons n\'ont pas été mis à jour depuis 7 jours'],
                    ['type' => 'fix',  'text' => 'Les raids terminés affichent désormais "Raid terminé" au lieu d\'un countdown'],
                    ['type' => 'fix',  'text' => 'Les candidatures en attente sont masquées sur les raids terminés'],
                    ['type' => 'impr', 'text' => 'Page personnages redesignée : vue compacte avec menu d\'actions ⋮'],
                    ['type' => 'impr', 'text' => 'Bouton Ko-fi flottant en bas à droite pour soutenir le projet'],
                    ['type' => 'impr', 'text' => 'Navigation : "Les guildes", "Encyclopédie", hauteur unifiée sur tous les boutons'],
                ],
            ],
            [
                'version' => '1.2.0',
                'date'    => '15 juin 2026',
                'latest'  => false,
                'changes' => [
                    ['type' => 'new',  'text' => 'Notifications en temps réel avec badge pulsant sur la cloche'],
                    ['type' => 'new',  'text' => 'Système de commentaires sur les raids (réservé aux participants)'],
                    ['type' => 'new',  'text' => 'Notes privées sur les membres de guilde (meneur / créateur de raid)'],
                    ['type' => 'impr', 'text' => 'Dashboard entièrement revu avec récap des raids et personnages'],
                    ['type' => 'fix',  'text' => 'Correction de l\'affichage des races de personnages sur mobile'],
                ],
            ],
            [
                'version' => '1.1.0',
                'date'    => '1er juin 2026',
                'latest'  => false,
                'changes' => [
                    ['type' => 'new',  'text' => 'Encyclopédie des raids : guides stratégiques par donjon'],
                    ['type' => 'new',  'text' => 'Énigmes associées aux templates de raids'],
                    ['type' => 'impr', 'text' => 'Rejoindre la liste d\'attente quand un raid est complet'],
                    ['type' => 'fix',  'text' => 'Le créateur d\'un raid ne peut plus se retirer de son propre raid'],
                ],
            ],
            [
                'version' => '1.0.0',
                'date'    => '20 mai 2026',
                'latest'  => false,
                'changes' => [
                    ['type' => 'new', 'text' => 'Lancement de Zeminal 🎉'],
                    ['type' => 'new', 'text' => 'Création et gestion de guildes multi-serveurs'],
                    ['type' => 'new', 'text' => 'Publication et candidature à des raids'],
                    ['type' => 'new', 'text' => 'Profils de personnages avec classe, niveau et optimisation'],
                    ['type' => 'new', 'text' => 'Connexion Discord'],
                ],
            ],
        ];

        return $this->render('changelog/index.html.twig', [
            'entries' => $entries,
        ]);
    }
}
