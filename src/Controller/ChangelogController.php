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
                'version' => '1.4.0',
                'date'    => '29 juin 2026',
                'latest'  => true,
                'changes' => [
                    ['type' => 'new',  'text' => 'Système de feedback intégré — signalez un bug ou proposez une suggestion depuis le menu ou le footer'],
                    ['type' => 'new',  'text' => 'Page de mises à jour (vous y êtes !)'],
                    ['type' => 'fix',  'text' => 'Les raids terminés affichent "Raid terminé" au lieu d\'un countdown ou "Fin à HH:MM"'],
                    ['type' => 'fix',  'text' => 'Les candidatures en attente et la section "Intéressés" sont masquées sur les raids terminés'],
                    ['type' => 'fix',  'text' => 'Image des guildatons intégrée et visible sur toutes les pages'],
                ],
            ],
            [
                'version' => '1.3.0',
                'date'    => 'Juin 2026',
                'latest'  => false,
                'changes' => [
                    ['type' => 'new',  'text' => 'Champ Guildatons sur les personnages — renseignez votre investissement et suivez celui des autres'],
                    ['type' => 'new',  'text' => 'Guildatons visibles dans les listes de participants et d\'intéressés des raids'],
                    ['type' => 'new',  'text' => 'Rappel hebdomadaire par notification si les guildatons n\'ont pas été mis à jour depuis 7 jours'],
                    ['type' => 'new',  'text' => 'Bouton Ko-fi flottant pour soutenir le projet, lien dans la navbar et le footer'],
                    ['type' => 'new',  'text' => 'Compte Twitter / X @zeminalraid dans le footer'],
                    ['type' => 'impr', 'text' => 'Page personnages redesignée : vue liste compacte avec menu d\'actions ⋮ (modifier, quitter guilde, supprimer)'],
                    ['type' => 'impr', 'text' => 'Navigation unifiée : hauteur h-9 sur tous les boutons, "Les guildes", "Encyclopédie"'],
                    ['type' => 'fix',  'text' => 'Les guildatons affichent "N/A" quand ils ne sont pas renseignés (au lieu d\'être masqués)'],
                    ['type' => 'fix',  'text' => 'Animation Ko-fi sans box-shadow (GPU only, plus fluide)'],
                ],
            ],
            [
                'version' => '1.2.0',
                'date'    => 'Juin 2026',
                'latest'  => false,
                'changes' => [
                    ['type' => 'new',  'text' => 'Notifications en temps réel avec badge pulsant sur la cloche'],
                    ['type' => 'new',  'text' => 'Notes privées sur les membres de guilde (meneur / organisateur)'],
                    ['type' => 'new',  'text' => 'Rôle "organisateur de raids" assignable par le meneur'],
                    ['type' => 'new',  'text' => 'Déplacer un participant vers un autre raid de la guilde'],
                    ['type' => 'new',  'text' => 'Transférer le rôle de créateur d\'un raid à un autre membre'],
                    ['type' => 'new',  'text' => 'Remettre un participant accepté en liste d\'attente'],
                    ['type' => 'new',  'text' => 'Un participant peut se retirer lui-même d\'un raid'],
                    ['type' => 'new',  'text' => 'Scroll automatique vers le dernier commentaire à l\'ouverture'],
                    ['type' => 'impr', 'text' => 'Notifications dans un conteneur scrollable avec chargement par page'],
                    ['type' => 'impr', 'text' => 'Menu ⋮ regroupant toutes les actions sur un participant (accepter, refuser, déplacer…)'],
                    ['type' => 'fix',  'text' => 'Correction des boutons en double dans le menu d\'actions des participants'],
                    ['type' => 'fix',  'text' => 'Bouton "Se retirer" masqué pour le créateur du raid'],
                ],
            ],
            [
                'version' => '1.1.0',
                'date'    => 'Mai – juin 2026',
                'latest'  => false,
                'changes' => [
                    ['type' => 'new',  'text' => 'Encyclopédie des raids : guides stratégiques par donjon'],
                    ['type' => 'new',  'text' => 'Système de candidature avec liste d\'intéressés (au lieu de rejoindre directement)'],
                    ['type' => 'new',  'text' => 'Candidature possible même si le raid est complet (liste d\'attente)'],
                    ['type' => 'new',  'text' => 'Bouton /w pour copier le pseudonyme en jeu'],
                    ['type' => 'new',  'text' => 'Page membres de guilde dédiée avec rôles, niveaux et options de gestion'],
                    ['type' => 'new',  'text' => 'Description de guilde éditable par le meneur'],
                    ['type' => 'new',  'text' => 'Le meneur peut exclure des membres et supprimer la guilde'],
                    ['type' => 'new',  'text' => 'URLs traduites en français sur tout le site'],
                    ['type' => 'impr', 'text' => 'Pages guildes colorisées selon le serveur'],
                    ['type' => 'impr', 'text' => 'Filtrage de la liste des guildes par serveur du personnage'],
                    ['type' => 'fix',  'text' => 'Slug de guilde immuable — l\'URL ne change plus si le nom est modifié'],
                    ['type' => 'fix',  'text' => 'Image de guilde affichée en avatar circulaire'],
                ],
            ],
            [
                'version' => '1.0.0',
                'date'    => 'Mai 2026',
                'latest'  => false,
                'changes' => [
                    ['type' => 'new', 'text' => 'Lancement de Zeminal 🎉'],
                    ['type' => 'new', 'text' => 'Connexion via Discord OAuth'],
                    ['type' => 'new', 'text' => 'Création et gestion de guildes multi-serveurs'],
                    ['type' => 'new', 'text' => 'Publication et candidature à des raids'],
                    ['type' => 'new', 'text' => 'Profils de personnages avec classe, niveau et optimisation'],
                    ['type' => 'new', 'text' => 'Système d\'énigmes associées aux raids'],
                    ['type' => 'new', 'text' => 'Dashboard personnalisé avec récap des raids et activité'],
                ],
            ],
        ];

        return $this->render('changelog/index.html.twig', [
            'entries' => $entries,
        ]);
    }
}
