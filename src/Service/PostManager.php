<?php

namespace App\Service;

use App\Entity\Publication;
use InvalidArgumentException;

class PostManager
{
    public function validate(Publication $publication): bool
    {
        $content = $publication->getContent();

        if ($content === null || trim($content) === '') {
            throw new InvalidArgumentException('Le contenu de la publication est obligatoire.');
        }

        if (mb_strlen(trim($content)) < 10) {
            throw new InvalidArgumentException('Le contenu doit contenir au moins 10 caractères.');
        }

        if ($publication->getUser() === null) {
            throw new InvalidArgumentException('La publication doit être associée à un utilisateur.');
        }

        $valid = [Publication::STATUS_PENDING, Publication::STATUS_APPROVED, Publication::STATUS_REJECTED];
        if (!in_array($publication->getStatus(), $valid, true)) {
            throw new InvalidArgumentException('Le statut de la publication est invalide.');
        }

        return true;
    }
}
