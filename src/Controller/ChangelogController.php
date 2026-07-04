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
                'version' => '1.6.0',
                'date'    => '4 juillet 2026',
                'latest'  => true,
                'changes' => [
                    ['type' => 'impr', 'text' => 'Police du site changée pour Poppins, désormais auto-hébergée (plus d\'appel à Google Fonts — meilleure confidentialité et performance)'],
                    ['type' => 'impr', 'text' => 'Liste des participants d\'un raid simplifiée — le pseudo du compte n\'est plus affiché, le nom du personnage suffit'],
                    ['type' => 'impr', 'text' => 'Sélecteur de personnage pour candidater à un raid simplifié — n\'affiche plus que le nom du personnage'],
                    ['type' => 'impr', 'text' => 'Badges "Guilde", "Externe" et "vous" harmonisés avec le même style que les badges d\'optimisation'],
                    ['type' => 'fix',  'text' => 'Hauteur des boutons et des menus déroulants harmonisée sur tout le site'],
                    ['type' => 'fix',  'text' => 'Bouton "Commenter" sur la page de raid remis au style standard du site'],
                ],
            ],
            [
                'version' => '1.5.0',
                'date'    => '3 juillet 2026',
                'latest'  => false,
                'changes' => [
                    ['type' => 'new',  'text' => 'Nouvelle identité visuelle inspirée de l\'univers Dofus — accent émeraude, boutons, badges et alertes translucides au lieu des aplats pleins'],
                    ['type' => 'impr', 'text' => 'Boutons et cards harmonisés sur un même composant partagé dans toutes les pages du site'],
                    ['type' => 'new',  'text' => 'Bannières Open Graph régénérées avec les couleurs du nouveau thème'],
                    ['type' => 'new',  'text' => 'Page "Ce raid n\'existe plus" contextualisée à la place de l\'erreur 404 générique'],
                    ['type' => 'fix',  'text' => 'Curseur "pointeur" manquant sur les boutons'],
                ],
            ],
            [
                'version' => '1.4.0',
                'date'    => '29 juin 2026',
                'latest'  => false,
                'changes' => [
                    ['type' => 'new',  'text' => 'Système de feedback intégré — signalez un bug ou proposez une suggestion depuis le menu avatar ou le footer'],
                    ['type' => 'new',  'text' => 'Page de mises à jour (vous y êtes !)'],
                    ['type' => 'fix',  'text' => 'Les raids terminés affichent "Raid terminé" au lieu d\'un countdown ou "Fin à HH:MM"'],
                    ['type' => 'fix',  'text' => 'Les candidatures en attente et la section "Intéressés" sont masquées sur les raids terminés'],
                    ['type' => 'fix',  'text' => 'Image des guildatons commitée et visible sur toutes les pages'],
                ],
            ],
            [
                'version' => '1.3.0',
                'date'    => 'Juin 2026',
                'latest'  => false,
                'changes' => [
                    ['type' => 'new',  'text' => 'Champ Guildatons sur les personnages — renseignez votre investissement en guilde'],
                    ['type' => 'new',  'text' => 'Guildatons affichés dans les listes de participants et d\'intéressés des raids'],
                    ['type' => 'new',  'text' => 'Section guildatons sur le dashboard avec état de mise à jour par personnage'],
                    ['type' => 'new',  'text' => 'Rappel hebdomadaire par notification si les guildatons n\'ont pas été mis à jour depuis 7 jours'],
                    ['type' => 'new',  'text' => 'Bouton Ko-fi flottant en bas à droite pour soutenir le projet, lien dans la navbar et le footer'],
                    ['type' => 'new',  'text' => 'Compte Twitter / X @zeminalraid dans le footer'],
                    ['type' => 'impr', 'text' => 'Page personnages redesignée : vue liste compacte avec menu d\'actions ⋮ (modifier, quitter guilde, supprimer)'],
                    ['type' => 'impr', 'text' => 'Navigation unifiée : hauteur h-9 sur tous les boutons, renommage "Les guildes" et "Encyclopédie"'],
                    ['type' => 'fix',  'text' => 'Les guildatons affichent "N/A" quand non renseignés au lieu d\'être masqués'],
                    ['type' => 'fix',  'text' => 'Animation Ko-fi optimisée — suppression du box-shadow des keyframes pour des animations GPU uniquement'],
                ],
            ],
            [
                'version' => '1.2.0',
                'date'    => 'Juin 2026',
                'latest'  => false,
                'changes' => [
                    ['type' => 'new',  'text' => 'Notifications en temps réel avec badge pulsant sur la cloche, défilement et chargement par page'],
                    ['type' => 'new',  'text' => 'Notes privées sur les membres de guilde, accessibles par le meneur et les organisateurs'],
                    ['type' => 'new',  'text' => 'Rôle "organisateur de raids" assignable par le meneur de guilde'],
                    ['type' => 'new',  'text' => 'Déplacer un participant vers un autre raid de la guilde'],
                    ['type' => 'new',  'text' => 'Transférer le rôle de créateur d\'un raid à un autre participant'],
                    ['type' => 'new',  'text' => 'Remettre un participant accepté en liste d\'attente'],
                    ['type' => 'new',  'text' => 'Un participant peut se retirer lui-même d\'un raid'],
                    ['type' => 'new',  'text' => 'Scroll automatique vers le dernier commentaire à l\'ouverture'],
                    ['type' => 'impr', 'text' => 'Menu ⋮ regroupant toutes les actions sur un participant (accepter, refuser, déplacer, remettre en attente…)'],
                    ['type' => 'impr', 'text' => 'Refactoring : logique métier extraite des contrôleurs vers des services dédiés'],
                    ['type' => 'fix',  'text' => 'Correction des boutons en double dans le menu d\'actions des participants'],
                    ['type' => 'fix',  'text' => 'Correction du popup menu et du bouton "Se retirer", affichage du compteur de raids terminés'],
                    ['type' => 'fix',  'text' => 'Correction des balises canonical / Open Graph et ajout des balises noindex manquantes'],
                    ['type' => 'fix',  'text' => 'Correction d\'une redirection ouverte et de plusieurs risques d\'erreur 500'],
                    ['type' => 'fix',  'text' => 'Sécurité : cookie_httponly explicite, en-têtes HTTP de sécurité, masquage X-Powered-By'],
                ],
            ],
            [
                'version' => '1.1.0',
                'date'    => 'Mai – juin 2026',
                'latest'  => false,
                'changes' => [
                    ['type' => 'new',  'text' => 'Guide complet pour le raid Sanctuaire des Jardins Éternels'],
                    ['type' => 'new',  'text' => 'Crédits Dofus pour les Noobs intégrés sur les guides de raid'],
                    ['type' => 'new',  'text' => 'Bannière Open Graph dédiée aux pages de guide'],
                    ['type' => 'new',  'text' => 'Indexation SEO des guides et de l\'encyclopédie'],
                    ['type' => 'new',  'text' => 'Panneau d\'administration EasyAdmin : gestion des membres, guildes, types de raids'],
                    ['type' => 'new',  'text' => 'Clôture automatique des raids dont la durée estimée est dépassée'],
                    ['type' => 'new',  'text' => 'Candidature possible même si le raid est complet (liste d\'attente)'],
                    ['type' => 'new',  'text' => 'Bouton /w pour copier le pseudonyme en jeu depuis les listes de participants'],
                    ['type' => 'new',  'text' => 'Page membres de guilde dédiée avec rôles, niveaux et options de gestion'],
                    ['type' => 'new',  'text' => 'Description de guilde éditable par le meneur'],
                    ['type' => 'new',  'text' => 'Le meneur peut exclure des membres et supprimer la guilde'],
                    ['type' => 'new',  'text' => 'URLs du site traduites en français'],
                    ['type' => 'new',  'text' => 'Filtrage de la liste des guildes par serveur, avec recherche et vue en grille'],
                    ['type' => 'new',  'text' => 'Empêche la création d\'un raid avec une date passée'],
                    ['type' => 'impr', 'text' => 'Pages guildes colorisées selon le serveur pour réduire la monotonie'],
                    ['type' => 'impr', 'text' => 'Dashboard utilisateur complet remplaçant la page d\'accueil générique'],
                    ['type' => 'impr', 'text' => 'Guide raid : sommaire sticky et navigation entre sections corrigée'],
                    ['type' => 'impr', 'text' => 'Ouverture des guides dans un nouvel onglet depuis la page de raid'],
                    ['type' => 'fix',  'text' => 'Slug de guilde immuable — l\'URL ne change plus si le nom est modifié'],
                    ['type' => 'fix',  'text' => 'Image de guilde affichée en avatar circulaire (au lieu de bannière)'],
                    ['type' => 'fix',  'text' => 'Mise à jour de l\'image de guilde sans rechargement de la page'],
                    ['type' => 'fix',  'text' => 'Édition de la classe et du serveur d\'un personnage depuis la fiche'],
                    ['type' => 'fix',  'text' => 'Classe Forgelance manquante ajoutée au jeu de données initial'],
                ],
            ],
            [
                'version' => '1.0.0',
                'date'    => 'Mai 2026',
                'latest'  => false,
                'changes' => [
                    ['type' => 'new', 'text' => 'Lancement de Zeminal 🎉'],
                    ['type' => 'new', 'text' => 'Connexion via Discord OAuth'],
                    ['type' => 'new', 'text' => 'Création et gestion de guildes multi-serveurs avec image et description'],
                    ['type' => 'new', 'text' => 'Publication de raids avec template, date, capacité et accès privé/public'],
                    ['type' => 'new', 'text' => 'Système de candidature avec acceptation / refus des participants'],
                    ['type' => 'new', 'text' => 'Profils de personnages avec classe, niveau et niveau d\'optimisation'],
                    ['type' => 'new', 'text' => 'Système d\'énigmes associées aux raids pour tester la préparation des joueurs'],
                    ['type' => 'new', 'text' => 'Commentaires sur les raids réservés aux participants'],
                    ['type' => 'new', 'text' => 'Compteur de raids terminés par personnage affiché dans les listes'],
                ],
            ],
        ];

        return $this->render('changelog/index.html.twig', [
            'entries' => $entries,
        ]);
    }
}
