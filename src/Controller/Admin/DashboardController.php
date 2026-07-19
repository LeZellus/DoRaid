<?php

namespace App\Controller\Admin;

use App\Repository\FeedbackRepository;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Symfony\Component\HttpFoundation\Response;

#[AdminDashboard(routePath: '/admin', routeName: 'admin_dashboard')]
class DashboardController extends AbstractDashboardController
{
    public function __construct(private readonly FeedbackRepository $feedbackRepo) {}

    public function index(): Response
    {
        return $this->render('admin/dashboard.html.twig', [
            'openFeedbackCount' => $this->feedbackRepo->countOpen(),
        ]);
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('DoRaid — Administration')
            ->setFaviconPath('favicon.ico')
            ->renderContentMaximized();
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('Tableau de bord', 'fa fa-home');

        yield MenuItem::section('Contenu');
        yield MenuItem::linkTo(RaidTemplateCrudController::class, 'Types de raid', 'fa fa-dragon')->setAction('index');
        yield MenuItem::linkTo(EnigmeTemplateCrudController::class, 'Énigmes des templates', 'fa fa-puzzle-piece')->setAction('index');

        yield MenuItem::section('Répartiteur de loot');
        yield MenuItem::linkTo(GemCrudController::class, 'Gemmes', 'fa fa-gem')->setAction('index');
        yield MenuItem::linkTo(MobCrudController::class, 'Mobs', 'fa fa-spider')->setAction('index');
        yield MenuItem::linkTo(SalleCrudController::class, 'Salles', 'fa fa-door-open')->setAction('index');
        yield MenuItem::linkTo(SalleCompositionCrudController::class, 'Compositions de salle', 'fa fa-layer-group')->setAction('index');

        yield MenuItem::section('Modération');
        yield MenuItem::linkTo(UserCrudController::class, 'Membres', 'fa fa-users')->setAction('index');
        yield MenuItem::linkTo(GuildCrudController::class, 'Guildes', 'fa fa-shield')->setAction('index');
        yield MenuItem::linkTo(RaidCrudController::class, 'Raids', 'fa fa-shield-halved')->setAction('index');
        yield MenuItem::linkTo(FeedbackCrudController::class, 'Feedbacks', 'fa fa-comments')->setAction('index');

        yield MenuItem::section();
        yield MenuItem::linkToUrl('← Retour au site', 'fa fa-arrow-left', '/');
    }
}
