<?php

declare(strict_types=1);

namespace Publicplan\DocumentProcessor\Tests\Service;

use PHPUnit\Framework\TestCase;
use Publicplan\DocumentProcessor\Service\HtmlParityComparator;

class HtmlParityComparatorTest extends TestCase
{
    private HtmlParityComparator $comparator;

    protected function setUp(): void
    {
        $this->comparator = new HtmlParityComparator();
    }

    public function testCompareReturnsPerfectParityForIdenticalHtml(): void
    {
        $result = $this->comparator->compare('<p>Alpha</p>' . PHP_EOL, '<p>Alpha</p>' . PHP_EOL);

        $this->assertTrue($result->stringsMatch);
        $this->assertTrue($result->domMatches);
        $this->assertSame([], $result->stringDiff);
        $this->assertSame([], $result->domDiff);
    }

    public function testCompareKeepsDomParityForWhitespaceOnlyDifference(): void
    {
        $result = $this->comparator->compare('<p>Alpha</p>' . PHP_EOL, '<p>Alpha</p>');

        $this->assertFalse($result->stringsMatch);
        $this->assertTrue($result->domMatches);
        $this->assertArrayHasKey('firstMismatch', $result->stringDiff);
        $this->assertSame([], $result->domDiff);
    }

    public function testCompareReportsDomDifferencesSeparately(): void
    {
        $result = $this->comparator->compare('<p>Alpha</p>', '<div>Alpha</div>');

        $this->assertFalse($result->stringsMatch);
        $this->assertFalse($result->domMatches);
        $this->assertNotEmpty($result->domDiff);
        $this->assertContains(
            'node-name-mismatch',
            array_column($result->domDiff, 'reason')
        );
    }
}
