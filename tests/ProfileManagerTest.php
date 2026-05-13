<?php

namespace App\Tests\Service;

use App\Entity\Profile;
use App\Service\ProfileManager;
use PHPUnit\Framework\TestCase;

class ProfileManagerTest extends TestCase
{
    // ✅ Test 1: valid profile passes validation
    public function testValidProfile(): void
    {
        $profile = new Profile();
        $profile->setCoins(100);
        $profile->setLanguage('English');
        $profile->setMemberPremium('yes');

        $manager = new ProfileManager();
        $this->assertTrue($manager->validate($profile));
    }

    // ❌ Test 2: negative coins throws exception
    public function testNegativeCoinsThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $profile = new Profile();
        $profile->setCoins(-5);
        $profile->setLanguage('English');
        $profile->setMemberPremium('yes');

        $manager = new ProfileManager();
        $manager->validate($profile);
    }

    // ❌ Test 3: invalid language throws exception
    public function testInvalidLanguageThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $profile = new Profile();
        $profile->setCoins(0);
        $profile->setLanguage('Arabic');  // not allowed
        $profile->setMemberPremium('yes');

        $manager = new ProfileManager();
        $manager->validate($profile);
    }

    // ❌ Test 4: invalid member_premium throws exception
    public function testInvalidMemberPremiumThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $profile = new Profile();
        $profile->setCoins(0);
        $profile->setLanguage('Francais');
        $profile->setMemberPremium('maybe');  // not allowed

        $manager = new ProfileManager();
        $manager->validate($profile);
    }

    // ✅ Test 5: zero coins is valid (edge case)
    public function testZeroCoinsIsValid(): void
    {
        $profile = new Profile();
        $profile->setCoins(0);
        $profile->setLanguage('Francais');
        $profile->setMemberPremium('no');

        $manager = new ProfileManager();
        $this->assertTrue($manager->validate($profile));
    }
}