<?php

namespace App\Entity;

use App\Repository\CommentRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CommentRepository::class)]
#[ORM\Table(name: 'comment')]
class Comment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'commentID', type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Publication::class, inversedBy: 'comments')]
    #[ORM\JoinColumn(name: 'publicationID', referencedColumnName: 'publicationID', nullable: false)]
    private ?Publication $publication = null;

    #[ORM\ManyToOne(targetEntity: Client::class)]
    #[ORM\JoinColumn(name: 'client_id', referencedColumnName: 'clientID', nullable: false)]
    private ?Client $client = null;

    #[ORM\Column(name: 'content', type: 'text')]
    private ?string $content = null;

    #[ORM\Column(name: 'commentDate', type: 'datetime')]
    private ?\DateTimeInterface $commentDate = null;

    public function __construct()
    {
        $this->commentDate = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }

    public function getPublication(): ?Publication { return $this->publication; }
    public function setPublication(?Publication $publication): static { $this->publication = $publication; return $this; }

    public function getClient(): ?Client { return $this->client; }
    public function setClient(?Client $client): static { $this->client = $client; return $this; }

    public function getContent(): ?string { return $this->content; }
    public function setContent(string $content): static { $this->content = $content; return $this; }

    public function getCommentDate(): ?\DateTimeInterface { return $this->commentDate; }
    public function setCommentDate(\DateTimeInterface $date): static { $this->commentDate = $date; return $this; }

    public function getTimeAgo(): string
    {
        if (!$this->commentDate) return '';
        $diff = (new \DateTime())->getTimestamp() - $this->commentDate->getTimestamp();
        $days = intdiv($diff, 86400);
        $hours = intdiv($diff, 3600);
        $minutes = intdiv($diff, 60);

        if ($days > 0) return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
        if ($hours > 0) return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
        if ($minutes > 0) return $minutes . ' minute' . ($minutes > 1 ? 's' : '') . ' ago';
        return 'Just now';
    }
}
