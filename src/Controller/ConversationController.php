<?php

namespace App\Controller;

use App\Entity\Conversation;
use App\Entity\ParticipantConversation;
use App\Entity\User;
use App\Enum\TypeConversation;
use App\Repository\ConversationRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use App\Repository\ParticipantConversationRepository;
use Doctrine\Common\Collections\ArrayCollection;

#[Route('/conversation')]
class ConversationController extends AbstractController
{
    /**
     * Displays the list of all conversations for the logged-in user.
     */
    #[Route('/', name: 'app_conversation_index', methods: ['GET'])]
    public function index(ConversationRepository $conversationRepository, UserRepository $userRepository): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        // Custom repository method
        // Note: Check if your User entity uses getId() or getIdUser()
        $conversations = $conversationRepository->findConversationsByUser($user->getId());

        return $this->render('conversation/index.html.twig', [
            'conversations' => $conversations,
        ]);
    }

    /**
     * Starts a 1-on-1 private chat with another user.
     */
    #[Route('/start/{idRecipient}', name: 'app_conversation_start', methods: ['GET', 'POST'])]
    public function startPrivate(
        int $idRecipient,
        UserRepository $userRepository,
        ConversationRepository $convRepo,
        EntityManagerInterface $entityManager,
        Request $request
    ): Response {
        /** @var User $me */
        $me = $this->getUser();

        if (!$me) {
            return $this->redirectToRoute('app_login');
        }

        $recipient = $userRepository->find($idRecipient);

        if (!$recipient || $me->getId() === $recipient->getId()) {
            throw $this->createNotFoundException('Recipient not found or invalid.');
        }

        // Check if a private conversation already exists
        $existingConversation = $convRepo->findPrivateChat($me->getId(), $recipient->getId());

        if ($existingConversation) {
            if ($request->isXmlHttpRequest() || $request->headers->get('X-Requested-With') === 'XMLHttpRequest') {
                return new JsonResponse(['conversationId' => $existingConversation->getId()]);
            }
            return $this->redirectToRoute('app_messenger');
        }

        // Create new Private Conversation
        $conversation = new Conversation();
        $conversation->setType(TypeConversation::PRIVEE);
        $conversation->setDateCreation(new \DateTime());
        $entityManager->persist($conversation);

        // Add Me as Participant
        $p1 = new ParticipantConversation();
        $p1->setIdUtilisateur($me);
        $p1->setIdConversation($conversation);
        $p1->setDateAjout(new \DateTime());
        $p1->setEstActif(true);
        $entityManager->persist($p1);

        // Add Recipient as Participant
        $p2 = new ParticipantConversation();
        $p2->setIdUtilisateur($recipient);
        $p2->setIdConversation($conversation);
        $p2->setDateAjout(new \DateTime());
        $p2->setEstActif(true);
        $entityManager->persist($p2);

        $entityManager->flush();

        if ($request->isXmlHttpRequest() || $request->headers->get('X-Requested-With') === 'XMLHttpRequest') {
            return new JsonResponse(['conversationId' => $conversation->getId()]);
        }

        return $this->redirectToRoute('app_messenger');
    }

    /**
     * Creates a new Group Conversation.
     */
    #[Route('/create-group', name: 'app_conversation_create_group', methods: ['POST'])]
    public function createGroup(
        Request $request,
        EntityManagerInterface $em,
        UserRepository $userRepo
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $name = $data['name'] ?? 'New Group';
        $memberIds = $data['members'] ?? [];

        /** @var User $creator */
        $creator = $this->getUser();

        if (!$creator) {
            return new JsonResponse(['error' => 'User not logged in'], 401);
        }

        $conversation = new Conversation();
        $conversation->setType(TypeConversation::GROUPE);
        $conversation->setTitre($name);
        $conversation->setDateCreation(new \DateTime());
        $em->persist($conversation);

        // Add Creator
        $pCreator = new ParticipantConversation();
        $pCreator->setIdUtilisateur($creator);
        $pCreator->setIdConversation($conversation);
        $pCreator->setDateAjout(new \DateTime());
        $pCreator->setEstActif(true);
        $em->persist($pCreator);

        foreach ($memberIds as $id) {
            $member = $userRepo->find($id);
            if ($member && $member->getId() !== $creator->getId()) {
                $pNew = new ParticipantConversation();
                $pNew->setIdUtilisateur($member);
                $pNew->setIdConversation($conversation);
                $pNew->setDateAjout(new \DateTime());
                $pNew->setEstActif(true);
                $em->persist($pNew);
            }
        }

        $em->flush();

        return new JsonResponse(['conversationId' => $conversation->getId()]);
    }

    /**
     * Fetch conversation details as JSON.
     */
    #[Route('/api/{id}', name: 'app_conversation_api', methods: ['GET'])]
    public function apiShow(
        Conversation $conversation, // Symfony will now find it via the 'id' property
        ParticipantConversationRepository $pcRepo
    ): JsonResponse {
        /** @var User $currentUser */
        $currentUser = $this->getUser();

        if (!$currentUser) {
            return new JsonResponse(['error' => 'Not logged in'], 401);
        }

        // Check if the user is a participant using the Repository
        $participant = $pcRepo->findOneBy([
            'idConversation' => $conversation,
            'idUtilisateur' => $currentUser,
            'estActif' => true
        ]);

        if (!$participant) {
            return new JsonResponse(['error' => 'Unauthorized access'], 403);
        }

        // Build data
        $participantsData = [];
        foreach ($conversation->getParticipants() as $p) {
            $u = $p->getIdUtilisateur();
            if ($u) {
                $participantsData[] = [
                    'id' => $u->getId(),
                    'name' => trim(($u->getLastName() ?? '') . ' ' . ($u->getName() ?? 'User')),
                ];
            }
        }

        return new JsonResponse([
            'id' => $conversation->getId(),
            'type' => $conversation->getType()->value,
            'title' => $conversation->getTitre(),
            'displayName' => $conversation->getDisplayName($currentUser),
            'participants' => $participantsData,
        ]);
    }
    /**
     * Delete conversation.
     */
    #[Route('/delete/{id}', name: 'app_conversation_delete', methods: ['POST'])]
    public function delete(Conversation $conversation, EntityManagerInterface $entityManager, ParticipantConversationRepository $participantRepo): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['error' => 'Not authenticated'], 401);
        }

        // Check if user is a participant in this conversation
        $isParticipant = false;
        foreach ($conversation->getParticipants() as $participant) {
            if ($participant->getIdUtilisateur()->getId() === $user->getId()) {
                $isParticipant = true;
                break;
            }
        }

        if (!$isParticipant) {
            return new JsonResponse(['error' => 'Not authorized'], 403);
        }

        $entityManager->remove($conversation);
        $entityManager->flush();

        return new JsonResponse(['status' => 'success'], 200);
    }

    #[Route('/rename/{id}', name: 'app_conversation_rename', methods: ['POST'])]
    public function rename(Conversation $conversation, Request $request, EntityManagerInterface $em, ParticipantConversationRepository $participantRepo): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['error' => 'Not authenticated'], 401);
        }

        // Check if user is a participant in this conversation
        $isParticipant = false;
        foreach ($conversation->getParticipants() as $participant) {
            if ($participant->getIdUtilisateur()->getId() === $user->getId()) {
                $isParticipant = true;
                break;
            }
        }

        if (!$isParticipant) {
            return new JsonResponse(['error' => 'Not authorized'], 403);
        }

        $data = json_decode($request->getContent(), true);
        $conversation->setTitre($data['title']);
        $em->flush();
        return new JsonResponse(['status' => 'ok']);
    }
}
