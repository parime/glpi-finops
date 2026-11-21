<?php

/**
 * -------------------------------------------------------------------------
 * FinOps plugin for GLPI
 * Copyright (C) 2026 Vincent GUILLOTTE
 * https://github.com/parime/glpi-finops
 * -------------------------------------------------------------------------
 * LICENSE
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version. See LICENSE for the full text.
 * -------------------------------------------------------------------------
 */

declare(strict_types=1);

namespace GlpiPlugin\Finops\Tests\Unit;

use GlpiPlugin\Finops\Import\CsvCostParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Lecture du CSV commun, dont les fichiers piégés (binaire, code, trop gros, trop large, mal formé).
 * Données fictives uniquement.
 */
final class CsvCostParserTest extends TestCase
{
    private const HEADER = "date,provider,account,service,resource,cost,currency,tags\n";

    private function parse(string $content, int $maxBytes = 1048576): array
    {
        return (new CsvCostParser('EUR', $maxBytes))->parseString($content);
    }

    public function testReadsTheCommonFormat(): void
    {
        $result = $this->parse(self::HEADER
            . "2026-10-01,AWS,111111111111,Amazon EC2,i-0abc,12.5,USD,team=web;env=prod\n"
            . "2026-10-02,azure,sub-demo,Virtual Machines,vm-demo-01,3,,\n");

        $this->assertNull($result['fatal']);
        $this->assertSame(0, $result['rejected']);
        $this->assertCount(2, $result['rows']);
        $first = $result['rows'][0];
        $this->assertSame('2026-10-01', $first['usage_date']);
        $this->assertSame('2026-10', $first['usage_month']);
        $this->assertSame('aws', $first['provider']);
        $this->assertSame('12.500000', $first['cost']);
        $this->assertSame('USD', $first['currency']);
        $this->assertSame('{"env":"prod","team":"web"}', $first['tags']);
        $this->assertSame(64, strlen($first['source_key']));
        // Devise absente : devise par défaut.
        $this->assertSame('EUR', $result['rows'][1]['currency']);
    }

    public function testAcceptsSemicolonsBomAliasesAndFrenchNumbers(): void
    {
        $result = $this->parse("\xEF\xBB\xBFUsage Date;Cloud;Subscription;Product;Resource ID;Amount;Currency Code\n"
            . "01/10/2026;ovhcloud;projet-demo;Public Cloud;b2-7-demo;\"1 234,56\";eur\n");

        $this->assertNull($result['fatal']);
        $row = $result['rows'][0];
        $this->assertSame('2026-10-01', $row['usage_date']);
        $this->assertSame('ovhcloud', $row['provider']);
        $this->assertSame('projet-demo', $row['account']);
        $this->assertSame('1234.560000', $row['cost']);
        $this->assertSame('EUR', $row['currency']);
    }

    public function testSameLineTwiceInAFileKeepsTwoKeysAndTheSameKeysOnReimport(): void
    {
        $content = self::HEADER . str_repeat("2026-10-01,aws,1,EC2,i-1,1,USD,\n", 2);
        $first = $this->parse($content);
        $second = $this->parse($content);

        $this->assertCount(2, $first['rows']);
        $this->assertNotSame($first['rows'][0]['source_key'], $first['rows'][1]['source_key']);
        $this->assertSame(array_column($first['rows'], 'source_key'), array_column($second['rows'], 'source_key'));
    }

    public function testRejectsInvalidLinesAndKeepsTheOthers(): void
    {
        $result = $this->parse(self::HEADER
            . "2026-02-30,aws,1,EC2,i-1,1,USD,\n"
            . "2026-10-01,,1,EC2,i-1,1,USD,\n"
            . "2026-10-01,aws,1,EC2,i-1,douze,USD,\n"
            . "2026-10-01,aws,1,EC2,i-1,1,DOLLARS,\n"
            . "\n"
            . "2026-10-01,aws,1,EC2,i-1,1,USD,\n");

        $this->assertSame(4, $result['rejected']);
        $this->assertCount(1, $result['rows']);
        $this->assertSame(
            [CsvCostParser::E_DATE, CsvCostParser::E_PROVIDER, CsvCostParser::E_COST, CsvCostParser::E_CURRENCY],
            array_column($result['errors'], 'code')
        );
        $this->assertSame([2, 3, 4, 5], array_column($result['errors'], 'line'));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function trappedFiles(): array
    {
        return [
            'binary file'         => ["\x89PNG\r\n\x1a\n\0\0\0\rIHDR", CsvCostParser::E_BINARY],
            'php code'            => [
                self::HEADER . "2026-10-01,aws,<?php system('id'); ?>,EC2,i-1,1,USD,\n",
                CsvCostParser::E_CODE,
            ],
            'short php tag'       => ["<?= phpinfo() ?>\n" . self::HEADER, CsvCostParser::E_CODE],
            'empty file'          => ["  \n\n", CsvCostParser::E_EMPTY],
            'no cost column'      => ["date,provider,service\n2026-10-01,aws,EC2\n", CsvCostParser::E_COLUMNS],
            'too many columns'    => [implode(',', range(1, 80)) . "\n", CsvCostParser::E_TOO_MANY_COLUMNS],
        ];
    }

    #[DataProvider('trappedFiles')]
    public function testRefusesTrappedFiles(string $content, string $code): void
    {
        $result = $this->parse($content);

        $this->assertSame($code, $result['fatal']);
        $this->assertSame([], $result['rows']);
    }

    public function testRefusesAFileLargerThanTheLimitBeforeReadingIt(): void
    {
        $result = $this->parse(self::HEADER . str_repeat("2026-10-01,aws,1,EC2,i-1,1,USD,\n", 100), 512);

        $this->assertSame(CsvCostParser::E_TOO_LARGE, $result['fatal']);

        $path = tempnam(sys_get_temp_dir(), 'finops');
        file_put_contents($path, str_repeat('x', 2048));
        try {
            $this->assertSame(CsvCostParser::E_TOO_LARGE, (new CsvCostParser('EUR', 1024))->parseFile($path)['fatal']);
        } finally {
            unlink($path);
        }
    }

    public function testCleansCellsAndKeepsMarkupAsPlainText(): void
    {
        $long = str_repeat('a', 400);
        $result = $this->parse(self::HEADER
            . "2026-10-01,aws,\"=HYPERLINK(\"\"http://example.invalid\"\")\",\"<svg/onload=alert(1)>\",\"$long\","
            . "1,USD,\"{\"\"owner\"\":\"\"<b>x</b>\"\"}\"\n"
            . "2026-10-01,aws,\"acc\x07ount\",EC2,i-2,1,USD,\n");

        $this->assertNull($result['fatal']);
        $row = $result['rows'][0];
        // Rien n'est interprété : la cellule est gardée en texte, l'échappement se fait à l'affichage.
        $this->assertSame('<svg/onload=alert(1)>', $row['service']);
        $this->assertSame(CsvCostParser::MAX_FIELD, mb_strlen($row['resource']));
        $this->assertSame('{"owner":"<b>x</b>"}', $row['tags']);
        $this->assertSame('acc ount', $result['rows'][1]['account']);
    }

    public function testConvertsWindows1252ToUtf8(): void
    {
        $result = $this->parse(self::HEADER . "2026-10-01,aws,soci\xE9t\xE9,EC2,i-1,1,EUR,\n");

        $this->assertSame('société', $result['rows'][0]['account']);
    }

    /**
     * @return array<string, array{string, ?string}>
     */
    public static function amounts(): array
    {
        return [
            'dot'               => ['12.5', '12.500000'],
            'comma'             => ['12,5', '12.500000'],
            'thousands comma'   => ['1,234.50', '1234.500000'],
            'thousands dot'     => ['1.234,50', '1234.500000'],
            'negative (credit)' => ['-3.20', '-3.200000'],
            'text'              => ['abc', null],
            'empty'             => ['', null],
            'too large'         => ['99999999999999', null],
            'exponent'          => ['1e5', null],
        ];
    }

    #[DataProvider('amounts')]
    public function testParsesAmounts(string $value, ?string $expected): void
    {
        $this->assertSame($expected, CsvCostParser::parseAmount($value));
    }

    public function testParsesDates(): void
    {
        $this->assertSame('2026-10-31', CsvCostParser::parseDate('2026-10-31T23:00:00Z'));
        $this->assertSame('2026-10-31', CsvCostParser::parseDate('31/10/2026'));
        $this->assertNull(CsvCostParser::parseDate('1999-12-31'));
        $this->assertNull(CsvCostParser::parseDate('2026-13-01'));
        $this->assertNull(CsvCostParser::parseDate('next monday'));
    }

    public function testAllowsOnlyCsvAndTextNames(): void
    {
        $this->assertTrue(CsvCostParser::isAllowedName('costs-2026-10.CSV'));
        $this->assertTrue(CsvCostParser::isAllowedName('costs.txt'));
        $this->assertFalse(CsvCostParser::isAllowedName('costs.csv.php'));
        $this->assertFalse(CsvCostParser::isAllowedName('costs.xlsx'));
        $this->assertFalse(CsvCostParser::isAllowedName('costs'));
    }
}
