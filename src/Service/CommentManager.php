<?php

namespace App\Service;

use App\Entity\Comment;
use InvalidArgumentException;

class CommentManager
{
    public function validate(Comment $comment): bool
    {
        $content = $comment->getContent();

        if ($content === null || trim($content) === '') {
            throw new InvalidArgumentException('Le contenu du commentaire est obligatoire.');
        }

        if ($comment->getUser() === null) {
            throw new InvalidArgumentException('Le commentaire doit être associé à un utilisateur.');
        }

        if ($comment->getPublication() === null) {
            throw new InvalidArgumentException('Le commentaire doit être associé à une publication.');
        }

        return true;
    }
}
