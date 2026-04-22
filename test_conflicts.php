<?php
require 'vendor/autoload.php';
use Symfony\Component\Dotenv\Dotenv;

(new Dotenv())->bootEnv(__DIR__.'/.env');

$kernel = new App\Kernel('dev', true);
$kernel->boot();
$container = $kernel->getContainer();

$reservationsRepo = $container->get('doctrine')->getRepository(App\Entity\Reservations::class);
$servicesRepo = $container->get('doctrine')->getRepository(App\Entity\Services::class);
$conflictService = $container->get(App\Service\ReservationConflictService::class);

echo "Simulating Conflict Check for 'mehdi':\n";
// Let's create a fake new Hotel booking for mehdi on 2026-04-23 located in "sousse"
$hotel = new \App\Entity\Services();
$hotel->setType('hotel');
$hotel->setNom('el mouradi');
$hotel->setLocalisation('sousse');

$resDate = new \DateTime('2026-04-23');

$conflicts = $conflictService->checkConflicts('mehdi', $hotel, $resDate);

if (empty($conflicts)) {
    echo "NO CONFLICTS DETECTED. (This explains why the user is confused!)\n";
} else {
    echo "CONFLICTS DETECTED:\n";
    print_r($conflicts);
}
