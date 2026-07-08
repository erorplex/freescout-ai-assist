<?php

namespace Modules\AiAssist\Tests;

use Modules\AiAssist\Services\DraftService;
use PHPUnit\Framework\TestCase;

class DraftServicePayloadTest extends TestCase
{
    public function testNoteRowsAreDroppedEntirely(): void
    {
        $rows = [
            ['author_type' => 'customer', 'date' => 'd1', 'text' => 'Wo ist meine Bestellung?'],
            ['author_type' => 'note',     'date' => 'd2', 'text' => 'GEHEIME-KULANZ-NOTIZ-42'],
            ['author_type' => 'agent',    'date' => 'd3', 'text' => 'Wir pruefen das.'],
        ];
        $out = DraftService::messagesFromRows($rows);

        $this->assertCount(2, $out);
        $joined = json_encode($out, JSON_UNESCAPED_UNICODE);
        $this->assertStringNotContainsString('GEHEIME-KULANZ-NOTIZ-42', $joined);
        $this->assertSame('customer', $out[0]['author_type']);
        $this->assertSame('agent', $out[1]['author_type']);
    }

    public function testKeepsLastTenChronological(): void
    {
        $rows = [];
        for ($i = 0; $i < 13; $i++) {
            $rows[] = ['author_type' => 'customer', 'date' => "d$i", 'text' => "m$i"];
        }
        $out = DraftService::messagesFromRows($rows);
        $this->assertCount(10, $out);
        $this->assertSame('m3', $out[0]['text']);
        $this->assertSame('m12', $out[9]['text']);
    }

    public function testEmptyTextRowsSkipped(): void
    {
        $rows = [
            ['author_type' => 'customer', 'date' => 'd', 'text' => '   '],
            ['author_type' => 'customer', 'date' => 'd', 'text' => 'hallo'],
        ];
        $this->assertCount(1, DraftService::messagesFromRows($rows));
    }

    public function testExtractsAmazonOrderNumberFromSubject(): void
    {
        $subject = 'Rueckfrage zur Lieferung (Bestellung: 028-4937770-4832355)';
        $this->assertSame('028-4937770-4832355', DraftService::extractOrderNumber($subject, []));
    }

    public function testExtractsAmazonOrderNumberFromMessageBody(): void
    {
        $messages = [
            ['author_type' => 'customer', 'date' => 'd', 'text' => 'Hi! Bestellnr.: 028-4937770-4832355 - ASIN: B0H2N6ZSY7'],
        ];
        $this->assertSame('028-4937770-4832355', DraftService::extractOrderNumber('Frage', $messages));
    }

    public function testExtractsLabelledOrderNumberWhenNoAmazonPattern(): void
    {
        $messages = [
            ['author_type' => 'customer', 'date' => 'd', 'text' => 'Meine Bestellnr.: SHOP-10042 ist nicht angekommen.'],
        ];
        $this->assertSame('SHOP-10042', DraftService::extractOrderNumber('Frage', $messages));
    }

    public function testReturnsEmptyWhenNoOrderNumberPresent(): void
    {
        $messages = [
            ['author_type' => 'customer', 'date' => 'd', 'text' => 'Wo bleibt mein Paket? Danke!'],
        ];
        $this->assertSame('', DraftService::extractOrderNumber('Lieferung', $messages));
    }

    public function testExtractsEbayUsername(): void
    {
        $messages = [
            ['author_type' => 'customer', 'date' => 'd', 'text' => "Buyer's eBay Username: cool_buyer-99"],
        ];
        $this->assertSame('cool_buyer-99', DraftService::extractEbayUsername('Frage', $messages));
    }

    public function testReturnsEmptyWhenNoEbayUsername(): void
    {
        $this->assertSame('', DraftService::extractEbayUsername('Frage', [
            ['author_type' => 'customer', 'date' => 'd', 'text' => 'Hallo, wo ist meine Bestellung?'],
        ]));
    }

    public function testWithPerDraftInstructionAppendsToGlobalInstructions(): void
    {
        $g = ['version' => 1, 'global' => ['instructions' => 'Basis-Regel.'], 'channels' => []];
        $out = DraftService::withPerDraftInstruction($g, 'Biete Ersatz an.');
        $this->assertStringContainsString('Basis-Regel.', $out['global']['instructions']);
        $this->assertStringContainsString('Zusätzliche Anweisung nur für diese Antwort: Biete Ersatz an.', $out['global']['instructions']);
    }

    public function testWithPerDraftInstructionEmptyIsNoOp(): void
    {
        $g = ['version' => 1, 'global' => ['instructions' => 'X'], 'channels' => []];
        $this->assertSame($g, DraftService::withPerDraftInstruction($g, '   '));
    }

    public function testWithPerDraftInstructionSetsWhenNoBase(): void
    {
        $g = ['version' => 1, 'global' => ['instructions' => ''], 'channels' => []];
        $out = DraftService::withPerDraftInstruction($g, 'Kurz halten.');
        $this->assertSame('Zusätzliche Anweisung nur für diese Antwort: Kurz halten.', $out['global']['instructions']);
    }
}
