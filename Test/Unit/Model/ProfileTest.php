<?php
declare(strict_types=1);

namespace Digitalway\SocialLogin\Test\Unit\Model;

use Digitalway\SocialLogin\Model\Profile;
use PHPUnit\Framework\TestCase;

class ProfileTest extends TestCase
{
    public function testArrayRoundTrip(): void
    {
        $profile = new Profile('google', '123', 'a@b.it', true, 'Mario', 'Rossi');

        self::assertEquals($profile, Profile::fromArray($profile->toArray()));
    }

    public function testRoundTripKeepsNullEmail(): void
    {
        $profile = new Profile('instagram', '99', null, false, 'mario', '');

        self::assertNull(Profile::fromArray($profile->toArray())->email);
    }

    public function testWithContactDataMarksEmailAsUnverified(): void
    {
        $profile = new Profile('instagram', '99', null, false, 'mario', '');

        $completed = $profile->withContactData('m@r.it', 'Mario', 'Rossi');

        self::assertSame('m@r.it', $completed->email);
        self::assertFalse($completed->emailVerified);
        self::assertSame('Mario', $completed->firstname);
        self::assertSame('Rossi', $completed->lastname);
        self::assertSame('instagram', $completed->provider);
        self::assertSame('99', $completed->providerUserId);
    }

    public function testHasVerifiedEmail(): void
    {
        self::assertTrue((new Profile('g', '1', 'a@b.it', true, 'A', 'B'))->hasVerifiedEmail());
        self::assertFalse((new Profile('g', '1', 'a@b.it', false, 'A', 'B'))->hasVerifiedEmail());
        self::assertFalse((new Profile('g', '1', null, true, 'A', 'B'))->hasVerifiedEmail());
    }

    public function testHasNames(): void
    {
        self::assertTrue((new Profile('g', '1', null, false, 'A', 'B'))->hasNames());
        self::assertFalse((new Profile('g', '1', null, false, 'A', ''))->hasNames());
        self::assertFalse((new Profile('g', '1', null, false, '', 'B'))->hasNames());
    }

    public function testFromArrayRejectsMissingIdentity(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Profile::fromArray(['provider' => 'google', 'provider_user_id' => '']);
    }
}
