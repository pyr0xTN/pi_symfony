<?php

namespace App\Controller;

use App\Entity\Publication;
use App\Repository\AgencyRepository;
use App\Repository\PublicationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/agency')]
class AgencyController extends AbstractController
{
    #[Route('/login', name: 'agency_login')]
    public function login(
        Request $request,
        AgencyRepository $agencyRepo,
        RequestStack $requestStack,
    ): Response {
        $error = null;

        if ($request->isMethod('POST')) {
            $email    = $request->request->get('email', '');
            $password = $request->request->get('password', '');

            $agency = $agencyRepo->findByCredentials($email, $password);

            if ($agency) {
                $session = $requestStack->getSession();
                $session->set('agency_id', $agency->getId());
                $session->set('agency_name', $agency->getDisplayName());
                return $this->redirectToRoute('agency_dashboard');
            }

            $error = 'Invalid email or password. Please try again.';
        }

        return $this->render('agency/login.html.twig', [
            'error' => $error,
        ]);
    }

    #[Route('/dashboard', name: 'agency_dashboard')]
    public function dashboard(
        Request $request,
        RequestStack $requestStack,
        PublicationRepository $pubRepo,
    ): Response {
        $session = $requestStack->getSession();
        $agencyId = $session->get('agency_id');

        if (!$agencyId) {
            return $this->redirectToRoute('agency_login');
        }

        $posts = $pubRepo->findByAgency($agencyId);

        // Count by status
        $pendingCount  = count(array_filter($posts, fn($p) => $p->isPending()));
        $approvedCount = count(array_filter($posts, fn($p) => $p->isApproved()));
        $rejectedCount = count(array_filter($posts, fn($p) => $p->isRejected()));

        // Filter
        $filter = $request->query->get('filter', 'all');
        $filteredPosts = match ($filter) {
            'pending'  => array_filter($posts, fn($p) => $p->isPending()),
            'approved' => array_filter($posts, fn($p) => $p->isApproved()),
            'rejected' => array_filter($posts, fn($p) => $p->isRejected()),
            default    => $posts,
        };

        return $this->render('agency/dashboard.html.twig', [
            'posts'         => $filteredPosts,
            'pendingCount'  => $pendingCount,
            'approvedCount' => $approvedCount,
            'rejectedCount' => $rejectedCount,
            'filter'        => $filter,
            'agencyName'    => $session->get('agency_name', 'Agency'),
        ]);
    }

    #[Route('/post/{id}/approve', name: 'agency_approve', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function approve(int $id, PublicationRepository $pubRepo, EntityManagerInterface $em): Response
    {
        $post = $pubRepo->find($id);
        if ($post) {
            $post->setStatus(Publication::STATUS_APPROVED);
            $em->flush();
            $this->addFlash('success', 'Post approved!');
        }
        return $this->redirectToRoute('agency_dashboard');
    }

    #[Route('/post/{id}/reject', name: 'agency_reject', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function reject(int $id, PublicationRepository $pubRepo, EntityManagerInterface $em): Response
    {
        $post = $pubRepo->find($id);
        if ($post) {
            $post->setStatus(Publication::STATUS_REJECTED);
            $em->flush();
            $this->addFlash('success', 'Post rejected.');
        }
        return $this->redirectToRoute('agency_dashboard');
    }

    #[Route('/logout', name: 'agency_logout')]
    public function logout(RequestStack $requestStack): Response
    {
        $session = $requestStack->getSession();
        $session->remove('agency_id');
        $session->remove('agency_name');
        return $this->redirectToRoute('agency_login');
    }
}
