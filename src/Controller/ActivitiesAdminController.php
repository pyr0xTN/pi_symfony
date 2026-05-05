<?php

namespace App\Controller;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class ActivitiesAdminController extends AbstractController
{
    #[Route('/activities/{id}/admin-delete', name: 'app_activities_admin_delete', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function adminDeleteActivity(int $id, Connection $connection, MailerInterface $mailer): Response
    {
        $activityOwner = $connection->fetchAssociative(
            "SELECT a.idActivite, a.titre,
                    u.email AS guide_email,
                    COALESCE(NULLIF(TRIM(CONCAT(COALESCE(u.name, ''), ' ', COALESCE(u.last_name, ''))), ''), u.username, 'Guide') AS guide_name
             FROM activite a
             LEFT JOIN `user` u ON u.id = a.idGuide
             WHERE a.idActivite = ?",
            [$id],
            [ParameterType::INTEGER]
        );

        if ($activityOwner === false) {
            $this->addFlash('error', 'Activity not found.');
            return $this->redirectToRoute('app_activities');
        }

        $activityTitle = trim((string) ($activityOwner['titre'] ?? 'Activity'));
        $guideEmail = trim((string) ($activityOwner['guide_email'] ?? ''));
        $guideName = trim((string) ($activityOwner['guide_name'] ?? 'Guide'));

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
            if ($guideEmail !== '') {
                try {
                    $email = (new Email())
                        ->from((string) ($_ENV['EMAIL_FROM'] ?? $_SERVER['EMAIL_FROM'] ?? 'noreply@rehletna.tn'))
                        ->to($guideEmail)
                        ->subject('Your activity was removed by admin')
                        ->text(sprintf(
                            "Hello %s,\n\nYour activity \"%s\" has been deleted by an administrator.\n\nIf you think this is a mistake, please contact support.\n\nRehletna Team",
                            $guideName,
                            $activityTitle !== '' ? $activityTitle : 'Activity'
                        ));

                    $mailer->send($email);
                    $this->addFlash('success', sprintf('The guide will receive a delete email on this address: %s', $guideEmail));
                } catch (\Throwable) {
                    $this->addFlash('warning', 'Activity deleted, but email notification could not be sent to the guide.');
                }
            } else {
                $this->addFlash('warning', 'Activity deleted, but no guide email address was found.');
            }

            $this->addFlash('success', 'Activity deleted successfully.');
        } else {
            $this->addFlash('error', 'Unable to delete activity.');
        }

        return $this->redirectToRoute('app_activities');
    }
}
