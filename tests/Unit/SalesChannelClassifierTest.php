<?php

declare(strict_types=1);

namespace Hashieban\Tests\Unit;

use Hashieban\Integration\WooCommerce\Attribution\SalesChannelClassifier;
use Hashieban\Tests\TestCase;

final class SalesChannelClassifierTest extends TestCase
{
    public function testDetectsTorobFromReferrer(): void
    {
        $result = $this->classifier()->classify(
            array(
                'source_type' => 'referral',
                'referrer' => 'https://torob.com/p/123',
            )
        );

        $this->assertSame('torob', $result['channel_key']);
        $this->assertSame('ترب', $result['channel_name']);
        $this->assertTrue($result['known']);
        $this->assertSame('torob.com', $result['referrer_domain']);
    }

    public function testDetectsEmallsFromSource(): void
    {
        $result = $this->classifier()->classify(
            array(
                'source_type' => 'utm',
                'source' => 'emalls',
                'medium' => 'cpc',
                'campaign' => 'summer',
            )
        );

        $this->assertSame('emalls', $result['channel_key']);
        $this->assertSame('comparison', $result['channel_group']);
        $this->assertSame('summer', $result['campaign']);
    }

    public function testDetectsSocialAndSearchSources(): void
    {
        $instagram = $this->classifier()->classify(
            array('referrer' => 'https://l.instagram.com/?u=shop')
        );
        $google = $this->classifier()->classify(
            array(
                'source_type' => 'organic',
                'referrer' => 'https://www.google.com/search?q=shop',
            )
        );

        $this->assertSame('instagram', $instagram['channel_key']);
        $this->assertSame('google', $google['channel_key']);
        $this->assertSame('search', $google['channel_group']);
    }

    public function testDirectAndAdminAreKnownSources(): void
    {
        $direct = $this->classifier()->classify(
            array('source_type' => 'typein')
        );
        $manual = $this->classifier()->classify(
            array('source_type' => 'admin')
        );

        $this->assertSame('direct', $direct['channel_key']);
        $this->assertTrue($direct['known']);
        $this->assertSame('manual', $manual['channel_key']);
        $this->assertTrue($manual['known']);
    }

    public function testUnknownOrdersAreNotGuessed(): void
    {
        $result = $this->classifier()->classify(array());

        $this->assertSame('unknown', $result['channel_key']);
        $this->assertSame('بدون داده منبع', $result['channel_name']);
        $this->assertFalse($result['known']);
    }

    public function testGenericUtmSourceRemainsAvailable(): void
    {
        $result = $this->classifier()->classify(
            array(
                'source_type' => 'utm',
                'source' => 'affiliate_partner',
                'medium' => 'affiliate',
                'campaign' => 'launch_1405',
            )
        );

        $this->assertSame('source_affiliate_partner', $result['channel_key']);
        $this->assertSame('affiliate_partner', $result['channel_name']);
        $this->assertSame('launch_1405', $result['campaign']);
        $this->assertTrue($result['known']);
    }

    public function testEmailMediumGetsUsefulPersianChannel(): void
    {
        $result = $this->classifier()->classify(
            array(
                'source_type' => 'utm',
                'source' => 'crm',
                'medium' => 'email',
            )
        );

        $this->assertSame('email', $result['channel_key']);
        $this->assertSame('ایمیل و خبرنامه', $result['channel_name']);
    }

    private function classifier(): SalesChannelClassifier
    {
        return new SalesChannelClassifier();
    }
}
