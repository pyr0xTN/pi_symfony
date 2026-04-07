<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

use Doctrine\Common\Collections\Collection;
use App\Entity\Reservations;

#[ORM\Entity]
class Services
{

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'idService',type: "integer")]
    private int $idService;

    #[ORM\Column(type: "string", length: 50)]
    private string $nom;

    #[ORM\Column(type: "text")]
    private string $description;

    #[ORM\Column(type: "float")]
    private float $prix;

    #[ORM\Column(type: "string", length: 255)]
    private string $disponibilite;

    #[ORM\Column(type: "integer")]
    private int $capacite;

    #[ORM\Column(type: "string", length: 50)]
    private string $localisation;

    #[ORM\Column(type: "string", length: 500)]
    private string $img_url;

    #[ORM\Column(type: "string", length: 255)]
    private string $type;

    #[ORM\Column(type: "integer")]
    private int $nombre_etoiles;

    #[ORM\Column(type: "string", length: 255)]
    private string $type_chambre;

    #[ORM\Column(type: "string", length: 25)]
    private string $numero_vol;

    #[ORM\Column(type: "string", length: 70)]
    private string $ville_depart;

    #[ORM\Column(type: "string", length: 70)]
    private string $ville_arrivee;

    #[ORM\Column(type: "date", nullable: true)]
    private ?\DateTimeInterface $date_depart = null;

    #[ORM\Column(type: "date", nullable: true)]
private ?\DateTimeInterface $date_arrive = null;

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

    public function getDescription()
    {
        return $this->description;
    }

    public function setDescription($value)
    {
        $this->description = $value;
    }

    public function getPrix()
    {
        return $this->prix;
    }

    public function setPrix($value)
    {
        $this->prix = $value;
    }

    public function getDisponibilite()
    {
        return $this->disponibilite;
    }

    public function setDisponibilite($value)
    {
        $this->disponibilite = $value;
    }

    public function getCapacite()
    {
        return $this->capacite;
    }

    public function setCapacite($value)
    {
        $this->capacite = $value;
    }

    public function getLocalisation()
    {
        return $this->localisation;
    }

    public function setLocalisation($value)
    {
        $this->localisation = $value;
    }

    public function getImg_url()
    {
        return $this->img_url;
    }

    public function setImg_url($value)
    {
        $this->img_url = $value;
    }

    public function getType()
    {
        return $this->type;
    }

    public function setType($value)
    {
        $this->type = $value;
    }

    public function getNombre_etoiles()
    {
        return $this->nombre_etoiles;
    }

    public function setNombre_etoiles($value)
    {
        $this->nombre_etoiles = $value;
    }

    public function getType_chambre()
    {
        return $this->type_chambre;
    }

    public function setType_chambre($value)
    {
        $this->type_chambre = $value;
    }

    public function getNumero_vol()
    {
        return $this->numero_vol;
    }

    public function setNumero_vol($value)
    {
        $this->numero_vol = $value;
    }

    public function getVille_depart()
    {
        return $this->ville_depart;
    }

    public function setVille_depart($value)
    {
        $this->ville_depart = $value;
    }

    public function getVille_arrivee()
    {
        return $this->ville_arrivee;
    }

    public function setVille_arrivee($value)
    {
        $this->ville_arrivee = $value;
    }

    public function getdate_Depart(): ?\DateTimeInterface
    {
        return $this->date_depart;
    }
    
    public function setdate_Depart(?\DateTimeInterface $value): void
    {
        $this->date_depart = $value;
    }
    
    public function getdate_arrive(): ?\DateTimeInterface
    {
        return $this->date_arrive;
    }
    
    public function setdate_arrive(?\DateTimeInterface $value): void
    {
        $this->date_arrive = $value;
    }
    public function isDisponibilite(): bool
    {
        return in_array($this->disponibilite, [true, 1, '1', 'true', 'Disponible'], true);
    }
    #[ORM\OneToMany(mappedBy: "idService", targetEntity: Reservations::class)]
    private Collection $reservationss;

    public function __construct()
    {
        $this->reservationss = new ArrayCollection();
    }

        public function getReservationss(): Collection
        {
            return $this->reservationss;
        }
    
        public function addReservations(Reservations $reservations): self
        {
            if (!$this->reservationss->contains($reservations)) {
                $this->reservationss[] = $reservations;
                $reservations->setIdService($this);
            }
    
            return $this;
        }
    
        public function removeReservations(Reservations $reservations): self
        {
            if ($this->reservationss->removeElement($reservations)) {
                // set the owning side to null (unless already changed)
                if ($reservations->getIdService() === $this) {
                    $reservations->setIdService(null);
                }
            }
    
            return $this;
        }

        public function getImgUrl(): ?string
        {
            return $this->img_url;
        }

        public function setImgUrl(string $img_url): static
        {
            $this->img_url = $img_url;

            return $this;
        }

        public function getNombreEtoiles(): ?int
        {
            return $this->nombre_etoiles;
        }

        public function setNombreEtoiles(int $nombre_etoiles): static
        {
            $this->nombre_etoiles = $nombre_etoiles;

            return $this;
        }

        public function getTypeChambre(): ?string
        {
            return $this->type_chambre;
        }

        public function setTypeChambre(string $type_chambre): static
        {
            $this->type_chambre = $type_chambre;

            return $this;
        }

        public function getNumeroVol(): ?string
        {
            return $this->numero_vol;
        }

        public function setNumeroVol(string $numero_vol): static
        {
            $this->numero_vol = $numero_vol;

            return $this;
        }

        public function getVilleDepart(): ?string
        {
            return $this->ville_depart;
        }

        public function setVilleDepart(string $ville_depart): static
        {
            $this->ville_depart = $ville_depart;

            return $this;
        }

        public function getVilleArrivee(): ?string
        {
            return $this->ville_arrivee;
        }

        public function setVilleArrivee(string $ville_arrivee): static
        {
            $this->ville_arrivee = $ville_arrivee;

            return $this;
        }

        public function getDateDepart(): ?\DateTime
        {
            return $this->date_depart;
        }

        public function setDateDepart(\DateTime $date_depart): static
        {
            $this->date_depart = $date_depart;

            return $this;
        }

        public function getDateArrive(): ?\DateTime
        {
            return $this->date_arrive;
        }

        public function setDateArrive(\DateTime $date_arrive): static
        {
            $this->date_arrive = $date_arrive;

            return $this;
        }

        public function addReservationss(Reservations $reservationss): static
        {
            if (!$this->reservationss->contains($reservationss)) {
                $this->reservationss->add($reservationss);
                $reservationss->setIdService($this);
            }

            return $this;
        }

        public function removeReservationss(Reservations $reservationss): static
        {
            if ($this->reservationss->removeElement($reservationss)) {
                // set the owning side to null (unless already changed)
                if ($reservationss->getIdService() === $this) {
                    $reservationss->setIdService(null);
                }
            }

            return $this;
        }
}
