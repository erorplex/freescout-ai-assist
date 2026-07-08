<?php

namespace Modules\AiAssist\Tests;

use Modules\AiAssist\Services\PromptBuilder;
use PHPUnit\Framework\TestCase;

class DetectChannelTest extends TestCase
{
    public function testEbayMemberAddress(): void
    {
        $this->assertSame('ebay', PromptBuilder::detectChannel('buyer@members.ebay.de'));
        $this->assertSame('ebay', PromptBuilder::detectChannel('X@MEMBERS.EBAY.COM'));
    }

    public function testAmazonMarketplaceAddress(): void
    {
        $this->assertSame('amazon', PromptBuilder::detectChannel('a1b2@marketplace.amazon.de'));
    }

    public function testEverythingElseIsEmail(): void
    {
        $this->assertSame('email', PromptBuilder::detectChannel('kunde@gmail.com'));
        $this->assertSame('email', PromptBuilder::detectChannel(''));
    }
}
