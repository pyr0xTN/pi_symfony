<?php

namespace App\Tests\Service;

use App\Entity\Services;
use App\Service\ServicesManager;
use PHPUnit\Framework\TestCase;

class ServicesManagerTest extends TestCase
{
    public function testValidService()
    {
        $service = new Services();
        $service->setPrix(150.5);
        $service->setCapacite(10);
        $service->setDateDepart(new \DateTime('2025-05-10'));
        $service->setDateArrive(new \DateTime('2025-05-15'));

        $manager = new ServicesManager();

        $this->assertTrue($manager->validate($service));
    }

    public function testServiceWithInvalidPrix()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le prix d\'un service doit toujours être supérieur à zéro.');

        $service = new Services();
        $service->setPrix(0.0);
        $service->setCapacite(10);

        $manager = new ServicesManager();
        $manager->validate($service);
    }

    public function testServiceWithNegativePrix()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le prix d\'un service doit toujours être supérieur à zéro.');

        $service = new Services();
        $service->setPrix(-50.0);
        $service->setCapacite(10);

        $manager = new ServicesManager();
        $manager->validate($service);
    }

    public function testServiceWithNegativeCapacite()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('La capacité saisie ne peut pas être négative.');

        $service = new Services();
        $service->setPrix(100.0);
        $service->setCapacite(-5);

        $manager = new ServicesManager();
        $manager->validate($service);
    }

    public function testServiceWithInvalidDates()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('La date d\'arrivée doit être postérieure à la date de départ.');

        $service = new Services();
        $service->setPrix(100.0);
        $service->setCapacite(50);
        // Start date is later than end date
        $service->setDateDepart(new \DateTime('2025-06-10'));
        $service->setDateArrive(new \DateTime('2025-06-01'));

        $manager = new ServicesManager();
        $manager->validate($service);
    }

    public function testServiceWithSameDates()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('La date d\'arrivée doit être postérieure à la date de départ.');

        $service = new Services();
        $service->setPrix(100.0);
        $service->setCapacite(50);
        // Same dates
        $service->setDateDepart(new \DateTime('2025-06-10'));
        $service->setDateArrive(new \DateTime('2025-06-10'));

        $manager = new ServicesManager();
        $manager->validate($service);
    }
}
