<?php

declare(strict_types=1);

namespace Tests\Tempest\Unit\Validation\Rules;

use PHPUnit\Framework\TestCase;
use Tempest\Validation\Rules\Alpha;

/**
 * @internal
 * @small
 */
class AlphaTest extends TestCase
{
    public function test_alpha(): void
    {
        $rule = new Alpha();

        $this->assertSame('Value should only contain alphabetic characters', $rule->message());
        $this->assertFalse($rule->isValid('string123'));
        $this->assertTrue($rule->isValid('string'));
        $this->assertTrue($rule->isValid('STRING'));
    }
}
