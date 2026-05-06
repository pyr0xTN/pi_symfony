<?php

namespace App\Service;

use App\Entity\Services;

class ServicesManager
{
    public function validate(Services $service): bool
    {
        if ($service->getPrix() <= 0) {
            throw new \InvalidArgumentException('Le prix d\'un service doit toujours être supérieur à zéro.');
        }

        if ($service->getCapacite() < 0) {
            throw new \InvalidArgumentException('La capacité saisie ne peut pas être négative.');
        }

        $dateDepart = $service->getDateDepart();
        $dateArrive = $service->getDateArrive();

        if ($dateDepart !== null && $dateArrive !== null) {
            if ($dateArrive <= $dateDepart) {
                throw new \InvalidArgumentException('La date d\'arrivée doit être postérieure à la date de départ.');
            }
        }

        return true;
    }
}
