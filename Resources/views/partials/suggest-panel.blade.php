@php $disabled = ($mode === 'disabled'); $connected = ($mode === 'connected'); @endphp
<div class="aiassist-panel" data-url="{{ htmlspecialchars($url, ENT_QUOTES) }}" style="margin-top:10px;border:1px solid #d6dce2;border-radius:4px;overflow:hidden;font-size:12px;">
    <div style="padding:7px 10px;background:#0f766e;color:#fff;font-weight:600;font-size:11px;letter-spacing:.5px;">KI-ANTWORT</div>
    <div style="padding:8px 10px;background:#fff;">
        @if($disabled)
        <button type="button" disabled title="Kein KI-Anbieter konfiguriert / DPA nicht bestätigt"
            style="display:block;width:100%;text-align:center;padding:6px 0;border:0;border-radius:3px;font-size:11px;color:#fff;font-weight:500;background:#9ca3af;cursor:not-allowed;">Kein KI-Anbieter konfiguriert</button>
        @else
        <textarea id="aiassist-instruction" rows="2" placeholder="Optional: Anweisung nur für diese Antwort (z. B. „kurz halten, Ersatz anbieten“)"
            style="width:100%;box-sizing:border-box;font-size:11px;border:1px solid #d6dce2;border-radius:3px;padding:5px;margin-bottom:6px;"></textarea>
        @if($connected)
        <div style="display:flex;gap:6px;">
            <button type="button" class="aiassist-suggest" data-grounding="on"
                style="flex:1;text-align:center;padding:6px 0;border:0;border-radius:3px;font-size:11px;color:#fff;font-weight:500;background:#0f766e;cursor:pointer;">Mit Bestelldaten</button>
            <button type="button" class="aiassist-suggest" data-grounding="off"
                style="flex:1;text-align:center;padding:6px 0;border:1px solid #d6dce2;border-radius:3px;font-size:11px;color:#334155;font-weight:500;background:#fff;cursor:pointer;">Ohne Daten</button>
        </div>
        <p style="margin:5px 0 0;color:#6b7280;font-size:10px;">„Mit Bestelldaten“ erdet den Entwurf über Flowkom (Auftrags-/Versandstatus). „Ohne Daten“ antwortet allgemein.</p>
        @else
        <button type="button" class="aiassist-suggest" data-grounding="on"
            style="display:block;width:100%;text-align:center;padding:6px 0;border:0;border-radius:3px;font-size:11px;color:#fff;font-weight:500;background:#0f766e;cursor:pointer;">Antwort vorschlagen</button>
        @endif
        @endif
        <p style="margin:6px 0 0;color:#6b7280;font-size:10px;">Entwurf für den Menschen — bitte prüfen, bearbeiten und selbst senden.</p>
        <div id="aiassist-msg" style="display:none;margin-top:6px;color:#c0392b;"></div>
        <div id="aiassist-draft-wrap" style="display:none;margin-top:8px;">
            <textarea id="aiassist-draft" readonly style="width:100%;min-height:120px;font-size:12px;border:1px solid #d6dce2;border-radius:3px;padding:6px;"></textarea>
            <p id="aiassist-hint" style="display:none;margin:5px 0 0;color:#b45309;font-size:10px;">Platzhalter in eckigen Klammern […] bitte noch ersetzen.</p>
            <div style="margin-top:6px;display:flex;gap:6px;">
                <button type="button" id="aiassist-insert" style="flex:1;padding:5px 0;border:0;border-radius:3px;font-size:11px;color:#fff;background:#0f766e;cursor:pointer;">In Antwort einfügen</button>
                <button type="button" id="aiassist-copy" style="flex:1;padding:5px 0;border:1px solid #d6dce2;border-radius:3px;font-size:11px;background:#fff;cursor:pointer;">Kopieren</button>
            </div>
        </div>
    </div>
</div>
@if(!$disabled)
<script {!! \Helper::cspNonceAttr() !!}>
(function() {
    var panel = document.querySelector('.aiassist-panel');
    var buttons = document.querySelectorAll('.aiassist-suggest');
    if (!panel || !buttons.length || panel.dataset.bound) return;
    panel.dataset.bound = '1';
    var url = panel.getAttribute('data-url');
    var msg = document.getElementById('aiassist-msg');
    var wrap = document.getElementById('aiassist-draft-wrap');
    var ta = document.getElementById('aiassist-draft');
    var hint = document.getElementById('aiassist-hint');
    var instr = document.getElementById('aiassist-instruction');

    function insertIntoReply(text) {
        var $ = window.jQuery;
        // HTML-escape the draft, keep line breaks (mirrors FreeScout's own
        // reply-insert behaviour — the summernote editor takes HTML).
        var box = document.createElement('div'); box.textContent = text;
        var html = '<div>' + box.innerHTML.replace(/\n/g, '<br>') + '</div><br>';
        // The editor is ready only when the summernote .note-editable is VISIBLE
        // (i.e. the reply form is open). FreeScout's reply toggle is .conv-reply.
        function insert() {
            if (!$ || !$('#body').length || !$('.note-editable:visible').length) return false;
            var cur = $('#body').summernote('code') || '';
            $('#body').summernote('code', html + cur);
            return true;
        }
        if (insert()) return;
        var replyBtn = document.querySelector('.conv-reply');
        if (replyBtn) replyBtn.click();
        var tries = 0;
        var iv = setInterval(function() {
            if (insert() || ++tries > 20) {
                clearInterval(iv);
                if (tries > 20) { msg.textContent = 'Antwortformular nicht gefunden — bitte manuell kopieren.'; msg.style.display = 'block'; }
            }
        }, 250);
    }

    function run(grounding, clicked) {
        msg.style.display = 'none'; wrap.style.display = 'none';
        if (hint) hint.style.display = 'none';
        buttons.forEach(function(b) { b.disabled = true; });
        var original = clicked.textContent; clicked.textContent = 'Wird erstellt…';
        var tokenEl = document.querySelector('meta[name=csrf-token]') || document.querySelector('input[name=_token]');
        var token = tokenEl ? (tokenEl.content || tokenEl.value) : '';
        var body = 'grounding=' + encodeURIComponent(grounding);
        if (instr && instr.value.trim() !== '') { body += '&instruction=' + encodeURIComponent(instr.value.trim()); }
        fetch(url, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json', 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body
        })
        .then(function(r) { return r.json().then(function(d) { return { ok: r.ok, d: d }; }); })
        .then(function(res) {
            if (res.ok && res.d && res.d.draft) {
                ta.value = res.d.draft; wrap.style.display = 'block';
                if (hint) hint.style.display = res.d.draft.indexOf('[') !== -1 ? 'block' : 'none';
            } else {
                msg.textContent = (res.d && res.d.error) ? res.d.error : 'Entwurf konnte nicht erstellt werden — bitte erneut versuchen.';
                msg.style.display = 'block';
            }
        })
        .catch(function() { msg.textContent = 'Entwurf konnte nicht erstellt werden — bitte erneut versuchen.'; msg.style.display = 'block'; })
        .finally(function() { buttons.forEach(function(b) { b.disabled = false; }); clicked.textContent = original; });
    }

    buttons.forEach(function(b) { b.addEventListener('click', function() { run(b.getAttribute('data-grounding'), b); }); });
    document.getElementById('aiassist-insert').addEventListener('click', function() { insertIntoReply(ta.value); });
    document.getElementById('aiassist-copy').addEventListener('click', function() {
        ta.select(); try { document.execCommand('copy'); } catch (e) {}
    });
})();
</script>
@endif
