<?php

namespace App\Controller;

use App\Entity\Services;
use App\Form\HotelType;
use App\Repository\ServicesRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;


#[Route('/hotel')]
class HotelController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private ServicesRepository     $servicesRepo,
        private SluggerInterface       $slugger,
    ) {}



    #[Route('', name: 'hotel_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('services/index.html.twig', [
            'active_page' => 'services',
            'services'    => $this->servicesRepo->findBy(['type' => 'hotel']),
        ]);
    }

   

    #[Route('/new', name: 'hotel_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $hotel = new Services();
        $hotel->setType('hotel');

        $form = $this->createForm(HotelType::class, $hotel);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            // Handle photo upload
            $photoFile = $form->get('photo')->getData();
            if ($photoFile) {
                $hotel->setImgUrl($this->uploadPhoto($photoFile, 'hotels'));
            }

            // Hotels don't use vol-specific fields — set safe defaults
            $hotel->setNumeroVol('');
            $hotel->setVilleDepart('');
            $hotel->setVilleArrivee('');
            $hotel->setDateDepart(new \DateTime('2000-01-01'));
            $hotel->setDateArrive(new \DateTime('2000-01-01'));

            $this->em->persist($hotel);
            $this->em->flush();

            $this->addFlash('success', 'Hôtel ajouté avec succès !');
            return $this->redirectToRoute('dashboard');
        }

        return $this->render('hotel/new.html.twig', [
            'active_page' => 'services',
            'form'        => $form,
        ]);
    }


    #[Route('/{id}', name: 'hotel_show', methods: ['GET'])]
    public function show(int $id): Response
    {
        $hotel = $this->findHotelOrFail($id);

        return $this->render('hotel/show.html.twig', [
            'active_page' => 'services',
            'hotel'       => $hotel,
           
        ]);
    }
    
    #[Route('/details/{id}', name: 'hotel_showdetails', methods: ['GET'])]
    public function showdetails(int $id): Response
    {
        $hotel = $this->findHotelOrFail($id);

        return $this->render('hotel/showdetails.html.twig', [
            'active_page' => 'services',
            'hotel'       => $hotel,
            
        ]);
    }

    #[Route('/{id}/edit', name: 'hotel_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, int $id): Response
    {
        $hotel = $this->findHotelOrFail($id);
      
        $form = $this->createForm(HotelType::class, $hotel);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
         
            $photoFile = $form->get('photo')->getData();
            if ($photoFile) {
                $hotel->setImgUrl($this->uploadPhoto($photoFile, 'hotels'));
            }

            $this->em->flush();

            $this->addFlash('success', 'Hôtel modifié avec succès !');
            return $this->redirectToRoute('hotel_show', ['id' => $hotel->getIdService()]);
        }

        return $this->render('hotel/edit.html.twig', [
            'active_page' => 'services',
            'form'        => $form,
            'hotel'       => $hotel,
        ]);
    }

 

    #[Route('/{id}/delete', name: 'hotel_delete', methods: ['GET', 'POST'])]
    public function delete(Request $request, int $id): Response
    {
        $hotel = $this->findHotelOrFail($id);
        if (!$hotel) {
            throw $this->createNotFoundException("vol #$id introuvable.");
        }
        if ($request->isMethod('POST')) {
            if ($this->isCsrfTokenValid('delete_hotel_' . $id, $request->request->get('_token'))) {
                $this->em->remove($hotel);
                $this->em->flush();
                $this->addFlash('success', 'Hôtel supprimé.');
            }
    
        } else {
            $this->em->remove($hotel);
            $this->em->flush();
            $this->addFlash('success', 'hotel supprimée.');
        }

        return $this->redirectToRoute('services_index');
    }

   

    private function findHotelOrFail(int $id): Services
    {
        $hotel = $this->servicesRepo->findOneBy(['idService' => $id, 'type' => 'hotel']);
        if (!$hotel) {
            throw $this->createNotFoundException("Hôtel #$id introuvable.");
        }
        return $hotel;
    }

    private function uploadPhoto(\Symfony\Component\HttpFoundation\File\UploadedFile $file, string $subfolder): string
    {
        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename     = $this->slugger->slug($originalFilename);
        $newFilename      = $safeFilename . '-' . uniqid() . '.' . $file->guessExtension();

        try {
            $file->move(
                $this->getParameter('kernel.project_dir') . '/public/uploads/' . $subfolder,
                $newFilename
            );
        } catch (FileException $e) {
            throw new \RuntimeException('Erreur lors de l\'upload de la photo : ' . $e->getMessage());
        }

        return 'uploads/' . $subfolder . '/' . $newFilename;
    }
    
}
