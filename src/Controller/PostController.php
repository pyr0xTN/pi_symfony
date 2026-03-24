<?php

namespace App\Controller;

use App\Entity\Comment;
use App\Entity\Like;
use App\Entity\Publication;
use App\Repository\ClientRepository;
use App\Repository\CommentRepository;
use App\Repository\LikeRepository;
use App\Repository\PublicationRepository;
use App\Repository\AgencyRepository;
use App\Service\ImageUploadService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class PostController extends AbstractController
{
    // Simulated user — same as Java app (client_id = 1)
    private const CURRENT_CLIENT_ID = 1;

    #[Route('/', name: 'app_feed')]
    public function feed(
        Request $request,
        PublicationRepository $pubRepo,
        LikeRepository $likeRepo,
        CommentRepository $commentRepo,
    ): Response {
        $search = $request->query->get('q', '');
        $view   = $request->query->get('view', 'grid');

        $posts = $search
            ? $pubRepo->searchByKeyword($search)
            : $pubRepo->findAllApproved();

        // Build metadata for each post
        $postMeta = [];
        foreach ($posts as $post) {
            $postMeta[$post->getId()] = [
                'likeCount'    => $likeRepo->countByPublication($post->getId()),
                'commentCount' => $commentRepo->countByPublication($post->getId()),
                'userLiked'    => $likeRepo->hasUserLiked($post->getId(), self::CURRENT_CLIENT_ID),
            ];
        }

        return $this->render('front/feed.html.twig', [
            'posts'     => $posts,
            'postMeta'  => $postMeta,
            'search'    => $search,
            'viewMode'  => $view,
            'clientId'  => self::CURRENT_CLIENT_ID,
        ]);
    }

    #[Route('/post/{id}', name: 'app_post_detail', requirements: ['id' => '\d+'])]
    public function detail(
        int $id,
        PublicationRepository $pubRepo,
        CommentRepository $commentRepo,
        LikeRepository $likeRepo,
    ): Response {
        $post = $pubRepo->find($id);
        if (!$post) throw $this->createNotFoundException('Post not found');

        return $this->render('front/post_detail.html.twig', [
            'post'         => $post,
            'comments'     => $commentRepo->findByPublication($id),
            'likeCount'    => $likeRepo->countByPublication($id),
            'commentCount' => $commentRepo->countByPublication($id),
            'userLiked'    => $likeRepo->hasUserLiked($id, self::CURRENT_CLIENT_ID),
            'clientId'     => self::CURRENT_CLIENT_ID,
        ]);
    }

    #[Route('/post/new', name: 'app_post_new')]
    public function new(
        Request $request,
        EntityManagerInterface $em,
        ClientRepository $clientRepo,
        AgencyRepository $agencyRepo,
        ImageUploadService $imageUpload,
    ): Response {
        if ($request->isMethod('POST')) {
            $client = $clientRepo->find(self::CURRENT_CLIENT_ID);
            if (!$client) throw $this->createNotFoundException('Client not found');

            $post = new Publication();
            $post->setContent($request->request->get('content', ''));
            $post->setClient($client);
            $post->setPlace($request->request->get('place', ''));

            $agencyId = (int) $request->request->get('agency_id', 0);
            if ($agencyId > 0) {
                $post->setAgencyId($agencyId);
                $post->setStatus(Publication::STATUS_PENDING);
            }

            // Handle image upload
            $imageFile = $request->files->get('image');
            if ($imageFile) {
                $path = $imageUpload->upload($imageFile);
                $post->setImagePath($path);
            }

            $em->persist($post);
            $em->flush();

            $this->addFlash('success', 'Post created successfully!');
            return $this->redirectToRoute('app_feed');
        }

        return $this->render('front/create_post.html.twig', [
            'agencies' => $agencyRepo->findAll(),
            'post'     => null,
            'clientId' => self::CURRENT_CLIENT_ID,
        ]);
    }

    #[Route('/post/{id}/edit', name: 'app_post_edit', requirements: ['id' => '\d+'])]
    public function edit(
        int $id,
        Request $request,
        PublicationRepository $pubRepo,
        AgencyRepository $agencyRepo,
        EntityManagerInterface $em,
        ImageUploadService $imageUpload,
    ): Response {
        $post = $pubRepo->find($id);
        if (!$post) throw $this->createNotFoundException('Post not found');

        if ($request->isMethod('POST')) {
            $post->setContent($request->request->get('content', ''));
            $post->setPlace($request->request->get('place', ''));

            $imageFile = $request->files->get('image');
            if ($imageFile) {
                $path = $imageUpload->upload($imageFile);
                $post->setImagePath($path);
            }

            $em->flush();
            $this->addFlash('success', 'Post updated successfully!');
            return $this->redirectToRoute('app_post_detail', ['id' => $id]);
        }

        return $this->render('front/create_post.html.twig', [
            'agencies' => $agencyRepo->findAll(),
            'post'     => $post,
            'clientId' => self::CURRENT_CLIENT_ID,
        ]);
    }

    #[Route('/post/{id}/delete', name: 'app_post_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(
        int $id,
        PublicationRepository $pubRepo,
        EntityManagerInterface $em,
    ): Response {
        $post = $pubRepo->find($id);
        if (!$post) throw $this->createNotFoundException('Post not found');

        $em->remove($post);
        $em->flush();

        $this->addFlash('success', 'Post deleted.');
        return $this->redirectToRoute('app_feed');
    }
}
