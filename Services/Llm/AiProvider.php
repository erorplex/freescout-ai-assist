<?php

namespace Modules\AiAssist\Services\Llm;

/**
 * Returns the raw draft text. Post-processing happens OUTSIDE the provider.
 * Throws on transport/HTTP error.
 */
interface AiProvider
{
    public function draft(array $messages, string $system, array $opts): string;
}
