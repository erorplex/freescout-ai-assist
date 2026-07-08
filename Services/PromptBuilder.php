<?php

namespace Modules\AiAssist\Services;

/**
 * Pure guidance compiler/resolver + channel detection + prompt assembly.
 * NO FreeScout dependencies — unit-tested in this repo's CI.
 */
class PromptBuilder
{
    const GUIDANCE_VERSION = 1;

    // Canonical scalar keys allowed on a channel override (sparse).
    const CHANNEL_SCALAR_KEYS = ['links_policy', 'quote_original', 'salutation', 'max_length', 'signature', 'language'];

    /**
     * Marketplace channel from the customer's sender address.
     * Mirrors Brainflow::detectChannel.
     */
    public static function detectChannel(string $email): string
    {
        $email = strtolower($email);
        if (preg_match('/@members\.ebay\./', $email)) {
            return 'ebay';
        }
        if (preg_match('/@marketplace\.amazon\./', $email)) {
            return 'amazon';
        }
        return 'email';
    }

    /**
     * Settings -> canonical guidance object {version, global, channels}.
     * $s uses short keys (instructions, salutation, max_length, signature,
     * links_mode, language, channel_overrides). Channel overrides use
     * links_mode -> translated to links_policy here.
     */
    public static function compileGuidance(array $s): array
    {
        $global = [
            'instructions' => (string) ($s['instructions'] ?? ''),
            'salutation'   => in_array($s['salutation'] ?? 'sie', ['du', 'sie'], true) ? $s['salutation'] : 'sie',
            'max_length'   => in_array($s['max_length'] ?? null, ['short', 'medium', 'long'], true) ? $s['max_length'] : null,
            'signature'    => (bool) ($s['signature'] ?? false),
            'links_policy' => (($s['links_mode'] ?? 'allow') === 'block') ? 'block' : 'allow',
            'language'     => (string) ($s['language'] ?? 'de'),
        ];

        $channels = [];
        foreach (($s['channel_overrides'] ?? []) as $name => $ov) {
            if (!is_array($ov)) {
                continue;
            }
            $entry = [];
            if (array_key_exists('links_mode', $ov)) {
                $entry['links_policy'] = ($ov['links_mode'] === 'block') ? 'block' : 'allow';
            }
            if (array_key_exists('links_policy', $ov)) {
                $entry['links_policy'] = ($ov['links_policy'] === 'block') ? 'block' : 'allow';
            }
            if (array_key_exists('quote_original', $ov)) {
                $entry['quote_original'] = (bool) $ov['quote_original'];
            }
            if (array_key_exists('instructions', $ov) && trim((string) $ov['instructions']) !== '') {
                $entry['instructions'] = (string) $ov['instructions'];
            }
            foreach (['salutation', 'max_length', 'signature', 'language'] as $k) {
                if (array_key_exists($k, $ov)) {
                    $entry[$k] = $ov[$k];
                }
            }
            if ($entry) {
                $channels[(string) $name] = $entry;
            }
        }

        return [
            'version'  => self::GUIDANCE_VERSION,
            'global'   => $global,
            'channels' => $channels,
        ];
    }

    /**
     * Resolve effective guidance for a channel.
     * - scalars replace
     * - instructions append (channel rules are additive compliance)
     * - links_policy tightens only (block on either side => block)
     */
    public static function resolve(array $guidance, string $channel): array
    {
        $global    = $guidance['global'] ?? [];
        $effective = $global;
        $ch        = $guidance['channels'][$channel] ?? null;
        if (!is_array($ch)) {
            return $effective;
        }

        foreach ($ch as $key => $val) {
            if ($key === 'instructions') {
                $base = trim((string) ($effective['instructions'] ?? ''));
                $add  = trim((string) $val);
                if ($add === '') {
                    continue;
                }
                $effective['instructions'] = $base === '' ? $add : ($base . "\n" . $add);
            } elseif ($key === 'links_policy') {
                $globalBlock = (($global['links_policy'] ?? 'allow') === 'block');
                $effective['links_policy'] = ($globalBlock || $val === 'block') ? 'block' : 'allow';
            } else {
                $effective[$key] = $val;
            }
        }

        return $effective;
    }

    const MAX_MESSAGES = 10;
    const MAX_TEXT_LEN = 195000;
    const MAX_SUBJECT_LEN = 490;

    /**
     * Assemble {system, messages} for a provider. Pure.
     * Returns null when there is nothing to draft.
     */
    public static function build(array $ticket, array $effective, ?array $kb): ?array
    {
        $raw = $ticket['messages'] ?? [];
        $messages = [];
        foreach ($raw as $m) {
            $type = $m['author_type'] ?? '';
            if ($type !== 'customer' && $type !== 'agent') {
                continue; // notes must never reach here (also dropped upstream)
            }
            $text = trim((string) ($m['text'] ?? ''));
            if ($text === '') {
                continue;
            }
            $messages[] = [
                'role'    => $type === 'customer' ? 'user' : 'assistant',
                'content' => mb_substr($text, 0, self::MAX_TEXT_LEN),
            ];
        }
        if (!$messages) {
            return null;
        }
        if (count($messages) > self::MAX_MESSAGES) {
            $messages = array_slice($messages, -self::MAX_MESSAGES);
        }

        return [
            'system'   => self::systemPrompt($ticket, $effective, $kb),
            'messages' => $messages,
        ];
    }

    private static function systemPrompt(array $ticket, array $eff, ?array $kb): string
    {
        $lines = [];
        $lines[] = 'Du bist ein Kundenservice-Assistent und entwirfst eine Antwort auf ein Support-Ticket. '
            . 'Der Entwurf wird einem menschlichen Agenten vorgeschlagen; der Mensch prueft, bearbeitet und sendet selbst.';

        $lang = (string) ($eff['language'] ?? 'de');
        $lines[] = 'Sprache der Antwort: ' . $lang . '.';

        $salutation = ($eff['salutation'] ?? 'sie') === 'du' ? 'Duze den Kunden.' : 'Sieze den Kunden (hoefliche Anrede).';
        $lines[] = $salutation;

        $len = $eff['max_length'] ?? null;
        if ($len === 'short') {
            $lines[] = 'Halte die Antwort kurz (wenige Saetze).';
        } elseif ($len === 'medium') {
            $lines[] = 'Halte die Antwort mittellang.';
        } elseif ($len === 'long') {
            $lines[] = 'Eine ausfuehrliche Antwort ist erlaubt.';
        }

        if (($eff['links_policy'] ?? 'allow') === 'block') {
            $lines[] = 'WICHTIG: Gib keine Links und keine Kontaktdaten (E-Mail-Adressen, Telefonnummern, externe URLs) aus. '
                . 'Verweise nicht auf externe Kanaele.';
        }

        $instructions = trim((string) ($eff['instructions'] ?? ''));
        if ($instructions !== '') {
            $lines[] = 'Vorgaben des Betreibers: ' . $instructions;
        }

        $subject = mb_substr((string) ($ticket['subject'] ?? ''), 0, self::MAX_SUBJECT_LEN);
        if ($subject !== '') {
            $lines[] = 'Betreff des Tickets: ' . $subject;
        }

        $kbText = trim((string) ($kb['text'] ?? ''));
        if ($kbText !== '') {
            $lines[] = "Wissensbasis (nur als Kontext, nicht woertlich zitieren):\n" . $kbText;
        }

        return implode("\n\n", $lines);
    }
}
