<?php

namespace App\Service;

use App\Entity\Reservations;
use App\Entity\Services;
use App\Repository\ReservationsRepository;
use DateTimeInterface;

class ReservationConflictService
{
    public function __construct(
        private ReservationsRepository $reservationsRepo
    ) {}

    public function checkConflicts(string $nom, Services $newService, DateTimeInterface $newDateReservation): array
    {
        $conflicts = [];
        $existingReservations = $this->reservationsRepo->findActiveByNom($nom);

        $newStart = $this->getStartDate($newService, $newDateReservation);
        $newEnd = $this->getEndDate($newService, $newDateReservation);
        $newCities = $this->getCities($newService);

        foreach ($existingReservations as $existingResa) {
            $existingService = $existingResa->getIdService();
            if (!$existingService) continue;

            $existingStart = $this->getStartDate($existingService, $existingResa->getDateReservation());
            $existingEnd = $this->getEndDate($existingService, $existingResa->getDateReservation());

            if ($this->datesOverlap($newStart, $newEnd, $existingStart, $existingEnd)) {
                $existingCities = $this->getCities($existingService);
                
                // Geo conflict check
                if (!$this->citiesCompatible($newCities, $existingCities)) {
                    $conflicts[] = sprintf(
                        'Conflit géolocalisation: Vous avez déjà une réservation à cette date pour %s "%s" impliquant une ville différente.',
                        $existingService->getType() === 'vol' ? 'le vol' : 'l\'hôtel',
                        $existingService->getNom()
                    );
                    continue; // Already added a conflict for this reservation
                } 

                // Date overlap check for same types (even if cities match)
                if ($newService->getType() === 'hotel' && $existingService->getType() === 'hotel') {
                    $conflicts[] = sprintf(
                        'Conflit de date: Vous avez déjà réservé un hôtel ("%s") pour cette même date.',
                        $existingService->getNom()
                    );
                }
                else if ($newService->getType() === 'vol' && $existingService->getType() === 'vol') {
                    $conflicts[] = sprintf(
                        'Conflit de date: Vous avez déjà un vol ("%s") réservé sur ces dates.',
                        $existingService->getNom()
                    );
                }
            }
        }

        return $conflicts;
    }

    private function getStartDate(Services $service, DateTimeInterface $reservationDate): DateTimeInterface
    {
        // The user's form-selected reservation date is the ground truth
        return $reservationDate;
    }

    private function getEndDate(Services $service, DateTimeInterface $reservationDate): DateTimeInterface
    {
        return $reservationDate;
    }

    private function datesOverlap(DateTimeInterface $start1, DateTimeInterface $end1, DateTimeInterface $start2, DateTimeInterface $end2): bool
    {
        $s1 = (clone $start1)->setTime(0, 0, 0);
        $e1 = (clone $end1)->setTime(23, 59, 59);
        $s2 = (clone $start2)->setTime(0, 0, 0);
        $e2 = (clone $end2)->setTime(23, 59, 59);
        
        return $s1 <= $e2 && $e1 >= $s2;
    }

    private function getCities(Services $service): array
    {
        $cities = [];
        if ($service->getType() === 'vol') {
            if ($service->getVilleDepart()) $cities[] = strtolower(trim($service->getVilleDepart()));
            if ($service->getVilleArrivee()) $cities[] = strtolower(trim($service->getVilleArrivee()));
        } else {
            if ($service->getLocalisation()) $cities[] = strtolower(trim($service->getLocalisation()));
        }
        return array_unique($cities);
    }

    private function citiesCompatible(array $cities1, array $cities2): bool
    {
        if (empty($cities1) || empty($cities2)) return true;

        $intersection = array_intersect($cities1, $cities2);
        return count($intersection) > 0;
    }
}
