<?php

namespace App\Tests\Service;

use App\Entity\Comment;
use App\Entity\Publication;
use App\Entity\User;
use App\Service\CommentManager;
use PHPUnit\Framework\TestCase;

class CommentManagerTest extends TestCase
{
    private CommentManager $manager;

    protected function setUp(): void
    {
        $this->manager = new CommentManager();
    }

    public function testValidComment(): void
    {
        $user = $this->createMock(User::class);
        $publication = $this->createMock(Publication::class);

        $comment = new Comment();
        $comment->setContent('Superbe voyage, merci pour le partage !');
        $comment->setUser($user);
        $comment->setPublication($publication);

        $this->assertTrue($this->manager->validate($comment));
    }

    public function testEmptyContentThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le contenu du commentaire est obligatoire.');

        $user = $this->createMock(User::class);
        $publication = $this->createMock(Publication::class);

        $comment = new Comment();
        $comment->setContent('');
        $comment->setUser($user);
        $comment->setPublication($publication);

        $this->manager->validate($comment);
    }

    public function testNullUserThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le commentaire doit être associé à un utilisateur.');

        $publication = $this->createMock(Publication::class);

        $comment = new Comment();
        $comment->setContent('Un commentaire valide.');
        $comment->setPublication($publication);

        $this->manager->validate($comment);
    }

    public function testNullPublicationThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le commentaire doit être associé à une publication.');

        $user = $this->createMock(User::class);

        $comment = new Comment();
        $comment->setContent('Un commentaire valide.');
        $comment->setUser($user);

        $this->manager->validate($comment);
    }
}
