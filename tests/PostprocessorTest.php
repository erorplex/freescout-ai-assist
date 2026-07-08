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
        $out = Postprocessor::apply('Text.', $eff, "Ihr Team\nMusterfirma", null);
        $this->assertSame("Text.\n\nIhr Team\nMusterfirma", $out);
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

    public function testNamePlaceholderResolvedToCustomerName(): void
    {
        $eff = ['links_policy' => 'allow', 'signature' => false];
        $out = Postprocessor::apply('Hallo {{name}}, vielen Dank.', $eff, '', null, 'Sahra');
        $this->assertSame('Hallo Sahra, vielen Dank.', $out);
    }

    public function testEmptyNameRemovesPlaceholderAndTidies(): void
    {
        $out = Postprocessor::resolvePlaceholders('Guten Tag {{name}}, alles gut.', '', false, false);
        $this->assertSame('Guten Tag, alles gut.', $out);
    }

    public function testTrackingPlaceholderBecomesSafePhraseWhenLinksBlocked(): void
    {
        $out = Postprocessor::resolvePlaceholders('Ihre Sendung ist {{tracking_url}}.', '', true, false);
        $this->assertStringContainsString('in Ihrem Kundenkonto einsehbar', $out);
        $this->assertStringNotContainsString('{{', $out);
        $this->assertStringNotContainsString('http', $out);
        // "du" variant
        $du = Postprocessor::resolvePlaceholders('Deine Sendung ist {{tracking_url}}.', '', true, true);
        $this->assertStringContainsString('in deinem Kundenkonto einsehbar', $du);
    }

    public function testTrackingPlaceholderBecomesBracketWhenLinksAllowed(): void
    {
        $out = Postprocessor::resolvePlaceholders('Sendungsstatus: {{tracking_url}}', '', false, false);
        $this->assertStringContainsString('[Sendungslink hier einfuegen]', $out);
        $this->assertStringNotContainsString('{{', $out);
    }

    public function testStrayPlaceholderBecomesBracket(): void
    {
        $out = Postprocessor::resolvePlaceholders('Bezug: {{bestellnummer}} — danke.', '', false, false);
        $this->assertStringContainsString('[bestellnummer]', $out);
        $this->assertStringNotContainsString('{{', $out);
    }
}
