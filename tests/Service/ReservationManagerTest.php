<?php

namespace App\Tests\Service;

use App\Entity\Reservations;
use App\Service\ReservationManager;
use PHPUnit\Framework\TestCase;

class ReservationManagerTest extends TestCase
{
    public function testValidReservation()
    {
        $reservation = new Reservations();
        $reservation->setNom('Victor Hugo');
        $reservation->setSeatNb(2);

        $manager = new ReservationManager();

        $this->assertTrue($manager->validate($reservation));
    }

    public function testReservationWithoutNom()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le nom est obligatoire.');

        $reservation = new Reservations();
        $reservation->setNom(''); // Initialize to empty to avoid uninitialized property error
        $reservation->setSeatNb(2);

        $manager = new ReservationManager();
        $manager->validate($reservation);
    }

    public function testReservationWithInvalidSeatNb()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le nombre de sièges doit être supérieur à zéro.');

        $reservation = new Reservations();
        $reservation->setNom('Victor Hugo');
        $reservation->setSeatNb(0);

        $manager = new ReservationManager();
        $manager->validate($reservation);
    }

    public function testReservationWithNegativeSeatNb()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le nombre de sièges doit être supérieur à zéro.');

        $reservation = new Reservations();
        $reservation->setNom('Victor Hugo');
        $reservation->setSeatNb(-5);

        $manager = new ReservationManager();
        $manager->validate($reservation);
    }
}
