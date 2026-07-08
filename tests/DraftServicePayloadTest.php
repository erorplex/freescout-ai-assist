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
}
