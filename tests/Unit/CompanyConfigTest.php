<?php

declare(strict_types=1);

namespace Tests\Unit;

use Tests\TestCase;

class CompanyConfigTest extends TestCase
{
    public function test_the_registered_name_keeps_its_article_when_the_environment_drops_it(): void
    {
        $this->assertSame(
            'The Saban Company B.V.',
            $this->legalNameFor('Saban Company B.V.'),
        );
    }

    public function test_a_name_that_already_carries_the_article_is_left_alone(): void
    {
        $this->assertSame(
            'The Saban Company B.V.',
            $this->legalNameFor('The Saban Company B.V.'),
        );
    }

    public function test_surrounding_whitespace_is_trimmed(): void
    {
        $this->assertSame(
            'The Saban Company B.V.',
            $this->legalNameFor('  The Saban Company B.V.  '),
        );
    }

    public function test_another_operator_keeps_its_own_name(): void
    {
        $this->assertSame(
            'The Example Holding B.V.',
            $this->legalNameFor('The Example Holding B.V.'),
        );
    }

    /**
     * Re-reads config/company.php with COMPANY_LEGAL_NAME set to the given value,
     * because the container holds the config as it was resolved during boot.
     */
    private function legalNameFor(string $envValue): string
    {
        $original = $_SERVER['COMPANY_LEGAL_NAME'] ?? null;

        $_SERVER['COMPANY_LEGAL_NAME'] = $envValue;
        $_ENV['COMPANY_LEGAL_NAME'] = $envValue;

        try {
            $config = require config_path('company.php');

            return $config['legal_name'];
        } finally {
            $_SERVER['COMPANY_LEGAL_NAME'] = $original;
            $_ENV['COMPANY_LEGAL_NAME'] = $original;
        }
    }
}
