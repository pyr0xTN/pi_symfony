<?php

namespace App\Entity;

use App\Repository\LikeRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LikeRepository::class)]
#[ORM\Table(name: '`like`')]
class Like
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'likeID', type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Publication::class, inversedBy: 'likes')]
    #[ORM\JoinColumn(name: 'publicationID', referencedColumnName: 'publicationID', nullable: false)]
    private ?Publication $publication = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false)]
    private ?User $user = null;
    #[ORM\Column(name: 'commentID', type: 'integer', options: ['default' => 0])]
    private int $commentId = 0;

    public function getId(): ?int { return $this->id; }

    public function getPublication(): ?Publication { return $this->publication; }
    public function setPublication(?Publication $publication): static { $this->publication = $publication; return $this; }

    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): static { $this->user = $user; return $this; }

    public function getCommentId(): int { return $this->commentId; }
    public function setCommentId(int $commentId): static { $this->commentId = $commentId; return $this; }
}
