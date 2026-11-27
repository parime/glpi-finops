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

use GlpiPlugin\Finops\AssetMatcher;
use PHPUnit\Framework\TestCase;

/**
 * Rattachement assisté (issue #6) : lecture de l'ordre des règles et valeurs cherchées côté GLPI.
 */
final class AssetMatcherKeysTest extends TestCase
{
    public function testParseRulesKeepsKnownRulesInOrder(): void
    {
        $this->assertSame(['serial', 'name'], AssetMatcher::parseRules(' Serial ; name, unknown,serial'));
        $this->assertSame([], AssetMatcher::parseRules(''));
        $this->assertSame(AssetMatcher::RULES, AssetMatcher::parseRules(implode(',', AssetMatcher::RULES)));
    }

    public function testKeysAddTheLastSegmentOfAPath(): void
    {
        $this->assertSame(['i-0abc'], AssetMatcher::keys(' i-0abc '));
        $this->assertSame(['projects/p1/instances/i-0abc', 'i-0abc'], AssetMatcher::keys('projects/p1/instances/i-0abc'));
        $this->assertSame(['arn:aws:ec2:eu-west-3:1:instance/i-1', 'i-1'], AssetMatcher::keys('arn:aws:ec2:eu-west-3:1:instance/i-1'));
        $this->assertSame([], AssetMatcher::keys(''));
    }
}
