<form class="form-horizontal margin-top margin-bottom" method="POST" action="">
    {{ csrf_field() }}

    <h4 class="margin-bottom">{{ __('KI-Antworten (S1)') }}</h4>

    <div class="form-group">
        <label class="col-sm-3 control-label"></label>
        <div class="col-sm-8">
            <div class="controls">
                <div class="onoffswitch-wrap" style="display:inline-block;vertical-align:middle;">
                    <div class="onoffswitch">
                        <input type="checkbox" class="onoffswitch-checkbox" id="aiassist-enabled" name="settings[aiassist.enabled]" value="1" @if ($settings['aiassist.enabled'] ?? true) checked @endif>
                        <label class="onoffswitch-label" for="aiassist-enabled"></label>
                    </div>
                </div>
                <span style="margin-left:10px;vertical-align:middle;">{{ __('Button „Antwort vorschlagen" anzeigen') }}</span>
            </div>
        </div>
    </div>

    <div class="form-group">
        <label class="col-sm-3 control-label">{{ __('DPA / ZDR bestätigt') }}</label>
        <div class="col-sm-8">
            <div class="checkbox">
                <label>
                    <input type="checkbox" name="settings[aiassist.dpa_acknowledged]" value="1" @if ($settings['aiassist.dpa_acknowledged'] ?? false) checked @endif>
                    {{ __('Ich habe einen AV-Vertrag (DPA) mit dem KI-Anbieter geschlossen und Zero-Data-Retention aktiviert.') }}
                </label>
            </div>
            <p class="form-help">{{ __('Muss bestätigt sein, bevor überhaupt ein LLM aufgerufen wird.') }}</p>
        </div>
    </div>

    <hr>
    <h4 class="margin-bottom">{{ __('Standalone-Anbieter (BYOK)') }}</h4>

    <div class="form-group">
        <label class="col-sm-3 control-label">{{ __('Anbieter') }}</label>
        <div class="col-sm-5">
            <select class="form-control input-sized" name="settings[aiassist.provider]">
                <option value="claude" @if (($settings['aiassist.provider'] ?? 'claude') == 'claude') selected @endif>Anthropic (Claude)</option>
                <option value="openai" @if (($settings['aiassist.provider'] ?? 'claude') == 'openai') selected @endif>OpenAI</option>
            </select>
        </div>
    </div>

    <div class="form-group">
        <label class="col-sm-3 control-label">{{ __('Modell') }}</label>
        <div class="col-sm-5">
            <input type="text" class="form-control input-sized-lg" name="settings[aiassist.model]" value="{{ $settings['aiassist.model'] ?? 'claude-haiku-4-5' }}" placeholder="claude-haiku-4-5">
            <p class="form-help">{{ __('Editierbar. Claude-Standard: claude-haiku-4-5 (Sonnet/Opus möglich). OpenAI: gültige Modell-ID eintragen.') }}</p>
        </div>
    </div>

    <div class="form-group">
        <label class="col-sm-3 control-label">{{ __('API-Key') }}</label>
        <div class="col-sm-5">
            <input type="password" class="form-control input-sized-lg" name="settings[aiassist.api_key]" value="{{ ($settings['aiassist.api_key'] ?? '') ? '********' : '' }}" placeholder="{{ __('API-Key eingeben') }}" autocomplete="new-password">
            <p class="form-help">
                {{ __('Leer lassen, um den gespeicherten Key zu behalten. Hinweis: der Key wird in der FreeScout-DB maskiert gespeichert, NICHT verschlüsselt — DB-Verschlüsselung ist Installer-Pflicht.') }}
                <a href="#" id="aiassist-test-llm">{{ __('Verbindung testen') }}</a> <span id="aiassist-test-llm-result"></span>
            </p>
        </div>
    </div>

    <hr>
    <h4 class="margin-bottom">{{ __('Leitplanken & Ton') }}</h4>

    <div class="form-group">
        <label class="col-sm-3 control-label">{{ __('Anrede') }}</label>
        <div class="col-sm-5">
            <select class="form-control input-sized" name="settings[aiassist.anrede]">
                <option value="sie" @if (($settings['aiassist.anrede'] ?? 'sie') == 'sie') selected @endif>{{ __('Sie') }}</option>
                <option value="du" @if (($settings['aiassist.anrede'] ?? 'sie') == 'du') selected @endif>{{ __('Du') }}</option>
            </select>
        </div>
    </div>

    <div class="form-group">
        <label class="col-sm-3 control-label">{{ __('Länge') }}</label>
        <div class="col-sm-5">
            <select class="form-control input-sized" name="settings[aiassist.max_length]">
                <option value="short" @if (($settings['aiassist.max_length'] ?? 'medium') == 'short') selected @endif>{{ __('Kurz') }}</option>
                <option value="medium" @if (($settings['aiassist.max_length'] ?? 'medium') == 'medium') selected @endif>{{ __('Mittel') }}</option>
                <option value="long" @if (($settings['aiassist.max_length'] ?? 'medium') == 'long') selected @endif>{{ __('Lang') }}</option>
            </select>
            <p class="form-help">{{ __('Weiche Vorgabe (nur Prompt) in v0.') }}</p>
        </div>
    </div>

    <div class="form-group">
        <label class="col-sm-3 control-label">{{ __('Links') }}</label>
        <div class="col-sm-5">
            <select class="form-control input-sized" name="settings[aiassist.links_mode]">
                <option value="allow" @if (($settings['aiassist.links_mode'] ?? 'allow') == 'allow') selected @endif>{{ __('Erlauben') }}</option>
                <option value="block" @if (($settings['aiassist.links_mode'] ?? 'allow') == 'block') selected @endif>{{ __('Blockieren (Links + Kontaktdaten entfernen)') }}</option>
            </select>
            <p class="form-help">{{ __('eBay/Amazon erzwingen „Blockieren" unabhängig von dieser Einstellung.') }}</p>
        </div>
    </div>

    <div class="form-group">
        <label class="col-sm-3 control-label">{{ __('System-Instruktionen') }}</label>
        <div class="col-sm-6">
            <textarea class="form-control" name="settings[aiassist.system_instructions]" rows="4">{{ $settings['aiassist.system_instructions'] ?? '' }}</textarea>
        </div>
    </div>

    <div class="form-group">
        <label class="col-sm-3 control-label"></label>
        <div class="col-sm-8">
            <div class="checkbox">
                <label>
                    <input type="checkbox" name="settings[aiassist.signature_on]" value="1" @if ($settings['aiassist.signature_on'] ?? true) checked @endif>
                    {{ __('Signatur anhängen') }}
                </label>
            </div>
        </div>
    </div>

    <div class="form-group">
        <label class="col-sm-3 control-label">{{ __('Signatur-Text') }}</label>
        <div class="col-sm-6">
            <textarea class="form-control" name="settings[aiassist.signature_text]" rows="3">{{ $settings['aiassist.signature_text'] ?? '' }}</textarea>
        </div>
    </div>

    <div class="form-group">
        <label class="col-sm-3 control-label">{{ __('Wissensbasis (KB)') }}</label>
        <div class="col-sm-6">
            <textarea class="form-control" name="settings[aiassist.kb_text]" rows="5" maxlength="6000">{{ $settings['aiassist.kb_text'] ?? '' }}</textarea>
            <p class="form-help">{{ __('Standalone-Kontext (max. 6000 Zeichen), wird jedem Entwurf beigelegt.') }}</p>
        </div>
    </div>

    <div class="form-group">
        <label class="col-sm-3 control-label">{{ __('Kanal-Overrides (JSON)') }}</label>
        <div class="col-sm-6">
            <textarea class="form-control" name="settings[aiassist.channel_overrides]" rows="6">{{ $settings['aiassist.channel_overrides'] ?? '' }}</textarea>
            <p class="form-help">{{ __('Sparse pro-Kanal-Regeln. Voreingestellt: eBay/Amazon = links_mode: block.') }}</p>
        </div>
    </div>

    <div class="form-group">
        <label class="col-sm-3 control-label">{{ __('Postfächer') }}</label>
        <div class="col-sm-6">
            @foreach ($mailbox_options as $id => $label)
                <div class="checkbox">
                    <label>
                        <input type="checkbox" name="settings[aiassist.mailbox_ids][]" value="{{ $id }}" @if (in_array($id, $settings['aiassist.mailbox_ids'] ?? [])) checked @endif>
                        {{ $label }}
                    </label>
                </div>
            @endforeach
            <p class="form-help">{{ __('Nichts auswählen = alle Postfächer.') }}</p>
        </div>
    </div>

    <hr>
    <h4 class="margin-bottom">{{ __('Flowkom-Datenzugriff (S2, optional)') }}</h4>

    <div class="form-group">
        <label class="col-sm-3 control-label"></label>
        <div class="col-sm-8">
            <div class="checkbox">
                <label>
                    <input type="checkbox" name="settings[aiassist.flowkom_enabled]" value="1" @if ($settings['aiassist.flowkom_enabled'] ?? false) checked @endif>
                    {{ __('Über Flowkom erden (Connected-Modus)') }}
                </label>
            </div>
            <p class="form-help">{{ __('Wenn aktiv und Flowkom die Fähigkeit meldet, entwirft Flowkom (mit eigenem Key). Sonst läuft alles standalone.') }}</p>
        </div>
    </div>

    <div class="form-group">
        <label class="col-sm-3 control-label">{{ __('Flowkom-URL') }}</label>
        <div class="col-sm-5">
            <input type="url" class="form-control input-sized-lg" name="settings[aiassist.flowkom_url]" value="{{ $settings['aiassist.flowkom_url'] ?? '' }}" placeholder="https://app.flowkom.de">
        </div>
    </div>

    <div class="form-group">
        <label class="col-sm-3 control-label">{{ __('Flowkom-API-Key') }}</label>
        <div class="col-sm-5">
            <input type="password" class="form-control input-sized-lg" name="settings[aiassist.flowkom_api_key]" value="{{ ($settings['aiassist.flowkom_api_key'] ?? '') ? '********' : '' }}" placeholder="{{ __('Integration-Key') }}" autocomplete="new-password">
            <p class="form-help">
                {{ __('Leer lassen, um den gespeicherten Key zu behalten.') }}
                @if ($settings['aiassist.flowkom_enabled'] ?? false)
                    <a href="#" id="aiassist-test-cap">{{ __('Datenzugriff testen') }}</a> <span id="aiassist-test-cap-result"></span>
                @endif
            </p>
        </div>
    </div>

    <hr>
    <h4 class="margin-bottom">{{ __('Welche Daten werden geteilt?') }}</h4>
    <table class="table table-bordered" style="max-width:760px;">
        <thead><tr><th>{{ __('Modus') }}</th><th>{{ __('Übermittelt an das LLM') }}</th><th>{{ __('NICHT übermittelt') }}</th></tr></thead>
        <tbody>
            <tr>
                <td>{{ __('Standalone') }}</td>
                <td>{{ __('bereinigter Ticket-Text, eingefügte KB, Instruktionen') }}</td>
                <td rowspan="2">{{ __('Adresse, Telefon, E-Mail, Zahlungsdaten, SKU, Sendungsnummer, interne Notizen') }}</td>
            </tr>
            <tr>
                <td>{{ __('Connected') }}</td>
                <td>{{ __('zusätzlich Auftrags-/Versandstatus, Produkttitel + Menge, Kanal, Anrede') }}</td>
            </tr>
        </tbody>
    </table>
    <p class="form-help">{{ __('Interne Notizen erreichen das LLM in keinem Modus. Der deterministische Filter deckt normale Links + blanke E-Mails ab; Telefonnummern und obfuskierte Formen bleiben Aufgabe des menschlichen Prüfers.') }}</p>

    <div class="form-group margin-top">
        <div class="col-sm-6 col-sm-offset-3">
            <button type="submit" class="btn btn-primary">{{ __('Speichern') }}</button>
        </div>
    </div>
</form>

<script {!! \Helper::cspNonceAttr() !!}>
(function() {
    function wire(btnId, outId, route) {
        var btn = document.getElementById(btnId);
        var out = document.getElementById(outId);
        if (!btn) return;
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            out.textContent = '…';
            fetch(route, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': document.querySelector('input[name=_token]').value, 'Accept': 'application/json' }
            })
            .then(function(r) { return r.json(); })
            .then(function(d) { out.textContent = (d.success ? '✅ ' : '❌ ') + d.message; })
            .catch(function() { out.textContent = '❌ Test fehlgeschlagen'; });
        });
    }
    wire('aiassist-test-llm', 'aiassist-test-llm-result', '{{ route('aiassist.test_llm') }}');
    wire('aiassist-test-cap', 'aiassist-test-cap-result', '{{ route('aiassist.test_cap') }}');
})();
</script>
