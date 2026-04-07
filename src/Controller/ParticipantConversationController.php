<?php

namespace App\Controller;

use App\Entity\Conversation;
use App\Entity\ParticipantConversation;
use App\Repository\ParticipantConversationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/participants')]
class ParticipantController extends AbstractController
{
    /**
     * Replaces 'quitterConversation'
     * Logic: Soft-delete a participant by setting active to false and adding a leave date.
     */
    #[Route('/leave/{idConversation}', name: 'app_participant_leave', methods: ['POST'])]
    public function leave(
        Conversation $conversation, 
        ParticipantConversationRepository $repo, 
        EntityManagerInterface $em
    ): JsonResponse {
        $user = $this->getUser();
        
        // Find the specific participant entry
        $participant = $repo->findOneBy([
            'idConversation' => $conversation,
            'idUtilisateur' => $user
        ]);

        if (!$participant) {
            return new JsonResponse(['error' => 'Participant not found'], 404);
        }

        // Equivalent to your Java UPDATE logic
        $participant->setEstActif(false);
        $participant->setDateSortie(new \DateTime()); // Replaces LocalDateTime.now()

        $em->flush();

        return new JsonResponse(['status' => 'User left the conversation']);
    }

    /**
     * Replaces 'isUserActiveInConversation'
     */
    #[Route('/status/{idConversation}', name: 'app_participant_status', methods: ['GET'])]
    public function checkStatus(Conversation $conversation, ParticipantConversationRepository $repo): JsonResponse
    {
        $participant = $repo->findOneBy([
            'idConversation' => $conversation,
            'idUtilisateur' => $this->getUser()
        ]);

        return new JsonResponse([
            'isActive' => $participant ? $participant->isEstActif() : false
        ]);
    }
}