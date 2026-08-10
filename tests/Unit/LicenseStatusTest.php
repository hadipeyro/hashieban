<?php

declare(strict_types=1);

namespace Hashieban\Tests\Unit;

use Hashieban\Licensing\LicenseStatus;
use Hashieban\Tests\TestCase;

final class LicenseStatusTest extends TestCase
{
    public function testActiveAndGraceStatesAreUsable(): void
    {
        $active = new LicenseStatus(
            LicenseStatus::ACTIVE,
            'ok',
            'zhaket',
            'example.com',
            1000,
            1000
        );

        $grace = new LicenseStatus(
            LicenseStatus::GRACE,
            'temporary outage',
            'zhaket',
            'example.com',
            1200,
            1000
        );

        $this->assertTrue($active->isUsable());
        $this->assertTrue($active->isActive());
        $this->assertTrue($grace->isUsable());
        $this->assertFalse($grace->isActive());
    }

    public function testInvalidStateIsNotUsable(): void
    {
        $status = new LicenseStatus(
            LicenseStatus::INVALID,
            'invalid',
            'zhaket',
            'example.com',
            1000,
            0
        );

        $this->assertFalse($status->isUsable());
        $this->assertFalse(
            $status->isInsideGracePeriod(
                1100,
                604800
            )
        );
    }

    public function testStatusRoundTripAndTiming(): void
    {
        $status = new LicenseStatus(
            LicenseStatus::ACTIVE,
            'valid',
            'zhaket',
            'shop.example',
            1000,
            900
        );

        $restored = LicenseStatus::fromArray(
            $status->toArray()
        );

        $this->assertSame(
            LicenseStatus::ACTIVE,
            $restored->state()
        );
        $this->assertSame(
            'shop.example',
            $restored->domain()
        );
        $this->assertFalse(
            $restored->isStale(
                1100,
                86400
            )
        );
        $this->assertTrue(
            $restored->isStale(
                87400,
                86400
            )
        );
        $this->assertTrue(
            $restored->isInsideGracePeriod(
                1000,
                604800
            )
        );
    }
}
