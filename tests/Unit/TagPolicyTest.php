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

use GlpiPlugin\Finops\TagPolicy;
use PHPUnit\Framework\TestCase;

final class TagPolicyTest extends TestCase
{
    public function testParseListTrimsDeduplicatesAndIgnoresCase(): void
    {
        $this->assertSame(
            ['owner', 'cost-center', 'env'],
            TagPolicy::parseList(" owner, cost-center ;\nenv, Owner,, ")
        );
        $this->assertSame([], TagPolicy::parseList(''));
        $this->assertCount(TagPolicy::MAX_TAGS, TagPolicy::parseList(implode(',', range(1, 40))));
    }

    public function testDecodeAcceptsOnlyAFlatObject(): void
    {
        $this->assertSame(['env' => 'prod', 'owner' => 'a.martin'], TagPolicy::decode('{"env":"prod","owner":"a.martin"}'));
        $this->assertSame([], TagPolicy::decode(null));
        $this->assertSame([], TagPolicy::decode('not json'));
        $this->assertSame([], TagPolicy::decode('{"a":{"b":"c"}}'));
    }

    public function testMissingIgnoresKeyCaseAndCountsEmptyValues(): void
    {
        $tags = ['Owner' => 'a.martin', 'env' => '  ', 'project' => 'demo'];
        $this->assertSame(['cost-center', 'env'], TagPolicy::missing($tags, ['owner', 'cost-center', 'env']));
        $this->assertSame('a.martin', TagPolicy::value($tags, 'OWNER'));
        $this->assertSame('', TagPolicy::value($tags, 'team'));
        $this->assertSame([], TagPolicy::missing($tags, []));
    }
}
