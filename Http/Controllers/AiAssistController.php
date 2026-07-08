<?php

namespace Modules\AiAssist\Http\Controllers;

use App\Conversation;
use App\Http\Controllers\Controller;
use Modules\AiAssist\Services\DraftService;
use Modules\AiAssist\Services\Llm\HttpJson;
use Modules\AiAssist\Services\Settings;

class AiAssistController extends Controller
{
    /**
     * POST /aiassist/draft/{id} -> { draft } | { error }.
     * Renders the draft into the panel; NEVER auto-inserts / auto-sends.
     */
    public function draft($conversationId)
    {
        try {
            if (!Settings::featureOn('enabled')) {
                return response()->json(['error' => 'KI-Antworten sind deaktiviert.'], 403);
            }
            $conversation = Conversation::find((int) $conversationId);
            if (!$conversation) {
                return response()->json(['error' => 'Ticket nicht gefunden.'], 404);
            }
            if (!auth()->user() || !auth()->user()->can('view', $conversation)) {
                return response()->json(['error' => 'Kein Zugriff auf dieses Ticket.'], 403);
            }

            $result = DraftService::draft($conversation, [
                // "grounding=off" = the "Ohne Daten" button (force standalone).
                'force_standalone' => request('grounding') === 'off',
                // Optional one-off instruction the agent typed for THIS draft.
                'instruction'      => mb_substr((string) request('instruction', ''), 0, 1000),
            ]);
            if (!empty($result['error']) || trim((string) $result['text']) === '') {
                return response()->json(['error' => $result['error'] ?: 'Entwurf konnte nicht erstellt werden — bitte erneut versuchen.'], 422);
            }
            return response()->json(['draft' => $result['text'], 'source' => $result['source']]);
        } catch (\Throwable $e) {
            // Surface the provider's error (e.g. "unsupported parameter", "model
            // not found", rate limit) so the admin can act on it. It carries no
            // key/prompt — the provider classes only throw the API's error text.
            $m = trim($e->getMessage());
            return response()->json(['error' => $m !== '' ? ('KI-Anbieter: ' . mb_substr($m, 0, 300)) : 'Entwurf konnte nicht erstellt werden — bitte erneut versuchen.'], 502);
        }
    }

    /** Cheap LLM ping (max_tokens:1). */
    public function testLlm()
    {
        $key = Settings::apiKey();
        if ($key === '') {
            return response()->json(['success' => false, 'message' => 'Kein API-Key gesetzt.']);
        }
        try {
            $http = new HttpJson();
            if (Settings::provider() === 'openai') {
                $res = $http->request('POST', 'https://api.openai.com/v1/chat/completions', [
                    'Authorization: Bearer ' . $key, 'content-type: application/json', 'accept: application/json',
                ], ['model' => Settings::model(), 'messages' => [['role' => 'user', 'content' => 'ping']], 'max_completion_tokens' => 5, 'store' => false], 10);
            } else {
                $res = $http->request('POST', 'https://api.anthropic.com/v1/messages', [
                    'x-api-key: ' . $key, 'anthropic-version: 2023-06-01', 'content-type: application/json', 'accept: application/json',
                ], ['model' => Settings::model(), 'max_tokens' => 1, 'messages' => [['role' => 'user', 'content' => 'ping']]], 10);
            }
            if (($res['transport_error'] ?? null) !== null) {
                return response()->json(['success' => false, 'message' => 'Verbindungsfehler: ' . $res['transport_error']]);
            }
            $status = (int) ($res['status'] ?? 0);
            // Only a 2xx is a real success. A 400 (wrong model / unsupported
            // parameter) is a FAILURE — surface the provider's message so the
            // admin can fix it instead of getting a false "successful" test.
            if ($status >= 200 && $status < 300) {
                return response()->json(['success' => true, 'message' => 'Verbindung erfolgreich (HTTP ' . $status . ').']);
            }
            $apiMsg = $res['json']['error']['message'] ?? ('HTTP ' . $status);
            return response()->json(['success' => false, 'message' => 'Fehler (HTTP ' . $status . '): ' . mb_substr((string) $apiMsg, 0, 300)]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Fehler: ' . $e->getMessage()]);
        }
    }

    /** Flowkom capability ping (GET /api/freescout/ai-draft). */
    public function testCap()
    {
        try {
            $ok = DraftService::flowkomClient()->available();
            return response()->json([
                'success' => $ok,
                'message' => $ok ? 'Flowkom-Datenzugriff aktiv (ai_draft: true).' : 'Kein Datenzugriff (S2 aus / Key ungültig / nicht erreichbar).',
            ]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Fehler: ' . $e->getMessage()]);
        }
    }
}
