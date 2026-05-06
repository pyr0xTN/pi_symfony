<?php

namespace App\Tests\Unit;

use App\Entity\Offer;
use App\Entity\Rating;
use App\Entity\Reservation;
use App\Entity\User;
use App\Entity\Actualite;
use PHPUnit\Framework\TestCase;

class OfferModuleTest extends TestCase
{
    // ── Test 1: Offer entity getters/setters ──
    public function testOfferTitleAndPrice(): void
    {
        $offer = new Offer();
        $offer->setTitle('Beach Holiday in Djerba');
        $offer->setPromoPrice('1200.00');
        $offer->setOriginalPrice('1500.00');
        $offer->setStatus('ACTIVE');

        $this->assertSame('Beach Holiday in Djerba', $offer->getTitle());
        $this->assertSame('1200.00', $offer->getPromoPrice());
        $this->assertSame('1500.00', $offer->getOriginalPrice());
        $this->assertSame('ACTIVE', $offer->getStatus());
    }

    // ── Test 2: Offer location and dates ──
    public function testOfferLocationAndDates(): void
    {
        $offer     = new Offer();
        $startDate = new \DateTimeImmutable('2026-06-10');
        $endDate   = new \DateTimeImmutable('2026-06-20');

        $offer->setLocation('Tunis, Tunisia');
        $offer->setStartDate($startDate);
        $offer->setEndDate($endDate);

        $this->assertSame('Tunis, Tunisia', $offer->getLocation());
        $this->assertSame('2026-06-10', $offer->getStartDate()->format('Y-m-d'));
        $this->assertSame('2026-06-20', $offer->getEndDate()->format('Y-m-d'));
    }

    // ── Test 3: Offer capacity ──
    public function testOfferCapacity(): void
    {
        $offer = new Offer();
        $offer->setCapacity(20);

        $this->assertSame(20, $offer->getCapacity());
        $this->assertGreaterThan(0, $offer->getCapacity());
    }

    // ── Test 4: Reservation entity ──
    public function testReservationStatusAndAmount(): void
    {
        $reservation = new Reservation();
        $reservation->setStatus('CONFIRMED');
        $reservation->setNumberOfPersons(2);
        $reservation->setTotalAmount('2400.00');
        $reservation->setPaymentStatus('PAID');

        $this->assertSame('CONFIRMED', $reservation->getStatus());
        $this->assertSame(2, $reservation->getNumberOfPersons());
        $this->assertSame('2400.00', $reservation->getTotalAmount());
        $this->assertSame('PAID', $reservation->getPaymentStatus());
    }

    // ── Test 5: Rating entity stars validation ──
    public function testRatingStarsRange(): void
    {
        $rating = new Rating();

        $rating->setStars(5);
        $this->assertSame(5, $rating->getStars());

        $rating->setStars(1);
        $this->assertSame(1, $rating->getStars());

        // Test boundary — should cap at 5
        $rating->setStars(10);
        $this->assertSame(5, $rating->getStars());

        // Test boundary — should floor at 1
        $rating->setStars(0);
        $this->assertSame(1, $rating->getStars());
    }

    // ── Test 6: Actualite (banner) entity ──
    public function testActualiteBannerActive(): void
    {
        $banner = new Actualite();
        $banner->setTitre('Summer Promo 2026');
        $banner->setBannerUrl('https://res.cloudinary.com/test/image.jpg');
        $banner->setIsActive(true);

        $this->assertSame('Summer Promo 2026', $banner->getTitre());
        $this->assertTrue($banner->isActive());
        $this->assertTrue($banner->isCurrentlyActive());
    }

    // ── Test 7: Banner expired ──
    public function testActualiteBannerExpired(): void
    {
        $banner = new Actualite();
        $banner->setIsActive(true);
        $banner->setEndsAt(new \DateTimeImmutable('2020-01-01')); // past date

        $this->assertFalse($banner->isCurrentlyActive());
    }

    // ── Test 8: Offer status archive ──
    public function testOfferArchiveStatus(): void
    {
        $offer = new Offer();
        $offer->setStatus('ACTIVE');
        $this->assertSame('ACTIVE', $offer->getStatus());

        $offer->setStatus('ARCHIVED');
        $this->assertSame('ARCHIVED', $offer->getStatus());
        $this->assertNotSame('ACTIVE', $offer->getStatus());
    }
}
