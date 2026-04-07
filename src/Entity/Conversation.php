<?php

namespace App\Entity;

use App\Enum\TypeConversation;
use App\Repository\ConversationRepository;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

#[ORM\Entity(repositoryClass: ConversationRepository::class)]
#[ORM\Table(name: 'conversation')]
class Conversation
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: "IDENTITY")]
    #[ORM\Column(name: 'idConversation', type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'type', enumType: TypeConversation::class)]
    private ?TypeConversation $type = null;

    #[ORM\Column(name: 'dateCreation')]
    private ?\DateTime $dateCreation = null;

    #[ORM\Column(name: 'titre', length: 100, nullable: true)]
    private ?string $titre = null;

    #[ORM\OneToMany(mappedBy: 'idConversation', targetEntity: ParticipantConversation::class)]
    private Collection $participants;

    /**
     * @var Collection<int, Messages>
     */
    #[ORM\OneToMany(targetEntity: Messages::class, mappedBy: 'idConversation')]
    private Collection $messages;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getType(): ?TypeConversation
    {
        return $this->type;
    }

    public function setType(TypeConversation $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getDateCreation(): ?\DateTime
    {
        return $this->dateCreation;
    }

    public function setDateCreation(\DateTime $dateCreation): static
    {
        $this->dateCreation = $dateCreation;

        return $this;
    }

    public function getTitre(): ?string
    {
        return $this->titre;
    }

    public function setTitre(?string $titre): static
    {
        $this->titre = $titre;

        return $this;
    }
    /**
     * @return Collection<int, ParticipantConversation>
     */
    public function __construct()
    {
        // 2. Initialise la collection (TRÈS IMPORTANT)
        $this->participants = new ArrayCollection();
        $this->messages = new ArrayCollection();
    }

    /**
     * @return Collection<int, ParticipantConversation>
     */
    public function getParticipants(): Collection
    {
        return $this->participants;
    }

    public function addParticipant(ParticipantConversation $participant): self
    {
        if (!$this->participants->contains($participant)) {
            $this->participants->add($participant);
            $participant->setIdConversation($this);
        }
        return $this;
    }

    public function removeParticipant(ParticipantConversation $participant): self
    {
        if ($this->participants->removeElement($participant)) {
            // set the owning side to null (unless already changed)
            if ($participant->getIdConversation() === $this) {
                $participant->setIdConversation(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, Messages>
     */
    public function getMessages(): Collection
    {
        return $this->messages;
    }

    public function addMessage(Messages $message): static
    {
        if (!$this->messages->contains($message)) {
            $this->messages->add($message);
            $message->setIdConversation($this);
        }

        return $this;
    }

    public function removeMessage(Messages $message): static
    {
        if ($this->messages->removeElement($message)) {
            // set the owning side to null (unless already changed)
            if ($message->getIdConversation() === $this) {
                $message->setIdConversation(null);
            }
        }

        return $this;
    }

    public function getDisplayName($currentUser): string
    {
        if ($this->getType()->value === 'GROUPE') {
            return $this->getTitre() ?? 'Unnamed Group';
        }

        foreach ($this->getParticipants() as $participant) {
            $user = $participant->getIdUtilisateur();

            // FIX: Check if $user is NOT NULL before calling getId() or getName()
            if ($user && $user->getId() !== $currentUser->getId()) {
                return ($user->getLastName() ?? '') . ' ' . ($user->getName() ?? 'Unknown');
            }
        }

        return 'Private Chat (Other user deleted)';
    }

    // src/Entity/Conversation.php

    public function getLastMessage(): ?Messages
    {
        if ($this->messages->isEmpty()) {
            return null;
        }

        // Convert to array to make sorting easier for the IDE
        $msgArray = $this->messages->toArray();

        // Sort: newest first
        usort($msgArray, function ($a, $b) {
            return $b->getDateEnvoi() <=> $a->getDateEnvoi();
        });

        return $msgArray[0];
    }
}
