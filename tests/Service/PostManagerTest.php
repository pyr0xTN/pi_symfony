<?php

namespace App\Tests\Service;

use App\Entity\Publication;
use App\Entity\User;
use App\Service\PostManager;
use PHPUnit\Framework\TestCase;

class PostManagerTest extends TestCase
{
    private PostManager $manager;

    protected function setUp(): void
    {
        $this->manager = new PostManager();
    }

    public function testValidPublication(): void
    {
        $user = $this->createMock(User::class);

        $publication = new Publication();
        $publication->setContent('Découverte incroyable à Sidi Bou Saïd !');
        $publication->setUser($user);

        $this->assertTrue($this->manager->validate($publication));
    }

    public function testEmptyContentThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le contenu de la publication est obligatoire.');

        $user = $this->createMock(User::class);
        $publication = new Publication();
        $publication->setContent('');
        $publication->setUser($user);

        $this->manager->validate($publication);
    }

    public function testShortContentThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le contenu doit contenir au moins 10 caractères.');

        $user = $this->createMock(User::class);
        $publication = new Publication();
        $publication->setContent('Hello');
        $publication->setUser($user);

        $this->manager->validate($publication);
    }

    public function testNullUserThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('La publication doit être associée à un utilisateur.');

        $publication = new Publication();
        $publication->setContent('Ceci est un contenu valide pour le test.');

        $this->manager->validate($publication);
    }

    public function testInvalidStatusThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le statut de la publication est invalide.');

        $user = $this->createMock(User::class);
        $publication = new Publication();
        $publication->setContent('Ceci est un contenu valide pour le test.');
        $publication->setUser($user);
        $publication->setStatus('INVALID_STATUS');

        $this->manager->validate($publication);
    }
}
