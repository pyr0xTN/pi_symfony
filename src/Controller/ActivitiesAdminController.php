<?php

namespace App\Controller;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class ActivitiesAdminController extends AbstractController
{
    #[Route('/activities/{id}/admin-delete', name: 'app_activities_admin_delete', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function adminDeleteActivity(int $id, Connection $connection): Response
    {
        $exists = $connection->fetchOne(
            'SELECT idActivite FROM activite WHERE idActivite = ?',
            [$id],
            [ParameterType::INTEGER]
        );

        if ($exists === false) {
            $this->addFlash('error', 'Activity not found.');
            return $this->redirectToRoute('app_activities');
        }

        try {
            $deletedRows = $connection->executeStatement(
                'DELETE FROM activite WHERE idActivite = ?',
                [$id],
                [ParameterType::INTEGER]
            );
        } catch (\Throwable) {
            $this->addFlash('error', 'Unable to delete activity.');
            return $this->redirectToRoute('app_activities');
        }

        if ($deletedRows > 0) {
            $this->addFlash('success', 'Activity deleted successfully.');
        } else {
            $this->addFlash('error', 'Unable to delete activity.');
        }

        return $this->redirectToRoute('app_activities');
    }
}
