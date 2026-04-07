<?php

namespace App\Controller;

use App\Entity\Services;
use App\Form\VolType;
use App\Repository\ServicesRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Handles all Vol (flight) CRUD operations.
 * Mirrors: addVolController + UpdateVolController + VolDetailsController (JavaFX)
 */
#[Route('/vol')]
class VolController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private ServicesRepository     $servicesRepo,
        private SluggerInterface       $slugger,
        private HttpClientInterface    $httpClient,
    ) {}

    // ──────────────────────────────────────────────
    // LIST
    // ──────────────────────────────────────────────

    #[Route('', name: 'vol_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('services/index.html.twig', [
            'active_page' => 'services',
            'services'    => $this->servicesRepo->findBy(['type' => 'vol']),
        ]);
    }

    // ──────────────────────────────────────────────
    // CREATE
    // ──────────────────────────────────────────────

    #[Route('/new', name: 'vol_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $vol = new Services();
        $vol->setType('vol');

        $form = $this->createForm(VolType::class, $vol);
        $form->handleRequest($request);
                                                                                                                      
        if ($form->isSubmitted() && $form->isValid()) {

            $photoFile = $form->get('photo')->getData();
            if ($photoFile) {
                $vol->setImgUrl($this->uploadPhoto($photoFile, 'vols'));
            }

            // Vols don't use hotel-specific fields — set safe defaults
            $vol->setNombreEtoiles(0);
            $vol->setLocalisation('');
            $vol->setTypeChambre('');
   
            $this->em->persist($vol);
            $this->em->flush();
            
            $this->addFlash('success', 'Vol ajouté avec succès !');
            return $this->redirectToRoute('dashboard');
        }

        return $this->render('vol/new.html.twig', [
            'active_page' => 'services',
            'form'        => $form,
        ]);
    }

    // ──────────────────────────────────────────────
    // SHOW / DETAILS
    // ──────────────────────────────────────────────

    #[Route('/{id}', name: 'vol_show', methods: ['GET'])]
    public function show(int $id): Response
    {
        $vol = $this->findVolOrFail($id);

        // Optional: fetch weather for departure & arrival cities
        // $tempDepart  = $this->weatherService->getTemperature($vol->getVilleDepart());
        // $tempArrivee = $this->weatherService->getTemperature($vol->getVilleArrivee());

        return $this->render('vol/show.html.twig', [
            'active_page' => 'services',
            'vol'         => $vol,
            // 'tempDepart'  => $tempDepart,
            // 'tempArrivee' => $tempArrivee,
        ]);
    }
    #[Route('/details/{id}', name: 'vol_showdetails', methods: ['GET'])]
    public function details(int $id): Response
    {
        $vol = $this->findVolOrFail($id);

        // Optional: fetch weather for departure & arrival cities
        // $tempDepart  = $this->weatherService->getTemperature($vol->getVilleDepart());
        // $tempArrivee = $this->weatherService->getTemperature($vol->getVilleArrivee());

        return $this->render('vol/showdetails.html.twig', [
            'active_page' => 'services',
            'vol'         => $vol,
            // 'tempDepart'  => $tempDepart,
            // 'tempArrivee' => $tempArrivee,
        ]);
    }


    // ──────────────────────────────────────────────
    // EDIT / UPDATE
    // ──────────────────────────────────────────────

    #[Route('/{id}/edit', name: 'vol_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, int $id): Response
    {
        $vol = $this->findVolOrFail($id);

        $form = $this->createForm(VolType::class, $vol);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $photoFile = $form->get('photo')->getData();
            if ($photoFile) {
                $vol->setImgUrl($this->uploadPhoto($photoFile, 'vols'));
            }

            $this->em->flush();

            $this->addFlash('success', 'Vol modifié avec succès !');
            return $this->redirectToRoute('vol_show', ['id' => $vol->getIdService()]);
        }

        return $this->render('vol/edit.html.twig', [
            'active_page' => 'services',
            'form'        => $form,
            'vol'         => $vol,
        ]);
    }

    // ──────────────────────────────────────────────
    // DELETE
    // ──────────────────────────────────────────────

    #[Route('/{id}/delete', name: 'vol_delete', methods: ['GET', 'POST'])]
    public function delete(Request $request, int $id): Response
    {
      
        $vol = $this->findVolOrFail($id);
        if (!$vol) {
            throw $this->createNotFoundException("vol #$id introuvable.");
        }

        // Accept both GET (with confirm dialog in Twig) and POST (with CSRF)
        if ($request->isMethod('POST')) {
            if ($this->isCsrfTokenValid('delete_vol_' . $id, $request->request->get('_token'))) {
                $this->em->remove($vol);
                $this->em->flush();
                $this->addFlash('success', 'Vol supprimé.');
            }
    
        } else {
            // GET — simple confirmation via JS confirm() in the Twig link
            $this->em->remove($vol);
            $this->em->flush();
            $this->addFlash('success', 'vol supprimée.');
        }

        return $this->redirectToRoute('services_index');
    }

    // ──────────────────────────────────────────────
    // AUTO-FILL FROM EXTERNAL FLIGHT API
    // Mirrors: chercherVolAPI() in addVolController (JavaFX)
    // Call via JS fetch from the "Auto remplissage" button
    // ──────────────────────────────────────────────

    #[Route('/autofill', name: 'vol_autofill', methods: ['GET'])]
    public function autofill(Request $request): JsonResponse
    {
        $numeroVol = $request->query->get('numeroVol', '');

        if (!$numeroVol) {
            return $this->json(['error' => 'Numéro de vol requis.'], 400);
        }

        try {
            // Example: AviationStack API — replace with your actual API key & endpoint
            $response = $this->httpClient->request('GET', 'http://api.aviationstack.com/v1/flights', [
                'query' => [
                    'access_key' => $_ENV['AVIATIONSTACK_API_KEY'] ?? '',
                    'flight_iata' => $numeroVol,
                ],
            ]);

            $data   = $response->toArray();
            $flight = $data['data'][0] ?? null;

            if (!$flight) {
                return $this->json(['error' => 'Vol introuvable.'], 404);
            }

            // Return fields to pre-fill the form via JavaScript
            return $this->json([
                'villeDepart'  => $flight['departure']['airport'] ?? '',
                'villeArrivee' => $flight['arrival']['airport']   ?? '',
                'dateDepart'   => $flight['departure']['scheduled'] ?? '',
                'dateArrivee'  => $flight['arrival']['scheduled']   ?? '',
            ]);

        } catch (\Exception $e) {
            return $this->json(['error' => 'Erreur API : ' . $e->getMessage()], 500);
        }
    }

    // ──────────────────────────────────────────────
    // PRIVATE HELPERS
    // ──────────────────────────────────────────────

    private function findVolOrFail(int $id): Services
    {
        $vol = $this->servicesRepo->findOneBy(['idService' => $id, 'type' => 'vol']);
        if (!$vol) {
            throw $this->createNotFoundException("Vol #$id introuvable.");
        }
        return $vol;
    }

    private function uploadPhoto(\Symfony\Component\HttpFoundation\File\UploadedFile $file, string $subfolder): string
    {
        $safeFilename = $this->slugger->slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));
        $newFilename  = $safeFilename . '-' . uniqid() . '.' . $file->guessExtension();

        try {
            $file->move(
                $this->getParameter('kernel.project_dir') . '/public/uploads/' . $subfolder,
                $newFilename
            );
        } catch (FileException $e) {
            throw new \RuntimeException('Erreur upload : ' . $e->getMessage());
        }

        return 'uploads/' . $subfolder . '/' . $newFilename;
    }
}
