<?php

namespace App\Service;
use App\Entity\ActivityReview;
use InvalidArgumentException;
class ActivityReviewManager {
    public function validate(ActivityReview $review): bool
    {
        //  le contenu obligatoire
        if (trim($review->getContent()) === '') {
            throw new InvalidArgumentException(
                'Le contenu de l avis est obligatoire'
            );
        }

        // l'activité exister
        if ($review->getActivityId() <= 0) {
            throw new InvalidArgumentException(
                'L identifiant de l activité est invalide'
            );
        }

        //l'utilisateur doit être renseigné
        if ($review->getUser() === null) {
            throw new InvalidArgumentException(
                'L avis doit être associé à un utilisateur'
            );
        }

        return true;
    }
}