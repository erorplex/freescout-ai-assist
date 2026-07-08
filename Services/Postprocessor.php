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
    public static function apply(string $draft, array $effective, string $signatureText, ?string $quotedOriginal): string
    {
        $out = $draft;

        if (($effective['links_policy'] ?? 'allow') === 'block') {
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
