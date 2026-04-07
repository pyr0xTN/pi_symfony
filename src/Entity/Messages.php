<?php

namespace App\Entity;

use App\Enum\TypeMessage;
use App\Repository\MessagesRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MessagesRepository::class)]
#[ORM\Table(name: 'message')]
class Messages
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'idMessage')]
    private ?int $id = null;

    #[ORM\Column(name: 'contenu', type: Types::TEXT)]
    private ?string $contenu = null;

    #[ORM\Column(name: 'dateEnvoi')]
    private ?\DateTime $dateEnvoi = null;

    #[ORM\Column(name: 'lu')]
    private ?bool $lu = null;

    #[ORM\ManyToOne(inversedBy: 'messages')]
    #[ORM\JoinColumn(nullable: false, name: 'idConversation', referencedColumnName: 'idConversation')]
    private ?Conversation $idConversation = null;

    #[ORM\ManyToOne(inversedBy: 'messagesEnvoyees')]
    #[ORM\JoinColumn(nullable: false, name: 'idExpediteur', referencedColumnName: 'id')] // <--- ADD name: 'idExpediteur'
    private ?User $idExpediteur = null;

    #[ORM\Column(name: 'typeMessage', enumType: TypeMessage::class)]
    private ?TypeMessage $typeMessage = null;

    #[ORM\Column(name: 'urlFichier', length: 255, nullable: true)]
    private ?string $urlFichier = null;

    #[ORM\Column(name: 'reaction', length: 255, nullable: true)]
    private ?string $reaction = null;

    #[ORM\Column(name: 'isDeleted')]
    private ?bool $isDeleted = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getContenu(): ?string
    {
        return $this->contenu;
    }

    public function setContenu(string $contenu): static
    {
        $this->contenu = $contenu;

        return $this;
    }

    public function getDateEnvoi(): ?\DateTime
    {
        return $this->dateEnvoi;
    }

    public function setDateEnvoi(\DateTime $dateEnvoi): static
    {
        $this->dateEnvoi = $dateEnvoi;

        return $this;
    }

    public function isLu(): ?bool
    {
        return $this->lu;
    }

    public function setLu(bool $lu): static
    {
        $this->lu = $lu;

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

    public function getIdExpediteur(): ?User
    {
        return $this->idExpediteur;
    }

    public function setIdExpediteur(?User $idExpediteur): static
    {
        $this->idExpediteur = $idExpediteur;

        return $this;
    }

    public function getTypeMessage(): ?TypeMessage
    {
        return $this->typeMessage;
    }

    public function setTypeMessage(TypeMessage $typeMessage): static
    {
        $this->typeMessage = $typeMessage;

        return $this;
    }

    public function getUrlFichier(): ?string
    {
        return $this->urlFichier;
    }

    public function setUrlFichier(?string $urlFichier): static
    {
        $this->urlFichier = $urlFichier;

        return $this;
    }

    public function getReaction(): ?string
    {
        return $this->reaction;
    }

    public function setReaction(?string $reaction): static
    {
        $this->reaction = $reaction;

        return $this;
    }

    public function isDeleted(): ?bool
    {
        return $this->isDeleted;
    }

    public function setIsDeleted(bool $isDeleted): static
    {
        $this->isDeleted = $isDeleted;

        return $this;
    }
}
