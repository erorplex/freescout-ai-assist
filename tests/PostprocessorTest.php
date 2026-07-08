<?php

namespace Modules\AiAssist\Tests;

use Modules\AiAssist\Services\Postprocessor;
use PHPUnit\Framework\TestCase;

class PostprocessorTest extends TestCase
{
    public function testStripUrlsRemovesMarkdownHtmlAndBare(): void
    {
        $in = 'Siehe [Shop](https://shop.de/x) und <a href="https://a.de">hier</a> oder https://b.de sowie www.c.de. Danke.';
        $out = Postprocessor::stripUrls($in);
        $this->assertStringContainsString('Siehe Shop und hier oder', $out);
        $this->assertStringNotContainsString('http', $out);
        $this->assertStringNotContainsString('www.', $out);
        $this->assertStringContainsString('Danke.', $out);
    }

    public function testStripContactRemovesEmailAndMailto(): void
    {
        $in = 'Schreiben Sie an mailto:info@shop.de oder service@shop.de. Ende.';
        $out = Postprocessor::stripContact($in);
        $this->assertStringNotContainsString('@', $out);
        $this->assertStringNotContainsString('mailto:', $out);
        $this->assertStringContainsString('Schreiben Sie an', $out);
        $this->assertStringContainsString('Ende.', $out);
    }

    public function testApplyBlockStripsLinksAndEmailsKeepsText(): void
    {
        $eff = ['links_policy' => 'block', 'signature' => false];
        $draft = 'Hallo, mehr unter https://shop.de oder info@shop.de.';
        $out = Postprocessor::apply($draft, $eff, '', null);
        $this->assertStringNotContainsString('http', $out);
        $this->assertStringNotContainsString('@', $out);
        $this->assertStringContainsString('Hallo', $out);
    }

    public function testApplyAllowIsByteIdentical(): void
    {
        $eff = ['links_policy' => 'allow', 'signature' => false];
        $draft = 'Hallo https://shop.de und info@shop.de bleibt.';
        $this->assertSame($draft, Postprocessor::apply($draft, $eff, 'IGN', null));
    }

    public function testApplyAppendsSignatureWhenEnabled(): void
    {
        $eff = ['links_policy' => 'allow', 'signature' => true];
        $out = Postprocessor::apply('Text.', $eff, "Ihr Team\nPixkom", null);
        $this->assertSame("Text.\n\nIhr Team\nPixkom", $out);
    }

    public function testApplyOmitsSignatureWhenDisabled(): void
    {
        $eff = ['links_policy' => 'allow', 'signature' => false];
        $this->assertSame('Text.', Postprocessor::apply('Text.', $eff, 'Ihr Team', null));
    }

    public function testQuoteOriginalAppendedOutsideStrippedDraftUnstripped(): void
    {
        $eff = ['links_policy' => 'block', 'signature' => false, 'quote_original' => true];
        // draft is stripped; the quoted original (with its URL) is preserved verbatim
        $quoted = "----- Urspruengliche Nachricht -----\nBitte https://ebay.de/itm/1 ansehen.";
        $out = Postprocessor::apply('Antwort ohne https://x.de Link.', $eff, '', $quoted);
        $this->assertStringNotContainsString('https://x.de', $out);        // draft stripped
        $this->assertStringContainsString('https://ebay.de/itm/1', $out);   // quote NOT stripped
        $this->assertStringContainsString('Urspruengliche Nachricht', $out);
    }
}
