<?php

namespace Modules\AiAssist\Services;

/**
 * Deterministic, model-never-trusted post-processing. Pure.
 *
 * Honest scope: normal links + bare emails only. Phone numbers, obfuscated
 * forms ("hxxp", "example dot com") and leftover fragments remain the human
 * reviewer's job — see README. This is NOT a full eBay contact-compliance
 * guarantee.
 */
class Postprocessor
{
    public static function apply(string $draft, array $effective, string $signatureText, ?string $quotedOriginal, string $customerName = ''): string
    {
        $blocked = (($effective['links_policy'] ?? 'allow') === 'block');
        $du      = (($effective['salutation'] ?? 'sie') === 'du');

        // Resolve the placeholders the LLM was told to use ({{name}} /
        // {{tracking_url}}) BEFORE stripping. The LLM never gets the real name or
        // tracking data — the module fills them here from local data. This is the
        // step that was missing (drafts showed raw {{...}} tokens).
        $out = self::resolvePlaceholders($draft, $customerName, $blocked, $du);

        if ($blocked) {
            $out = self::stripUrls($out);
            $out = self::stripContact($out);
        }

        if (!empty($effective['signature']) && trim($signatureText) !== '') {
            $out = rtrim($out) . "\n\n" . trim($signatureText);
        }

        // quote_original: the reply builder assembles the quoted block OUTSIDE
        // the stripped draft (exact quote is intentionally NOT stripped).
        if (!empty($effective['quote_original']) && $quotedOriginal !== null && trim($quotedOriginal) !== '') {
            $out = rtrim($out) . "\n\n" . $quotedOriginal;
        }

        return $out;
    }

    /**
     * Fill the LLM placeholders with local (non-LLM) data. The model is told to
     * write {{name}} / {{tracking_url}} so real PII + tracking data never reach
     * it; the deterministic module resolves them here.
     *
     *  - {{name}}: the real customer first name (empty -> token removed).
     *  - {{tracking_url}}: on link-blocked channels (Amazon/eBay) NEVER a link —
     *    a safe phrase; on link-allowed channels the module has no URL, so a
     *    clearly-marked bracket the human fills.
     *  - any other {{X}}: turned into [X] so it never looks like code.
     */
    public static function resolvePlaceholders(string $text, string $customerName, bool $linksBlocked, bool $du): string
    {
        $original = $text;
        $name = trim($customerName);
        $text = preg_replace('/\{\{\s*(?:name|kunde|customer_name|customername|kundenname)\s*\}\}/iu', $name, $text);

        $tracking = $linksBlocked
            ? ($du ? 'in deinem Kundenkonto einsehbar' : 'in Ihrem Kundenkonto einsehbar')
            : '[Sendungslink hier einfuegen]';
        $text = preg_replace('/\{\{\s*(?:tracking_url|tracking_number|trackingnummer|tracking|sendungsnummer|sendungslink|trackinglink)\s*\}\}/iu', $tracking, $text);

        // Any remaining {{...}} -> [...] (human-obvious, never cryptic braces).
        $text = preg_replace('/\{\{\s*([^{}]+?)\s*\}\}/u', '[$1]', $text);

        // Only tidy whitespace when a placeholder was actually substituted — keeps
        // allow-mode byte-identical for drafts that contain no placeholders.
        return $text === $original ? $text : self::collapseSpaces($text);
    }

    /**
     * Order matters:
     *  1. markdown [text](url) -> text
     *  2. html <a ...>inner</a> -> inner
     *  3. bare https?:// / www. -> removed; collapse double spaces
     */
    public static function stripUrls(string $text): string
    {
        $text = preg_replace('/\[([^\]]+)\]\((?:[^)]+)\)/', '$1', $text);
        $text = preg_replace('/<a\b[^>]*>(.*?)<\/a>/is', '$1', $text);
        $text = preg_replace('/\b(?:https?:\/\/|www\.)\S+/i', '', $text);
        return self::collapseSpaces($text);
    }

    /**
     * At links_policy=block, also remove bare emails + mailto: (most common
     * off-platform contact leak). mailto: removed before bare-email so the
     * scheme prefix is not left dangling.
     */
    public static function stripContact(string $text): string
    {
        $text = preg_replace('/mailto:\S+/i', '', $text);
        $text = preg_replace('/\S+@\S+\.\S+/', '', $text);
        return self::collapseSpaces($text);
    }

    private static function collapseSpaces(string $text): string
    {
        $text = preg_replace('/[ \t]{2,}/', ' ', $text);
        $text = preg_replace('/ +([.,;:!?])/', '$1', $text); // tidy space before punctuation
        return $text;
    }
}
