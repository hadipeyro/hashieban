<?php

declare(strict_types=1);

namespace Hashieban\Tests\Unit;

use Hashieban\Security\Csv;
use Hashieban\Security\Json;
use Hashieban\Tests\TestCase;

final class SecurityTest extends TestCase
{
    public function testCsvFormulaInjectionIsNeutralized(): void
    {
        $this->assertSame("'=SUM(A1:A2)", Csv::protectCell('=SUM(A1:A2)'));
        $this->assertSame("'+cmd", Csv::protectCell('+cmd'));
        $this->assertSame("'@danger", Csv::protectCell('@danger'));
        $this->assertSame('-123.45', Csv::protectCell('-123.45'));
        $this->assertSame('محصول سالم', Csv::protectCell('محصول سالم'));
    }

    public function testJsonForScriptEscapesExecutableMarkup(): void
    {
        $encoded = Json::forHtmlScript(array('value' => '</script><script>alert(1)</script>'));

        $this->assertFalse(strpos($encoded, '</script>') !== false);
        $this->assertTrue(strpos($encoded, '\\u003C') !== false);
    }
}
