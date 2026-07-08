# freescout-ai-assist

Guidance-first AI reply **drafts** for [FreeScout](https://freescout.net). The module lays an AI-generated draft into a sidebar panel; **the human always edits and sends** — there is no auto-send, no auto-refund, no auto-cancel.

- **Standalone (BYOK):** the module calls your LLM directly (Anthropic Claude or OpenAI) with your own API key. No commerce data — only the cleaned ticket text, an optional inserted knowledge base, and your guidance.
- **Connected (optional):** if you run [Flowkom](https://flowkom.de) and enable *AI data access* there, the module routes to Flowkom's `/api/freescout/ai-draft`, which grounds the draft with order/shipping status and returns only the finished text. Flowkom uses its own key.

Two independent switches, both **OFF by default**:

| Switch | Lives in | Effect |
|---|---|---|
| **S1 "KI-Antworten"** (`aiassist.enabled`) | this module | Whether the button renders / any LLM is called. Holds the standalone BYOK key. |
| **S2 "KI-Datenzugriff"** | Flowkom (per workspace) | Whether `/ai-draft` grounds + drafts. UI-toggle, never SQL. |

## Deterministic guardrails (the model is never trusted)

At `links_policy = block` (forced for eBay/Amazon) the server strips, in order: markdown `[text](url)` → `text`, HTML `<a>…</a>` → inner text, bare `http(s)://` / `www.` URLs, and bare emails / `mailto:`. This closes the most common off-platform-contact leak. **It is not a full compliance guarantee** — phone numbers, obfuscated forms (`hxxp`, "example dot com") and leftover fragments remain the human reviewer's job.

## Install

FreeScout loads a module by finding its folder at `Modules/<Name>/` and, on activation, booting the module's service provider. So the **whole** `AiAssist/` folder must arrive intact — an incomplete upload (e.g. `module.json` transfers but the PHP class files do not) makes FreeScout throw `Class "Modules\AiAssist\Providers\AiAssistServiceProvider" not found` on every request. See [Troubleshooting](#troubleshooting--recovery) if that happens.

**Recommended — extract on the server (avoids incomplete FTP transfers):**

1. Download `AiAssist.zip` from the [latest release](https://github.com/erorplex/freescout-ai-assist/releases/latest).
2. Upload the **ZIP file** to your server and **extract it server-side** — via your hosting control panel's file manager (all-inkl/KAS, Plesk, cPanel all have "Extract") or over SSH (`unzip AiAssist.zip -d /path/to/freescout/Modules/`). The result must be the folder `Modules/AiAssist/` (containing `module.json`, `Providers/`, `Services/`, …). Server-side extraction guarantees every one of the ~22 files arrives — plain FTP of many small files can silently drop some.
3. In FreeScout open **Manage → Modules**, find **AiAssist**, and click **Activate**. If your install uses config caching, click **Clear Cache** (Manage → System → Tools) first.

**If you can only use FTP:** unzip the archive **locally** first, then upload the entire `AiAssist/` folder into `Modules/`. Afterwards verify on the server that these exist and are non-empty **before** activating:

- `Modules/AiAssist/module.json`
- `Modules/AiAssist/Providers/AiAssistServiceProvider.php`
- `Modules/AiAssist/Services/` (10 `.php` files) and `Modules/AiAssist/Http/Controllers/AiAssistController.php`

Once installed, FreeScout's one-click update tracks new releases automatically via `module.json`.

## Troubleshooting / Recovery

**Symptom:** after activating, the helpdesk shows *"Whoops / Application error"* or a 500 on every page. The log (`storage/logs/laravel-YYYY-MM-DD.log`) shows `Class "Modules\AiAssist\Providers\AiAssistServiceProvider" not found`.

**Cause:** the module folder is incomplete (the provider/class files were not uploaded fully).

**Recovery — delete BOTH of these paths, then your helpdesk is immediately back:**

1. `Modules/AiAssist/` — the module folder.
2. `bootstrap/cache/ai_assist_module.php` — FreeScout's cached pointer to the module's provider.

> ⚠️ Deleting only the folder is **not enough** — FreeScout keeps the cached provider reference in `bootstrap/cache/ai_assist_module.php` and will keep erroring (and can make it look "even more broken") until that file is removed too. Remove both, reload, and the helpdesk is healthy again.

Then re-install completely (server-side extraction recommended).

## Data protection (DSGVO-first)

- **DPA / ZDR are installer duties.** Before enabling, sign a Data Processing Agreement (DPA) with your LLM provider and enable **Zero Data Retention**. The module gates every LLM call behind `aiassist.dpa_acknowledged`.
- **EU–US transfer basis:** direct endpoints `api.anthropic.com` / `api.openai.com` (US) in v0. Compliance basis = DPA + ZDR + SCC / EU-US Data Privacy Framework — document your own basis. EU-endpoint providers (Bedrock/Vertex/OpenAI-EU) are a later provider class, not v0.
- **`safe_password` honesty:** the provider key field is **masked in the UI and kept on empty submit; it is NOT encrypted at rest.** The key lives in your self-hosted FreeScout DB. DB-at-rest encryption is your responsibility.
- **Internal notes never reach the LLM.** `MailCleaner` is a display aid, **not** a PII control — it is not claimed as one.

### What is shared

| Mode | Sent to the LLM | NOT sent |
|---|---|---|
| Standalone | cleaned ticket text, inserted KB, instructions | address, phone, email, payment data, SKU, tracking number, internal notes |
| Connected | + order/shipping status, product title + qty, channel, salutation | (same as above) |

## License

MIT.
