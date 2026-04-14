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
use Symfony\Component\String\Slugger\SluggerInterface;
use App\Repository\ConversationRepository;

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
    #[Route('/update/{idMessage}', name: 'app_message_update', methods: ['POST', 'PUT'])]
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
            $message->setEdited(true);
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
    #[Route('/upload', name: 'api_message_upload', methods: ['POST'])]
    public function upload(
        Request $request,
        EntityManagerInterface $em,
        SluggerInterface $slugger,
        ConversationRepository $convRepo // Si vous l'avez
    ): JsonResponse {
        $file = $request->files->get('file');
        $conversationId = $request->request->get('conversationId');

        if (!$file || !$conversationId) {
            return new JsonResponse(['success' => false, 'message' => 'Données manquantes.'], 400);
        }

        $mimeType = $file->getMimeType();
        $type = TypeMessage::FICHIER; // Par défaut

        if (str_starts_with($mimeType, 'image/')) {
            $type = TypeMessage::IMAGE;
        } elseif (str_starts_with($mimeType, 'audio/')) {
            $type = TypeMessage::AUDIO;
        }

        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = $slugger->slug($originalFilename);
        $newFilename = $safeFilename . '-' . uniqid() . '.' . $file->guessExtension();

        try {
            $file->move(
                $this->getParameter('kernel.project_dir') . '/public/uploads/messages',
                $newFilename
            );
        } catch (\Exception $e) {
            return new JsonResponse(['success' => false, 'message' => 'Erreur lors de la sauvegarde du fichier.']);
        }

        $message = new Messages();
        $message->setTypeMessage($type);
        $message->setUrlFichier($newFilename);
        $message->setContenu($file->getClientOriginalName()); // On garde le nom original comme contenu
        $message->setDateEnvoi(new \DateTime());
        $message->setIdExpediteur($this->getUser());
        $message->setLu(false);        // Le message n'est pas encore lu
        $message->setIsDeleted(false);

        $conversation = $em->getRepository(Conversation::class)->find($conversationId);
        $message->setIdConversation($conversation);

        $em->persist($message);
        $em->flush();

        return new JsonResponse(['success' => true]);
    }

    #[Route('/fetch/{id}', name: 'app_message_fetch', methods: ['GET'])]
    public function fetchMessages(Conversation $conversation, MessagesRepository $repo): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $messages = $repo->findBy(['idConversation' => $conversation], ['dateEnvoi' => 'ASC']);

        $data = [];
        foreach ($messages as $msg) {
            $data[] = [
                'id' => $msg->getId(),
                'content' => $msg->isDeleted() ? 'This message was deleted' : $msg->getContenu(),
                'time' => $msg->getDateEnvoi()->format('H:i'),
                'sender' => $msg->getIdExpediteur()->getLastName() . ' ' . $msg->getIdExpediteur()->getName(),
                'isMine' => $user && $msg->getIdExpediteur()->getId() === $user->getId(),
                'lu' => $msg->isLu(),
                'isDeleted' => $msg->isDeleted(),
                'edited' => $msg->isEdited(),
                'type' => $msg->getTypeMessage() ? $msg->getTypeMessage()->value : 'TEXTE',
                'filePath' => $msg->getUrlFichier(),
                'reaction'  => $msg->getReaction()
            ];
        }
        return new JsonResponse($data);
    }

    #[Route('/{id}/react', name: 'message_react', methods: ['POST'])]
    public function react(Messages $message, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $emoji = $request->toArray()['emoji'] ?? null;

        // Si même emoji → toggle off, sinon on remplace
        if ($message->getReaction() === $emoji) {
            $message->setReaction(null);
        } else {
            $message->setReaction($emoji);
        }

        $em->flush();

        return $this->json([
            'reaction' => $message->getReaction()
        ]);
    }

    #[Route('/api/messages/mark-read/{id}', name: 'messages_mark_read', methods: ['POST'])]
    public function markRead(int $id, MessagesRepository $repo): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['error' => 'Not authenticated'], 401);
        }
        $repo->markAllAsRead($id, $user->getId());
        return $this->json(['ok' => true]);
    }
}
