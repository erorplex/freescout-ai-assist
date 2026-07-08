@php $disabled = ($mode === 'disabled'); @endphp
<div class="aiassist-panel" style="margin-top:10px;border:1px solid #d6dce2;border-radius:4px;overflow:hidden;font-size:12px;">
    <div style="padding:7px 10px;background:#0f766e;color:#fff;font-weight:600;font-size:11px;letter-spacing:.5px;">KI-ANTWORT</div>
    <div style="padding:8px 10px;background:#fff;">
        <button type="button" id="aiassist-suggest" data-url="{{ htmlspecialchars($url, ENT_QUOTES) }}" @if($disabled) disabled title="Kein KI-Anbieter konfiguriert / DPA nicht bestätigt" @endif
            style="display:block;width:100%;text-align:center;padding:6px 0;border:0;border-radius:3px;font-size:11px;color:#fff;font-weight:500;background:{{ $disabled ? '#9ca3af' : '#0f766e' }};cursor:{{ $disabled ? 'not-allowed' : 'pointer' }};">
            {{ $disabled ? 'Kein KI-Anbieter konfiguriert' : 'Antwort vorschlagen' }}</button>
        <p style="margin:6px 0 0;color:#6b7280;font-size:10px;">Entwurf für den Menschen — bitte prüfen, bearbeiten und selbst senden.</p>
        <div id="aiassist-msg" style="display:none;margin-top:6px;color:#c0392b;"></div>
        <div id="aiassist-draft-wrap" style="display:none;margin-top:8px;">
            <textarea id="aiassist-draft" readonly style="width:100%;min-height:120px;font-size:12px;border:1px solid #d6dce2;border-radius:3px;padding:6px;"></textarea>
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
    var btn = document.getElementById('aiassist-suggest');
    if (!btn || btn.dataset.bound) return;
    btn.dataset.bound = '1';
    var msg = document.getElementById('aiassist-msg');
    var wrap = document.getElementById('aiassist-draft-wrap');
    var ta = document.getElementById('aiassist-draft');

    function insertIntoReply(text) {
        var tries = 0;
        (function poll() {
            var $body = window.jQuery ? window.jQuery('#body') : null;
            if ($body && $body.length && $body.summernote) {
                try { $body.summernote('pasteHTML', text.replace(/\n/g, '<br>')); return; } catch (e) {}
            }
            // open the reply form if not mounted yet
            var replyBtn = document.querySelector('.js-reply, #conv-reply, [data-toggle-reply]');
            if (replyBtn && tries === 0) { replyBtn.click(); }
            if (tries++ < 20) { setTimeout(poll, 250); }
            else { msg.textContent = 'Antwortformular nicht gefunden — bitte manuell kopieren.'; msg.style.display = 'block'; }
        })();
    }

    btn.addEventListener('click', function() {
        msg.style.display = 'none'; wrap.style.display = 'none';
        btn.disabled = true; var original = btn.textContent; btn.textContent = 'Wird erstellt…';
        var tokenEl = document.querySelector('meta[name=csrf-token]') || document.querySelector('input[name=_token]');
        var token = tokenEl ? (tokenEl.content || tokenEl.value) : '';
        fetch(btn.dataset.url, { method: 'POST', headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json' } })
        .then(function(r) { return r.json().then(function(d) { return { ok: r.ok, d: d }; }); })
        .then(function(res) {
            if (res.ok && res.d && res.d.draft) {
                ta.value = res.d.draft; wrap.style.display = 'block';
            } else {
                msg.textContent = (res.d && res.d.error) ? res.d.error : 'Entwurf konnte nicht erstellt werden — bitte erneut versuchen.';
                msg.style.display = 'block';
            }
        })
        .catch(function() { msg.textContent = 'Entwurf konnte nicht erstellt werden — bitte erneut versuchen.'; msg.style.display = 'block'; })
        .finally(function() { btn.disabled = false; btn.textContent = original; });
    });

    document.getElementById('aiassist-insert').addEventListener('click', function() { insertIntoReply(ta.value); });
    document.getElementById('aiassist-copy').addEventListener('click', function() {
        ta.select(); try { document.execCommand('copy'); } catch (e) {}
    });
})();
</script>
@endif
