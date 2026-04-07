<?php

namespace App\Controller;

use App\Entity\Comment;
use App\Entity\Like;
use App\Entity\Publication;
use App\Entity\User;
use App\Repository\CommentRepository;
use App\Repository\LikeRepository;
use App\Repository\PublicationRepository;
use App\Repository\UserRepository;
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

        $user = $pubRepo->createQueryBuilder('p')->getEntityManager()->getRepository(\App\Entity\User::class)->findOneBy(['email' => 'testuser' . self::CURRENT_CLIENT_ID . '@test.com']);
        $clientId = $user ? $user->getId() : self::CURRENT_CLIENT_ID;

        // Build metadata for each post
        $postMeta = [];
        foreach ($posts as $post) {
            $postMeta[$post->getId()] = [
                'likeCount'    => $likeRepo->countByPublication($post->getId()),
                'commentCount' => $commentRepo->countByPublication($post->getId()),
                'userLiked'    => $likeRepo->hasUserLiked($post->getId(), $clientId),
            ];
        }

        return $this->render('front/feed.html.twig', [
            'posts'     => $posts,
            'postMeta'  => $postMeta,
            'search'    => $search,
            'viewMode'  => $view,
            'clientId'  => $clientId,
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

        $user = $pubRepo->createQueryBuilder('p')->getEntityManager()->getRepository(User::class)->findOneBy(['email' => 'testuser' . self::CURRENT_CLIENT_ID . '@test.com']);
        $clientId = $user ? $user->getId() : self::CURRENT_CLIENT_ID;

        return $this->render('front/post_detail.html.twig', [
            'post'         => $post,
            'comments'     => $commentRepo->findByPublication($id),
            'likeCount'    => $likeRepo->countByPublication($id),
            'commentCount' => $commentRepo->countByPublication($id),
            'userLiked'    => $likeRepo->hasUserLiked($id, $clientId),
            'clientId'     => $clientId,
        ]);
    }

    #[Route('/post/new', name: 'app_post_new')]
    public function new(
        Request $request,
        EntityManagerInterface $em,
        UserRepository $userRepo,
        ImageUploadService $imageUpload,
    ): Response {
        if ($request->isMethod('POST')) {
            $user = $userRepo->findOneBy(['email' => 'testuser' . self::CURRENT_CLIENT_ID . '@test.com']);
            if (!$user) {
                $user = new User();
                $user->setEmail('testuser' . self::CURRENT_CLIENT_ID . '@test.com');
                $user->setName('Demo');
                $user->setLastName('Traveller');
                $user->setPassword('123456');
                $user->setUsername('demo_traveller');
                $em->persist($user);
                $em->flush();
            }

            $post = new Publication();
            $post->setContent($request->request->get('content', ''));
            $post->setUser($user);
            $post->setPlace($request->request->get('place', ''));

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

        $user = $userRepo->findOneBy(['email' => 'testuser' . self::CURRENT_CLIENT_ID . '@test.com']);
        $clientId = $user ? $user->getId() : self::CURRENT_CLIENT_ID;

        return $this->render('front/create_post.html.twig', [
            'post'     => null,
            'clientId' => $clientId,
        ]);
    }

    #[Route('/post/{id}/edit', name: 'app_post_edit', requirements: ['id' => '\d+'])]
    public function edit(
        int $id,
        Request $request,
        PublicationRepository $pubRepo,
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

        $user = $pubRepo->createQueryBuilder('p')->getEntityManager()->getRepository(User::class)->findOneBy(['email' => 'testuser' . self::CURRENT_CLIENT_ID . '@test.com']);
        $clientId = $user ? $user->getId() : self::CURRENT_CLIENT_ID;

        return $this->render('front/create_post.html.twig', [
            'post'     => $post,
            'clientId' => $clientId,
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
