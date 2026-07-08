<?php

namespace Modules\AiAssist\Tests;

use Modules\AiAssist\Support\Version;
use PHPUnit\Framework\TestCase;

class SmokeTest extends TestCase
{
    public function testModuleVersionConstantIsSemver(): void
    {
        $this->assertMatchesRegularExpression('/^\d+\.\d+\.\d+$/', Version::MODULE);
    }
}
