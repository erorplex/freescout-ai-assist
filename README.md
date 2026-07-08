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

> ### ⚠️ Two things decide whether it works — get them right or FreeScout will 500
>
> **1. Download the right file.** On the [latest release](https://github.com/erorplex/freescout-ai-assist/releases/latest) download the asset **`AiAssist.zip`** (under *Assets*). **Do NOT** use the green *Code ▸ Download ZIP* button or *Source code (zip)* — those give you a folder named `freescout-ai-assist-main` / `freescout-ai-assist-x.y.z`, which is the **wrong name** and will not load.
>
> **2. The folder in `Modules/` must be named exactly `AiAssist`.** FreeScout resolves the class `Modules\AiAssist\Providers\…` to the path `Modules/AiAssist/…`. If your folder is named anything else, rename it to `AiAssist`. A wrong name (or an incomplete upload) makes FreeScout throw `Class "Modules\AiAssist\Providers\AiAssistServiceProvider" not found` on **every** request — see [Recovery](#troubleshooting--recovery).

**Recommended — extract on the server (avoids incomplete FTP transfers):**

1. Download **`AiAssist.zip`** (the release asset).
2. Upload the **ZIP** to your server and **extract it server-side** — via your hosting control panel's file manager (all-inkl/KAS, Plesk, cPanel all have "Extract") or over SSH (`unzip AiAssist.zip -d /path/to/freescout/Modules/`). The result must be exactly `Modules/AiAssist/` (containing `module.json`, `Providers/`, `Services/`, …). Server-side extraction guarantees every one of the ~22 files arrives — plain FTP of many small files can silently drop some.
3. In FreeScout open **Manage → Modules**, find **AiAssist**, and click **Activate**. If your install uses config caching, click **Clear Cache** (Manage → System → Tools) first.

**If you can only use FTP:** unzip `AiAssist.zip` **locally** first, then upload the entire `AiAssist/` folder into `Modules/`. Afterwards, **before** activating, verify on the server that the folder is named `AiAssist` and these exist and are non-empty:

- `Modules/AiAssist/module.json`
- `Modules/AiAssist/Providers/AiAssistServiceProvider.php`
- `Modules/AiAssist/Services/` (10 `.php` files) and `Modules/AiAssist/Http/Controllers/AiAssistController.php`

Once installed, FreeScout's one-click update tracks new releases automatically via `module.json`.

## Troubleshooting / Recovery

**Symptom:** after activating, the helpdesk shows *"Whoops / Application error"* or a **500 on every page**. `storage/logs/laravel-YYYY-MM-DD.log` shows `Class "Modules\AiAssist\Providers\AiAssistServiceProvider" not found`.

**Cause:** the module folder is missing / mis-named / incompletely uploaded, so the provider class can't be loaded — but FreeScout was already told to boot it.

**Recovery — delete these THREE things, then reload; the helpdesk is immediately back.** (The admin UI is unreachable while it's down, so do this over FTP or your file manager.)

1. `Modules/AiAssist/` **and** any wrongly-named copy such as `Modules/freescout-ai-assist-main/` — the module folder.
2. `bootstrap/cache/ai_assist_module.php` — FreeScout's cached pointer to the module's provider.
3. **The *contents* of `storage/framework/cache/data/`** (delete the files/subfolders inside, keep the `data` folder itself) — this is FreeScout's cached module list. If you skip this, FreeScout **re-creates** `bootstrap/cache/ai_assist_module.php` from the cached list on the next request and the 500 comes right back.

> ⚠️ Deleting only the folder — or only the folder + `ai_assist_module.php` — is **not enough**: FreeScout regenerates the cached pointer from the module-list cache in `storage/framework/cache/data/`. Clear all three and it stays fixed.

Then re-install cleanly per the [Install](#install) steps (right file, right folder name, server-side extraction).

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
