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

            $result = DraftService::draft($conversation);
            if (!empty($result['error']) || trim((string) $result['text']) === '') {
                return response()->json(['error' => $result['error'] ?: 'Entwurf konnte nicht erstellt werden — bitte erneut versuchen.'], 422);
            }
            return response()->json(['draft' => $result['text'], 'source' => $result['source']]);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Entwurf konnte nicht erstellt werden — bitte erneut versuchen.'], 502);
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
                ], ['model' => Settings::model(), 'messages' => [['role' => 'user', 'content' => 'ping']], 'max_tokens' => 1, 'store' => false], 10);
            } else {
                $res = $http->request('POST', 'https://api.anthropic.com/v1/messages', [
                    'x-api-key: ' . $key, 'anthropic-version: 2023-06-01', 'content-type: application/json', 'accept: application/json',
                ], ['model' => Settings::model(), 'max_tokens' => 1, 'messages' => [['role' => 'user', 'content' => 'ping']]], 10);
            }
            if (($res['transport_error'] ?? null) !== null) {
                return response()->json(['success' => false, 'message' => 'Verbindungsfehler: ' . $res['transport_error']]);
            }
            $status = (int) ($res['status'] ?? 0);
            if ($status === 401 || $status === 403) {
                return response()->json(['success' => false, 'message' => 'API-Key ungültig (HTTP ' . $status . ').']);
            }
            if ($status >= 200 && $status < 500) {
                return response()->json(['success' => true, 'message' => 'Verbindung erfolgreich (HTTP ' . $status . ').']);
            }
            return response()->json(['success' => false, 'message' => 'Unerwarteter Status: HTTP ' . $status]);
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
