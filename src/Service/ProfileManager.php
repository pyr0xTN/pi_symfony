<?php

namespace App\Service;

use App\Entity\Profile;

class ProfileManager
{
    public function validate(Profile $profile): bool
    {
        // Rule 1: coins cannot be negative
        if ($profile->getCoins() < 0) {
            throw new \InvalidArgumentException(
                'Coins cannot be negative'
            );
        }

        // Rule 2: language must be valid
        $validLanguages = ['English', 'Francais'];
        if (!in_array($profile->getLanguage(), $validLanguages, true)) {
            throw new \InvalidArgumentException(
                'Language must be English or Francais'
            );
        }

        // Rule 3: member_premium must be valid
        $validPremium = ['yes', 'no'];
        if (!in_array($profile->getMemberPremium(), $validPremium, true)) {
            throw new \InvalidArgumentException(
                'member_premium must be yes or no'
            );
        }

        return true;
    }
}