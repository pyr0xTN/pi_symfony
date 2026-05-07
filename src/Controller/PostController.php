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

#[IsGranted('ROLE_USER')]
class PostController extends AbstractController
{

    #[Route('/community', name: 'app_feed')]
    public function feed(
        Request $request,
        PublicationRepository $pubRepo,
        LikeRepository $likeRepo,
        CommentRepository $commentRepo,
        \Knp\Component\Pager\PaginatorInterface $paginator,
    ): Response {
        $search = $request->query->get('q', '');
        $view   = $request->query->get('view', 'grid');

        $qb = $search
            ? $pubRepo->searchByKeywordQb($search)
            : $pubRepo->findAllApprovedQb();

        $pagination = $paginator->paginate(
            $qb,
            $request->query->getInt('page', 1),
            6  // posts per page
        );

        $user = $this->getUser();
        $clientId = $user->getId();

        // Build metadata for each post
        $postMeta = [];
        foreach ($pagination as $post) {
            $postMeta[$post->getId()] = [
                'likeCount'    => $likeRepo->countByPublication($post->getId()),
                'commentCount' => $commentRepo->countByPublication($post->getId()),
                'userLiked'    => $likeRepo->hasUserLiked($post->getId(), $clientId),
            ];
        }

        return $this->render('front/feed.html.twig', [
            'posts'      => $pagination,
            'postMeta'   => $postMeta,
            'search'     => $search,
            'viewMode'   => $view,
            'clientId'   => $clientId,
            'embed'      => (bool) $request->query->get('embed', false),
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

        $user = $this->getUser();
        $clientId = $user->getId();

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
            $user = $this->getUser();


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

        $user = $this->getUser();
        $clientId = $user->getId();

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

        $user = $this->getUser();
        $clientId = $user->getId();

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

    #[Route('/community/journal/pdf', name: 'app_journal_pdf')]
    public function journalPdf(
        PublicationRepository $pubRepo,
        LikeRepository $likeRepo,
        CommentRepository $commentRepo,
    ): Response {
        $user = $this->getUser();
        $posts = $pubRepo->findByUser($user->getId());

        $entries = [];
        foreach ($posts as $post) {
            $entries[] = [
                'post'         => $post,
                'likeCount'    => $likeRepo->countByPublication($post->getId()),
                'commentCount' => $commentRepo->countByPublication($post->getId()),
            ];
        }

        $html = $this->renderView('front/journal_pdf.html.twig', [
            'entries'  => $entries,
            'username' => $user->getUsername(),
        ]);

        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return new Response($dompdf->output(), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="my-travel-journal.pdf"',
        ]);
    }
}
