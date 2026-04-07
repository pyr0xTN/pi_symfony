<?php

namespace App\Controller;

use App\Entity\Conversation;
use App\Entity\Messages;
use App\Entity\User;
use App\Enum\TypeMessage; // Added missing Enum import
use App\Repository\MessagesRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/messages')]
class MessagesController extends AbstractController
{
    /**
     * Creates a new message in a conversation.
     */
    #[Route('/send/{id}', name: 'app_message_insert', methods: ['POST'])]
    public function insertOne(
        Conversation $conversation,
        Request $request,
        EntityManagerInterface $em
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $content = $data['content'] ?? '';

        if (empty($content)) {
            return new JsonResponse(['error' => 'Message cannot be empty'], 400);
        }

        /** @var User $user */
        $user = $this->getUser();

        $message = new Messages();
        $message->setId($this->nextMessageId($em));
        $message->setContenu($content);
        $message->setIdConversation($conversation);
        $message->setIdExpediteur($user);
        $message->setDateEnvoi(new \DateTime());
        $message->setLu(false);
        $message->setIsDeleted(false);
        $message->setTypeMessage(TypeMessage::TEXTE); // Default type

        $em->persist($message);
        $em->flush(); // Enregistrement en base de données

        return new JsonResponse([
            'id' => $message->getId(),
            'time' => $message->getDateEnvoi()->format('H:i'),
            'sender' => $user->getLastName() . ' ' . $user->getName()
        ], 201);
    }

    /**
     * Edits an existing message.
     */
    #[Route('/update/{idMessage}', name: 'app_message_update', methods: ['POST','PUT'])]
    public function updateOne(
        int $idMessage,
        MessagesRepository $repo,
        Request $request,
        EntityManagerInterface $em
    ): JsonResponse {
        $message = $repo->find($idMessage);

        // Security: only the sender can edit their own message
        if (!$message || $message->getIdExpediteur() !== $this->getUser()) {
            return new JsonResponse(['error' => 'Unauthorized'], 403);
        }

        $data = json_decode($request->getContent(), true);
        if (isset($data['content'])) {
            $message->setContenu($data['content']);
            $em->flush();
        }

        return new JsonResponse(['status' => 'Message updated']);
    }

    /**
     * Soft deletes a message.
     */
    #[Route('/delete/{idMessage}', name: 'app_message_delete', methods: ['POST', 'DELETE'])]
    public function deleteOne(int $idMessage, MessagesRepository $repo, EntityManagerInterface $em): JsonResponse
    {
        $message = $repo->find($idMessage);

        if (!$message || $message->getIdExpediteur() !== $this->getUser()) {
            return new JsonResponse(['error' => 'Unauthorized'], 403);
        }

        $message->setIsDeleted(true);
        $em->flush();

        return new JsonResponse(['status' => 'Message deleted']);
    }

    /**
     * Fetches all messages for a specific conversation.
     */
   /* #[Route('/fetch/{id}', name: 'app_message_fetch', methods: ['GET'])]
    public function fetchMessages(Conversation $conversation, MessagesRepository $repo): JsonResponse
    {
        /** @var User $user 
        $user = $this->getUser();
        $messages = $repo->findBy(
            ['idConversation' => $conversation, 'isDeleted' => false],
            ['dateEnvoi' => 'ASC']
        );

        $data = [];
        foreach ($messages as $msg) {
            $data[] = [
                'id' => $msg->getId(),
                'content' => $msg->getContenu(),
                'time' => $msg->getDateEnvoi()->format('H:i'),
                'sender' => $msg->getIdExpediteur()->getLastName() . ' ' . $msg->getIdExpediteur()->getName(),
                'isMine' => $user && $msg->getIdExpediteur()->getId() === $user->getId(),
                'lu' => $msg->isLu()
            ];
        }
        return new JsonResponse($data);
    }*/

    #[Route('/fetch/{id}', name: 'app_message_fetch', methods: ['GET'])]
    public function fetchMessages(Conversation $conversation, MessagesRepository $repo): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        // Récupère tous les messages (grâce au repo modifié au dessus)
        $messages = $repo->findBy(['idConversation' => $conversation], ['dateEnvoi' => 'ASC']);

        $data = [];
        foreach ($messages as $msg) {
            $data[] = [
                'id' => $msg->getId(),
                // LOGIQUE ICI : Si supprimé, on remplace le contenu
                'content' => $msg->isDeleted() ? 'This message was deleted' : $msg->getContenu(),
                'time' => $msg->getDateEnvoi()->format('H:i'),
                'sender' => $msg->getIdExpediteur()->getLastName() . ' ' . $msg->getIdExpediteur()->getName(),
                //'isMine' => $msg->getIdExpediteur()->getId() === $user->getId(),
                'isMine' => $user && $msg->getIdExpediteur()->getId() === $user->getId(),
                'lu' => $msg->isLu(),
                'isDeleted' => $msg->isDeleted() 
            ];
        }
        return new JsonResponse($data);
    }

    private function nextMessageId(EntityManagerInterface $em): int
    {
        $maxId = (int) $em->createQueryBuilder()
            ->select('COALESCE(MAX(m.id), 0)')
            ->from(Messages::class, 'm')
            ->getQuery()
            ->getSingleScalarResult();

        $next = $maxId + 1;

        return max(1, $next);
    }
}
