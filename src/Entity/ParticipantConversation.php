<?php

namespace App\Entity;

use App\Repository\ParticipantConversationRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ParticipantConversationRepository::class)]
#[ORM\Table(name: 'participantconversation')]
class ParticipantConversation
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'NONE')]
    #[ORM\Column(name: 'idParticipant')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Conversation::class, inversedBy: 'participants')]
    #[ORM\JoinColumn(name: 'idConversation', referencedColumnName: 'idConversation', nullable: false)]
    private ?Conversation $idConversation = null;

    #[ORM\ManyToOne(inversedBy: 'participantConversations')]
    #[ORM\JoinColumn(name: 'idUtilisateur', referencedColumnName: 'id', nullable: false)] 
    private ?User $idUtilisateur = null;

    #[ORM\Column(name:'dateAjout', nullable: true)]
    private ?\DateTime $dateAjout = null;

    #[ORM\Column(name: 'estActif',nullable: true)]
    private ?bool $estActif = null;

    #[ORM\Column(name: 'dateSortie', type : 'datetime', nullable: true)] 
    private ?\DateTime $dateSortie = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): static
    {
        $this->id = $id;

        return $this;
    }

    public function getIdConversation(): ?Conversation
    {
        return $this->idConversation;
    }

    public function setIdConversation(?Conversation $idConversation): static
    {
        $this->idConversation = $idConversation;

        return $this;
    }

    public function getIdUtilisateur(): ?User
    {
        return $this->idUtilisateur;
    }

    public function setIdUtilisateur(?User $idUtilisateur): static
    {
        $this->idUtilisateur = $idUtilisateur;

        return $this;
    }

    public function getDateAjout(): ?\DateTime
    {
        return $this->dateAjout;
    }

    public function setDateAjout(\DateTime $dateAjout): static
    {
        $this->dateAjout = $dateAjout;

        return $this;
    }

    public function isEstActif(): ?bool
    {
        return $this->estActif;
    }

    public function setEstActif(?bool $estActif): static
    {
        $this->estActif = $estActif;

        return $this;
    }

    public function getDateSortie(): ?\DateTime
    {
        return $this->dateSortie;
    }

    public function setDateSortie(?\DateTime $dateSortie): static
    {
        $this->dateSortie = $dateSortie;

        return $this;
    }
}
