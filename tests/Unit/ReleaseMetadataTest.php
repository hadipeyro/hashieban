<?php

declare(strict_types=1);

namespace Hashieban\Tests\Unit;

use Hashieban\Tests\TestCase;

final class ReleaseMetadataTest extends TestCase
{
    public function testPluginHeaderAndRuntimeVersionMatch(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/hashieban.php');
        $this->assertTrue(is_string($source));

        preg_match('/^ \* Version:\s+([^\r\n]+)/m', (string) $source, $header);
        preg_match("/define\('HASHIEBAN_VERSION',\s*'([^']+)'\);/", (string) $source, $constant);

        $this->assertTrue(isset($header[1]));
        $this->assertTrue(isset($constant[1]));
        $this->assertSame(trim((string) $header[1]), trim((string) $constant[1]));
        $this->assertSame('0.99.1', trim((string) $constant[1]));
    }

    public function testReleaseCandidateDocumentationExists(): void
    {
        $root = dirname(__DIR__, 2);
        $this->assertTrue(is_readable($root . '/CHANGELOG.md'));
        $this->assertTrue(is_readable($root . '/readme.txt'));
        $this->assertTrue(is_readable($root . '/docs/QA-CHECKLIST-FA.md'));
    }
}
