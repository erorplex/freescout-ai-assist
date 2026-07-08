<?php

namespace Modules\AiAssist\Services;

use App\Mailbox;

/**
 * aiassist.* options + FreeScout settings page (Einstellungen -> KI-Antworten).
 * Mirrors the Suite's Settings conventions: default=true trap workaround for
 * bool toggles, safe_password for secrets, mailbox scope.
 */
class Settings
{
    const DEFAULT_MODEL = 'claude-haiku-4-5';

    const CHANNEL_SEED = [
        'ebay'   => ['links_mode' => 'block', 'quote_original' => true],
        'amazon' => ['links_mode' => 'block'],
    ];

    public static function featureOn(string $key): bool
    {
        return (bool) \Option::get('aiassist.' . $key, true);
    }

    public static function dpaAcknowledged(): bool
    {
        return (bool) \Option::get('aiassist.dpa_acknowledged', false);
    }

    public static function provider(): string
    {
        $p = (string) \Option::get('aiassist.provider', 'claude');
        return in_array($p, ['claude', 'openai'], true) ? $p : 'claude';
    }

    public static function model(): string
    {
        $m = trim((string) \Option::get('aiassist.model', self::DEFAULT_MODEL));
        return $m !== '' ? $m : self::DEFAULT_MODEL;
    }

    public static function apiKey(): string
    {
        return (string) \Option::get('aiassist.api_key', '');
    }

    public static function signatureOn(): bool
    {
        return (bool) \Option::get('aiassist.signature_on', true);
    }

    public static function signatureText(): string
    {
        return (string) \Option::get('aiassist.signature_text', '');
    }

    public static function kbText(): string
    {
        return mb_substr((string) \Option::get('aiassist.kb_text', ''), 0, 6000);
    }

    public static function channelOverrides(): array
    {
        $raw = \Option::get('aiassist.channel_overrides', null);
        if ($raw === null) {
            return self::CHANNEL_SEED;
        }
        if (is_array($raw)) {
            return $raw;
        }
        $decoded = json_decode((string) $raw, true);
        return is_array($decoded) ? $decoded : self::CHANNEL_SEED;
    }

    /**
     * Normalized array consumed by PromptBuilder::compileGuidance().
     */
    public static function guidanceSettings(): array
    {
        $maxLength = (string) \Option::get('aiassist.max_length', 'medium');
        return [
            'instructions'      => (string) \Option::get('aiassist.system_instructions', ''),
            'salutation'        => (string) \Option::get('aiassist.anrede', 'sie'),
            'max_length'        => in_array($maxLength, ['short', 'medium', 'long'], true) ? $maxLength : null,
            'signature'         => self::signatureOn(),
            'links_mode'        => (string) \Option::get('aiassist.links_mode', 'allow'),
            'language'          => 'de',
            'channel_overrides' => self::channelOverrides(),
        ];
    }

    public static function mailboxIds(): array
    {
        $ids = \Option::get('aiassist.mailbox_ids', '[]');
        if (is_array($ids)) {
            return array_map('intval', $ids);
        }
        $decoded = json_decode((string) $ids, true);
        return is_array($decoded) ? array_map('intval', $decoded) : [];
    }

    public static function flowkomEnabled(): bool
    {
        // default FALSE, NOT registered as default=true.
        return (bool) \Option::get('aiassist.flowkom_enabled', false);
    }

    public static function flowkomUrl(): string
    {
        return rtrim((string) \Option::get('aiassist.flowkom_url', ''), '/');
    }

    public static function flowkomApiKey(): string
    {
        return (string) \Option::get('aiassist.flowkom_api_key', '');
    }

    // =========================================================
    // Settings page (FreeScout standard hooks)
    // =========================================================

    public static function register(): void
    {
        \Eventy::addFilter('settings.sections', function ($sections) {
            $sections['aiassist'] = [
                'title' => 'KI-Antworten',
                'icon'  => 'flash',
                'order' => 360,
            ];
            return $sections;
        }, 35);

        \Eventy::addFilter('settings.section_settings', function ($settings, $section) {
            if ($section != 'aiassist') {
                return $settings;
            }
            $settings['aiassist.enabled']             = self::featureOn('enabled');
            $settings['aiassist.dpa_acknowledged']    = self::dpaAcknowledged();
            $settings['aiassist.provider']            = self::provider();
            $settings['aiassist.model']               = self::model();
            $settings['aiassist.api_key']             = self::apiKey();
            $settings['aiassist.system_instructions'] = (string) \Option::get('aiassist.system_instructions', '');
            $settings['aiassist.anrede']              = (string) \Option::get('aiassist.anrede', 'sie');
            $settings['aiassist.max_length']          = (string) \Option::get('aiassist.max_length', 'medium');
            $settings['aiassist.signature_on']        = self::signatureOn();
            $settings['aiassist.signature_text']      = self::signatureText();
            $settings['aiassist.links_mode']          = (string) \Option::get('aiassist.links_mode', 'allow');
            $settings['aiassist.kb_text']             = self::kbText();
            $settings['aiassist.channel_overrides']   = json_encode(self::channelOverrides(), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            $settings['aiassist.mailbox_ids']         = self::mailboxIds();
            $settings['aiassist.flowkom_enabled']     = self::flowkomEnabled();
            $settings['aiassist.flowkom_url']         = self::flowkomUrl();
            $settings['aiassist.flowkom_api_key']     = self::flowkomApiKey();
            return $settings;
        }, 20, 2);

        \Eventy::addFilter('settings.section_params', function ($params, $section) {
            if ($section != 'aiassist') {
                return $params;
            }
            $mailbox_options = [];
            foreach (Mailbox::get() as $mailbox) {
                $mailbox_options[$mailbox->id] = $mailbox->name . ' (' . $mailbox->email . ')';
            }
            $params['template_vars'] = ['mailbox_options' => $mailbox_options];

            $settingsParams = [];
            // default=true trap ONLY for bool toggles whose code-default is true.
            // enabled -> default true (effective off via routing/DPA gate).
            // signature_on -> default true.
            $settingsParams['aiassist.enabled']      = ['default' => true];
            $settingsParams['aiassist.signature_on'] = ['default' => true];
            // dpa_acknowledged + flowkom_enabled default FALSE -> NO default=true
            // (unchecked must stay unchecked; code-default false is correct).
            // Secrets: safe_password (masked + keeps stored value on empty submit;
            // does NOT encrypt at rest).
            $settingsParams['aiassist.api_key']         = ['safe_password' => true];
            $settingsParams['aiassist.flowkom_api_key'] = ['safe_password' => true];

            $params['settings'] = $settingsParams;
            return $params;
        }, 20, 2);

        \Eventy::addFilter('settings.view', function ($view, $section) {
            return $section == 'aiassist' ? 'aiassist::settings' : $view;
        }, 20, 2);

        \Eventy::addFilter('settings.before_save', function ($request, $section, $settings) {
            if ($section != 'aiassist') {
                return $request;
            }
            // Empty secret field means "keep stored value": mirror the stored
            // value back so the core save-loop rewrites it unchanged instead of
            // clearing it. ($request->input() cannot dot-address 'aiassist.*'.)
            $input = $request->input('settings', []);
            if (is_array($input)) {
                foreach (['aiassist.api_key' => self::apiKey(), 'aiassist.flowkom_api_key' => self::flowkomApiKey()] as $key => $stored) {
                    $val = trim((string) ($input[$key] ?? ''));
                    if ($val === '' || preg_match('/^\*+$/', $val)) {
                        $input[$key] = $stored;
                    }
                }
                $request->merge(['settings' => $input]);
            }
            return $request;
        }, 20, 3);
    }
}
