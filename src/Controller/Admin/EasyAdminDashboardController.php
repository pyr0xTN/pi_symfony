<?php

namespace App\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AdminDashboard(routePath: '/admin', routeName: 'app_easyadmin_dashboard')]
#[IsGranted('ROLE_ADMIN')]
class EasyAdminDashboardController extends AbstractDashboardController
{
    public function __construct(private AdminUrlGenerator $adminUrlGenerator)
    {
    }

    public function index(): Response
    {
        $url = $this->adminUrlGenerator
            ->setController(UserCrudController::class)
            ->generateUrl();

        return $this->redirect($url);
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()->setTitle('Rehletna Admin');
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('EasyAdmin', 'fa fa-gauge');
        yield MenuItem::linkToRoute('Users', 'fa fa-users', 'app_easyadmin_dashboard_user_index');
        yield MenuItem::linkToRoute('Custom Dashboard', 'fa fa-chart-line', 'app_dashboard');
        yield MenuItem::linkToRoute('Back To Main Page', 'fa fa-house', 'app_mainpage');
        yield MenuItem::linkToLogout('Logout', 'fa fa-sign-out-alt');
    }
}
