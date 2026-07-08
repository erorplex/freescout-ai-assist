<?php

namespace Modules\AiAssist\Services;

use App\Thread;
use Modules\AiAssist\Services\Cache\FreescoutCache;
use Modules\AiAssist\Services\Llm\ClaudeProvider;
use Modules\AiAssist\Services\Llm\HttpJson;
use Modules\AiAssist\Services\Llm\OpenAiProvider;

class DraftService
{
    const MAX_MESSAGES = 10;
    const MAX_TEXT_LEN = 195000;

    /**
     * Turn thread rows into the seam's messages[] shape.
     * author_type:"note" is DROPPED entirely (not filtered-and-toggled):
     * internal notes must never reach the LLM. Caps text, keeps last 10.
     *
     * @param array<int,array{author_type:string,date:string,text:string}> $rows
     * @return array<int,array{author_type:string,date:string,text:string}>
     */
    public static function messagesFromRows(array $rows): array
    {
        $messages = [];
        foreach ($rows as $r) {
            $type = $r['author_type'] ?? '';
            if ($type !== 'customer' && $type !== 'agent') {
                continue; // notes and anything else are dropped
            }
            $text = trim((string) ($r['text'] ?? ''));
            if ($text === '') {
                continue;
            }
            $messages[] = [
                'author_type' => $type,
                'date'        => (string) ($r['date'] ?? ''),
                'text'        => mb_substr($text, 0, self::MAX_TEXT_LEN),
            ];
        }
        if (count($messages) > self::MAX_MESSAGES) {
            $messages = array_slice($messages, -self::MAX_MESSAGES);
        }
        return $messages;
    }

    /**
     * Best-effort order-number extraction from the ticket text. Sent to Flowkom
     * as a resolution hint in connected mode — Flowkom matches it EXACTLY against
     * real orders (order_number / external_order_id), so a wrong guess is
     * harmless (it simply resolves nothing). Without this hint Flowkom can only
     * fall back to the (often anonymized) customer email and the draft ends up
     * ungrounded. Amazon order ids (3-7-7 digits) are matched first, then a
     * labelled "Bestellnr./Bestellung/Order" token.
     *
     * @param array<int,array{author_type:string,date:string,text:string}> $messages
     */
    public static function extractOrderNumber(string $subject, array $messages): string
    {
        $text = $subject . "\n";
        foreach ($messages as $m) {
            $text .= (string) ($m['text'] ?? '') . "\n";
        }
        // 1. Amazon order id: 3-7-7 digits — very specific, no false positives.
        if (preg_match('/\b\d{3}-\d{7}-\d{7}\b/', $text, $mm)) {
            return $mm[0];
        }
        // 2. Labelled order number: "Bestellnr.:/Bestellnummer/Bestellung/Order #/..." + token.
        if (preg_match('/(?:Bestell(?:nummer|nr|ung)|Order(?:\s*(?:#|Nr\.?|No\.?|Number))?)\.?\s*[:#]?\s*([A-Za-z0-9][A-Za-z0-9\-\/]{3,})/iu', $text, $mm)) {
            return trim($mm[1], "-/");
        }
        return '';
    }

    /**
     * eBay buyer username from the imported order-note line, if present. Sent as
     * a Flowkom resolution hint for eBay tickets; empty when absent.
     *
     * @param array<int,array{author_type:string,date:string,text:string}> $messages
     */
    public static function extractEbayUsername(string $subject, array $messages): string
    {
        $text = $subject . "\n";
        foreach ($messages as $m) {
            $text .= (string) ($m['text'] ?? '') . "\n";
        }
        if (preg_match("/Buyer'?s eBay Username:\\s*([A-Za-z0-9_.\\-]+)/i", $text, $mm)) {
            return $mm[1];
        }
        return '';
    }

    // ---- FreeScout-side orchestration (not unit-tested; leans on tested pures) ----

    public static function registerButton(): void
    {
        \Eventy::addAction('conversation.after_customer_sidebar', function ($conversation) {
            try {
                $mode = self::resolveMode();
                if ($mode === 'hidden') {
                    return;
                }
                $mailboxIds = Settings::mailboxIds();
                if ($mailboxIds && !in_array((int) $conversation->mailbox_id, $mailboxIds, true)) {
                    return;
                }
                echo view('aiassist::partials.suggest-panel', [
                    'conversation' => $conversation,
                    'mode'         => $mode,
                    'url'          => url('/aiassist/draft/' . (int) $conversation->id),
                ])->render();
            } catch (\Throwable $e) {
                // fail-open: never break the ticket view
            }
        }, 35, 1);
    }

    /**
     * Fail-closed routing (module side).
     */
    public static function resolveMode(): string
    {
        if (!Settings::featureOn('enabled')) {
            return 'hidden';
        }
        if (Settings::flowkomEnabled() && self::flowkomClient()->available()) {
            return 'connected';
        }
        if (Settings::apiKey() !== '' && Settings::dpaAcknowledged()) {
            return 'standalone';
        }
        return 'disabled';
    }

    /**
     * @return array{text:string,source:string,error:?string}
     */
    public static function draft($conversation): array
    {
        $mode = self::resolveMode();
        if ($mode === 'connected') {
            return self::draftConnected($conversation);
        }
        if ($mode === 'standalone') {
            return self::draftStandalone($conversation);
        }
        return ['text' => '', 'source' => $mode, 'error' => 'Kein KI-Anbieter konfiguriert oder DPA nicht bestätigt.'];
    }

    private static function draftStandalone($conversation): array
    {
        $ticket   = self::buildTicket($conversation);
        $channel  = $ticket['channel'];
        $guidance = PromptBuilder::compileGuidance(Settings::guidanceSettings());
        $effective = PromptBuilder::resolve($guidance, $channel);
        $kb       = Settings::kbText() !== '' ? ['text' => Settings::kbText()] : null;

        $prompt = PromptBuilder::build($ticket, $effective, $kb);
        if ($prompt === null) {
            return ['text' => '', 'source' => 'standalone', 'error' => 'Ticket enthält keine Nachrichten zum Entwerfen.'];
        }

        $provider = self::provider();
        $raw = $provider->draft($prompt['messages'], $prompt['system'], []);
        if (trim($raw) === '') {
            return ['text' => '', 'source' => 'standalone', 'error' => 'Der KI-Anbieter hat keinen Entwurf geliefert.'];
        }

        $quoted = (!empty($effective['quote_original'])) ? self::buildQuotedOriginal($ticket) : null;
        $text = Postprocessor::apply($raw, $effective, Settings::signatureText(), $quoted);
        return ['text' => $text, 'source' => 'standalone', 'error' => null];
    }

    private static function draftConnected($conversation): array
    {
        $ticket    = self::buildTicket($conversation);
        $channel   = $ticket['channel'];
        $guidance  = PromptBuilder::compileGuidance(Settings::guidanceSettings());
        $effective = PromptBuilder::resolve($guidance, $channel);
        $kb        = Settings::kbText() !== '' ? ['text' => Settings::kbText()] : null;

        $payload = [
            'version'  => PromptBuilder::GUIDANCE_VERSION,
            'ticket'   => $ticket,
            'guidance' => $guidance,
            'kb'       => $kb,
        ];
        $res  = self::flowkomClient()->draft($payload);
        $raw  = (string) ($res['draft'] ?? '');

        // Belt-and-suspenders: post-process again module-side before delivery.
        $quoted = (!empty($effective['quote_original'])) ? self::buildQuotedOriginal($ticket) : null;
        $text = Postprocessor::apply($raw, $effective, Settings::signatureText(), $quoted);
        return ['text' => $text, 'source' => 'connected', 'error' => null];
    }

    /**
     * Ticket payload (seam shape). Internal notes are DROPPED here.
     */
    public static function buildTicket($conversation): array
    {
        $threadTypes = [Thread::TYPE_CUSTOMER, Thread::TYPE_MESSAGE]; // NO TYPE_NOTE
        $threads = $conversation->getThreads(null, null, $threadTypes)->sortBy('created_at')->values();

        $rows = [];
        foreach ($threads as $thread) {
            $isCustomer = (int) $thread->type === Thread::TYPE_CUSTOMER;
            $rows[] = [
                'author_type' => $isCustomer ? 'customer' : 'agent',
                'date'        => (string) $thread->created_at,
                'text'        => strip_tags((string) $thread->body),
            ];
        }
        $messages = self::messagesFromRows($rows);

        $email   = (string) ($conversation->customer_email ?? '');
        $channel = PromptBuilder::detectChannel($email);
        $subject = mb_substr((string) $conversation->subject, 0, 490);

        $ticket = [
            'subject'        => $subject,
            'channel'        => $channel,
            'customer_email' => $email,
            'messages'       => $messages,
        ];
        // Connected-mode resolution hints: only sent when found. Flowkom grounds
        // the draft (order status, tracking, items) by matching these against
        // real orders — without them the draft stays generic ("send me the
        // order number"). Standalone mode ignores these fields.
        $orderNumber = self::extractOrderNumber($subject, $messages);
        if ($orderNumber !== '') {
            $ticket['order_number'] = $orderNumber;
        }
        $ebayUsername = self::extractEbayUsername($subject, $messages);
        if ($ebayUsername !== '') {
            $ticket['ebay_username'] = $ebayUsername;
        }
        return $ticket;
    }

    private static function buildQuotedOriginal(array $ticket): ?string
    {
        // eBay compliance: the module (not the LLM) assembles the quoted block.
        $last = null;
        foreach ($ticket['messages'] as $m) {
            if (($m['author_type'] ?? '') === 'customer') {
                $last = $m;
            }
        }
        if (!$last) {
            return null;
        }
        return "----- Urspruengliche Nachricht -----\n" . (string) $last['text'];
    }

    private static function provider()
    {
        $http = new HttpJson();
        return Settings::provider() === 'openai'
            ? new OpenAiProvider(Settings::apiKey(), Settings::model(), $http)
            : new ClaudeProvider(Settings::apiKey(), Settings::model(), $http);
    }

    public static function flowkomClient(): FlowkomClient
    {
        return new FlowkomClient(
            Settings::flowkomUrl(),
            Settings::flowkomApiKey(),
            new HttpJson(),
            new FreescoutCache()
        );
    }
}
