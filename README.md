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

Direct install (recommended first): download `AiAssist.zip` from [Releases](https://github.com/erorplex/freescout-ai-assist/releases/latest), unzip so a single `AiAssist/` folder lands in your FreeScout `Modules/` directory, then activate under **Modules**. FreeScout's one-click update then tracks new releases via `module.json`.

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
