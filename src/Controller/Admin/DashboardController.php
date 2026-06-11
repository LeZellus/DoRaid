<?php

namespace App\Controller\Admin;

use App\Entity\Enigme;
use App\Entity\EnigmeTemplate;
use App\Entity\Raid;
use App\Entity\RaidTemplate;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Symfony\Component\HttpFoundation\Response;

#[AdminDashboard(routePath: '/admin', routeName: 'admin_dashboard')]
class DashboardController extends AbstractDashboardController
{
    public function index(): Response
    {
        return $this->render('admin/dashboard.html.twig');
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
        yield MenuItem::linkTo(RaidTemplate::class, 'Types de raid', 'fa fa-dragon');
        yield MenuItem::linkTo(EnigmeTemplate::class, 'Énigmes des templates', 'fa fa-puzzle-piece');

        yield MenuItem::section('Raids en cours');
        yield MenuItem::linkTo(Raid::class, 'Raids', 'fa fa-shield-halved');
        yield MenuItem::linkTo(Enigme::class, 'Énigmes', 'fa fa-question-circle');

        yield MenuItem::section();
        yield MenuItem::linkToUrl('← Retour au site', 'fa fa-arrow-left', '/');
    }
}
