<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

use App\Entity\Services;

#[ORM\Entity]
class Reservations
{

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private int $id_reservation;

    #[ORM\Column(name:'date_reservation',type: "date")]
    private \DateTimeInterface $date_reservation;

    #[ORM\Column(type: "string", length: 255)]
    private string $statut;

    #[ORM\Column(type: "string", length: 50)]
    private string $mode_paiement;

        #[ORM\ManyToOne(targetEntity: Services::class, inversedBy: "reservationss")]
    #[ORM\JoinColumn(name: 'idService', referencedColumnName: 'idService', onDelete: 'CASCADE')]
    private Services $idService;

    #[ORM\Column(type: "string", length: 50)]
    private string $nom;

    #[ORM\Column(type: "integer")]
    private int $seat_nb;

    public function getId_reservation()
    {
        return $this->id_reservation;
    }

    public function setId_reservation($value)
    {
        $this->id_reservation = $value;
    }

    public function getDate_reservation()
    {
        return $this->date_reservation;
    }

    public function setDate_reservation($value)
    {
        $this->date_reservation = $value;
    }

    public function getStatut()
    {
        return $this->statut;
    }

    public function setStatut($value)
    {
        $this->statut = $value;
    }

    public function getMode_paiement()
    {
        return $this->mode_paiement;
    }

    public function setMode_paiement($value)
    {
        $this->mode_paiement = $value;
    }

    public function getIdService()
    {
        return $this->idService;
    }

    public function setIdService($value)
    {
        $this->idService = $value;
    }

    public function getNom()
    {
        return $this->nom;
    }

    public function setNom($value)
    {
        $this->nom = $value;
    }

    public function getSeat_nb()
    {
        return $this->seat_nb;
    }

    public function setSeat_nb($value)
    {
        $this->seat_nb = $value;
    }

    public function getIdReservation(): ?int
    {
        return $this->id_reservation;
    }

    public function getDateReservation(): ?\DateTime
    {
        return $this->date_reservation;
    }

    public function setDateReservation(\DateTime $date_reservation): static
    {
        $this->date_reservation = $date_reservation;

        return $this;
    }

    public function getModePaiement(): ?string
    {
        return $this->mode_paiement;
    }

    public function setModePaiement(string $mode_paiement): static
    {
        $this->mode_paiement = $mode_paiement;

        return $this;
    }

    public function getSeatNb(): ?int
    {
        return $this->seat_nb;
    }

    public function setSeatNb(int $seat_nb): static
    {
        $this->seat_nb = $seat_nb;

        return $this;
    }
}
