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

use GlpiPlugin\Finops\Source\CostSource;
use GlpiPlugin\Finops\Source\GenericCsvSource;
use GlpiPlugin\Finops\Source\SourceRegistry;
use PHPUnit\Framework\TestCase;

final class SourceRegistryTest extends TestCase
{
    public function testDiscoversTheGenericCsvSource(): void
    {
        $source = SourceRegistry::get('csv');
        $this->assertInstanceOf(GenericCsvSource::class, $source);
        $this->assertTrue($source->isAvailable());
        $this->assertSame(CostSource::KIND_FILE, $source->kind());
    }

    public function testKeysAreUniqueAndLowercase(): void
    {
        $keys = array_map(static fn (CostSource $source): string => $source->key(), SourceRegistry::all());
        $this->assertNotEmpty($keys);
        $this->assertSame($keys, array_values(array_unique($keys)));
        foreach ($keys as $key) {
            $this->assertMatchesRegularExpression('/^[a-z0-9-]+$/', $key);
        }
    }

    public function testDescribeGivesTheIconOfEachSource(): void
    {
        foreach (SourceRegistry::describe() as $row) {
            $this->assertStringStartsWith('ti ti-', $row['icon']);
            $this->assertNotSame('', $row['label']);
        }
    }

    public function testUnknownSourceIsNull(): void
    {
        $this->assertNull(SourceRegistry::get('unknown'));
    }
}
