<?php

namespace Modules\AiAssist\Tests;

use Modules\AiAssist\Services\PromptBuilder;
use PHPUnit\Framework\TestCase;

class PromptBuilderTest extends TestCase
{
    private function settings(): array
    {
        return [
            'instructions'      => 'Sei freundlich.',
            'salutation'        => 'sie',
            'max_length'        => 'medium',
            'signature'         => true,
            'links_mode'        => 'allow',
            'language'          => 'de',
            'channel_overrides' => [
                'ebay'   => ['links_mode' => 'block', 'quote_original' => true, 'instructions' => 'Keine externen Links oder Kontaktdaten.'],
                'amazon' => ['links_mode' => 'block'],
            ],
        ];
    }

    public function testCompileProducesCanonicalShape(): void
    {
        $g = PromptBuilder::compileGuidance($this->settings());
        $this->assertSame(1, $g['version']);
        $this->assertSame('Sei freundlich.', $g['global']['instructions']);
        $this->assertSame('sie', $g['global']['salutation']);
        $this->assertSame('medium', $g['global']['max_length']);
        $this->assertTrue($g['global']['signature']);
        $this->assertSame('allow', $g['global']['links_policy']);
        $this->assertSame('de', $g['global']['language']);
        // channel links_mode is translated to links_policy; sparse keys only
        $this->assertSame('block', $g['channels']['ebay']['links_policy']);
        $this->assertTrue($g['channels']['ebay']['quote_original']);
        $this->assertSame('block', $g['channels']['amazon']['links_policy']);
        $this->assertArrayNotHasKey('quote_original', $g['channels']['amazon']);
    }

    public function testResolveEbayForcesBlockAndAppendsInstructions(): void
    {
        $g = PromptBuilder::compileGuidance($this->settings()); // global links_policy=allow
        $eff = PromptBuilder::resolve($g, 'ebay');
        $this->assertSame('block', $eff['links_policy']); // channel tightens
        $this->assertTrue($eff['quote_original']);
        $this->assertSame("Sei freundlich.\nKeine externen Links oder Kontaktdaten.", $eff['instructions']);
    }

    public function testResolveUnknownChannelIsGlobalOnly(): void
    {
        $g = PromptBuilder::compileGuidance($this->settings());
        $eff = PromptBuilder::resolve($g, 'email');
        $this->assertSame('allow', $eff['links_policy']);
        $this->assertSame('Sei freundlich.', $eff['instructions']);
        $this->assertArrayNotHasKey('quote_original', $eff);
    }

    public function testGlobalBlockCannotBeLoosenedByChannel(): void
    {
        $s = $this->settings();
        $s['links_mode'] = 'block';
        // even if a (mis)configured channel says allow, block wins
        $s['channel_overrides']['ebay']['links_mode'] = 'allow';
        $g = PromptBuilder::compileGuidance($s);
        $eff = PromptBuilder::resolve($g, 'ebay');
        $this->assertSame('block', $eff['links_policy']);
    }

    public function testBuildMapsThreadsAndSizesLastTen(): void
    {
        $g   = PromptBuilder::compileGuidance($this->settings());
        $eff = PromptBuilder::resolve($g, 'email');
        $messages = [];
        for ($i = 0; $i < 14; $i++) {
            $messages[] = ['author_type' => $i % 2 === 0 ? 'customer' : 'agent', 'date' => '2026-07-08T09:00:00Z', 'text' => "m$i"];
        }
        $ticket = ['subject' => 'Wo ist meine Bestellung?', 'channel' => 'email', 'messages' => $messages];
        $out = PromptBuilder::build($ticket, $eff, ['text' => 'Retouren binnen 30 Tagen.']);

        $this->assertNotNull($out);
        $this->assertCount(10, $out['messages']);          // last 10 only
        $this->assertSame('m4', $out['messages'][0]['content']);
        $this->assertSame('user', $out['messages'][0]['role']);        // customer -> user
        $this->assertSame('assistant', $out['messages'][1]['role']);   // agent -> assistant
        $this->assertStringContainsString('Wo ist meine Bestellung?', $out['system']);
        $this->assertStringContainsString('Retouren binnen 30 Tagen.', $out['system']);
        $this->assertStringContainsString('Sei freundlich.', $out['system']);
    }

    public function testBuildReturnsNullWhenNoMessages(): void
    {
        $g   = PromptBuilder::compileGuidance($this->settings());
        $eff = PromptBuilder::resolve($g, 'email');
        $this->assertNull(PromptBuilder::build(['subject' => 'x', 'channel' => 'email', 'messages' => []], $eff, null));
    }

    public function testBuildBlockPolicyMentionsNoLinksInstruction(): void
    {
        $g   = PromptBuilder::compileGuidance($this->settings());
        $eff = PromptBuilder::resolve($g, 'ebay'); // block
        $out = PromptBuilder::build(['subject' => 's', 'channel' => 'ebay', 'messages' => [['author_type' => 'customer', 'date' => 'd', 'text' => 'hallo']]], $eff, null);
        $this->assertStringContainsString('keine Links', $out['system']);
    }
}
