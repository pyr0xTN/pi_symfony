<?php

namespace App\Service;

use App\Entity\Reservations;

class ReservationManager
{
    public function validate(Reservations $reservation): bool
    {
        if (empty($reservation->getNom())) {
            throw new \InvalidArgumentException('Le nom est obligatoire.');
        }

        if ($reservation->getSeatNb() <= 0) {
            throw new \InvalidArgumentException('Le nombre de sièges doit être supérieur à zéro.');
        }

        return true;
    }
}
