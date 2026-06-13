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


#[Route('/vol')]
class VolController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private ServicesRepository     $servicesRepo,
        private SluggerInterface       $slugger,
        private HttpClientInterface    $httpClient,
        #[\Symfony\Component\DependencyInjection\Attribute\Autowire('%aviationstack_api_key%')]
        private string $aviationstackApiKey,
    ) {}



    #[Route('', name: 'vol_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('services/index.html.twig', [
            'active_page' => 'services',
            'services'    => $this->servicesRepo->findBy(['type' => 'vol']),
        ]);
    }



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

        return $this->render('dashboard/vol/new.html.twig', [
            'active_page' => 'services',
            'form'        => $form,
        ]);
    }

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
                    'access_key' => $this->aviationstackApiKey,
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
                'nom'          => ($flight['airline']['name'] ?? '') . ' ' . ($flight['flight']['iata'] ?? ''),
                'capacite'     => $flight['aircraft']['seats'] ?? 180, 
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Erreur API : ' . $e->getMessage()], 500);
        }
    }

    #[Route('/{id}', name: 'vol_show', methods: ['GET'])]
    public function show(int $id): Response
    {
        $vol = $this->findVolOrFail($id);

      
        return $this->render('vol/show.html.twig', [
            'active_page' => 'services',
            'vol'         => $vol,
        ]);
    }
    #[Route('/details/{id}', name: 'vol_showdetails', methods: ['GET'])]
    public function details(int $id): Response
    {
        $vol = $this->findVolOrFail($id);

      

        return $this->render('vol/showdetails.html.twig', [
            'active_page' => 'services',
            'vol'         => $vol,
           
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

    

    #[Route('/{id}/delete', name: 'vol_delete', methods: ['GET', 'POST'])]
    public function delete(Request $request, int $id): Response
    {
      
        $vol = $this->findVolOrFail($id);
        if (!$vol) {
            throw $this->createNotFoundException("vol #$id introuvable.");
        }

        if ($request->isMethod('POST')) {
            if ($this->isCsrfTokenValid('delete_vol_' . $id, $request->request->get('_token'))) {
                $this->em->remove($vol);
                $this->em->flush();
                $this->addFlash('success', 'Vol supprimé.');
            }
    
        } else {
            $this->em->remove($vol);
            $this->em->flush();
            $this->addFlash('success', 'vol supprimée.');
        }

        return $this->redirectToRoute('servicespage');
    }

 




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
