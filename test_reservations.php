<?php
require 'vendor/autoload.php';
use Symfony\Component\Dotenv\Dotenv;

(new Dotenv())->bootEnv(__DIR__.'/.env');

$kernel = new App\Kernel('dev', true);
$kernel->boot();
$container = $kernel->getContainer();
$repo = $container->get('doctrine')->getRepository(App\Entity\Reservations::class);

echo "Checking Reservations:\n";
foreach($repo->findAll() as $r) {
    /** @var \App\Entity\Services $s */
    $s = $r->getIdService();
    $name = $r->getNom();
    $statut = $r->getStatut();
    $resDate = $r->getDateReservation() ? $r->getDateReservation()->format('Y-m-d') : 'NULL';
    
    echo sprintf("\n[Resa ID: %d] Nom: %s | Statut: %s | ResDate: %s\n", $r->getId_reservation(), $name, $statut, $resDate);
    if ($s) {
        $type = $s->getType();
        $sName = $s->getNom();
        $cityCode = $type === 'vol' ? $s->getVilleDepart() . '->' . $s->getVilleArrivee() : $s->getLocalisation();
        $sDep = $s->getDateDepart() ? $s->getDateDepart()->format('Y-m-d') : 'NULL';
        $sArr = $s->getDateArrive() ? $s->getDateArrive()->format('Y-m-d') : 'NULL';
        
        echo sprintf("   Service: %s (%s) | Cities: %s | sDep: %s | sArr: %s\n", $type, $sName, $cityCode, $sDep, $sArr);
    } else {
        echo "   Service: NULL\n";
    }
}
