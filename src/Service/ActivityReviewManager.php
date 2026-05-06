<?php

namespace App\Service;
use App\Entity\ActivityReview;
use InvalidArgumentException;
class ActivityReviewManager {
    public function validate(ActivityReview $review): bool
    {
        // Règle 1 : le contenu est obligatoire
        if (trim($review->getContent()) === '') {
            throw new InvalidArgumentException(
                'Le contenu de l avis est obligatoire'
            );
        }

        // Règle 2 : l'activité doit exister
        if ($review->getActivityId() <= 0) {
            throw new InvalidArgumentException(
                'L identifiant de l activité est invalide'
            );
        }

        // Règle 3 : l'utilisateur doit être renseigné
        if ($review->getUser() === null) {
            throw new InvalidArgumentException(
                'L avis doit être associé à un utilisateur'
            );
        }

        return true;
    }
}