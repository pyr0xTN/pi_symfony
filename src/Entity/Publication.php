<?php

namespace App\Entity;

use App\Repository\PublicationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PublicationRepository::class)]
#[ORM\Table(name: 'publication')]
class Publication
{
    public const STATUS_PENDING  = 'PENDING';
    public const STATUS_APPROVED = 'APPROVED';
    public const STATUS_REJECTED = 'REJECTED';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'publicationID', type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'content', type: 'text')]
    private ?string $content = null;

    #[ORM\Column(name: 'datePublication', type: 'datetime')]
    private ?\DateTimeInterface $datePublication = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false)]
    private ?User $user = null;

    #[ORM\Column(name: 'image_path', type: 'string', length: 500, nullable: true)]
    private ?string $imagePath = null;

    #[ORM\Column(name: 'place', type: 'string', length: 255, nullable: true)]
    private ?string $place = null;

    // agencyId removed, as User entity handles roles

    #[ORM\Column(name: 'status', type: 'string', length: 20, options: ['default' => 'APPROVED'])]
    private string $status = self::STATUS_APPROVED;

    #[ORM\OneToMany(targetEntity: Comment::class, mappedBy: 'publication', cascade: ['remove'])]
    #[ORM\OrderBy(['commentDate' => 'ASC'])]
    private Collection $comments;

    #[ORM\OneToMany(targetEntity: Like::class, mappedBy: 'publication', cascade: ['remove'])]
    private Collection $likes;

    public function __construct()
    {
        $this->comments = new ArrayCollection();
        $this->likes = new ArrayCollection();
        $this->datePublication = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }

    public function getContent(): ?string { return $this->content; }
    public function setContent(string $content): static { $this->content = $content; return $this; }

    public function getDatePublication(): ?\DateTimeInterface { return $this->datePublication; }
    public function setDatePublication(\DateTimeInterface $date): static { $this->datePublication = $date; return $this; }

    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): static { $this->user = $user; return $this; }

    public function getImagePath(): ?string { return $this->imagePath; }
    public function setImagePath(?string $imagePath): static { $this->imagePath = $imagePath; return $this; }

    public function getPlace(): ?string { return $this->place; }
    public function setPlace(?string $place): static { $this->place = $place; return $this; }

    // removed getAgencyId and setAgencyId

    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): static { $this->status = $status; return $this; }

    /** @return Collection<int, Comment> */
    public function getComments(): Collection { return $this->comments; }

    /** @return Collection<int, Like> */
    public function getLikes(): Collection { return $this->likes; }

    public function isPending(): bool  { return $this->status === self::STATUS_PENDING; }
    public function isApproved(): bool { return $this->status === self::STATUS_APPROVED; }
    public function isRejected(): bool { return $this->status === self::STATUS_REJECTED; }
    public function hasAgency(): bool  { return $this->user && in_array('ROLE_AGENCY', $this->user->getRoles()); }
    public function hasImage(): bool   { return $this->imagePath !== null && trim($this->imagePath) !== ''; }
    public function hasPlace(): bool   { return $this->place !== null && trim($this->place) !== ''; }

    public function getTimeAgo(): string
    {
        if (!$this->datePublication) return '';
        $diff = (new \DateTime())->getTimestamp() - $this->datePublication->getTimestamp();
        $days = intdiv($diff, 86400);
        $hours = intdiv($diff, 3600);
        $minutes = intdiv($diff, 60);

        if ($days > 0) return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
        if ($hours > 0) return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
        if ($minutes > 0) return $minutes . ' minute' . ($minutes > 1 ? 's' : '') . ' ago';
        return 'Just now';
    }
}
