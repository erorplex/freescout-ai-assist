<?php

namespace Modules\AiAssist\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\AiAssist\Services\DraftService;
use Modules\AiAssist\Services\Settings;

define('AIASSIST_MODULE', 'aiassist');

/**
 * freescout-ai-assist — guidance-first AI reply drafts. Additive & isolated:
 * own alias (aiassist), own namespace. Registers a second
 * conversation.after_customer_sidebar action; the Flowkom Suite is untouched.
 * No CSP connect-src needed — all LLM/Flowkom calls are server-side curl.
 */
class AiAssistServiceProvider extends ServiceProvider
{
    protected $defer = false;

    public function boot()
    {
        $this->loadViewsFrom(__DIR__ . '/../Resources/views', 'aiassist');

        Settings::register();
        $this->registerRoutes();

        if (Settings::featureOn('enabled')) {
            DraftService::registerButton(); // conversation.after_customer_sidebar, prio 35, fail-open
        }
    }

    public function register()
    {
        //
    }

    private function registerRoutes(): void
    {
        \Route::group([
            'middleware' => ['web', 'auth'],
            'prefix'     => 'aiassist',
            'namespace'  => 'Modules\AiAssist\Http\Controllers',
        ], function () {
            \Route::post('/draft/{conversationId}', 'AiAssistController@draft')->name('aiassist.draft');
            \Route::post('/test-llm', 'AiAssistController@testLlm')->name('aiassist.test_llm');
            \Route::post('/test-cap', 'AiAssistController@testCap')->name('aiassist.test_cap');
        });
    }
}
