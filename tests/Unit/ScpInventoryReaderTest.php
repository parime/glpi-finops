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

use GlpiPlugin\Finops\Sangfor\ScpInventoryReader;
use GlpiPlugin\Finops\Tests\Fake\FakeScp;
use PHPUnit\Framework\TestCase;

final class ScpInventoryReaderTest extends TestCase
{
    public function testAllocationsAreReadFromTheDocumentedFieldsAndTheirAlternatives(): void
    {
        $web = ScpInventoryReader::vm([
            'id' => FakeScp::VM_WEB, 'name' => 'web', 'power_state' => 'on', 'cores' => 4, 'memory_mb' => 8192.0,
            'disks' => [['size_mb' => 51200.0], ['size_mb' => 51200.0]], 'host_name' => 'node-a',
        ]);
        $this->assertNotNull($web);
        $this->assertSame(['on', 4, 8192, 102400, 'node-a'], [
            $web['power'], $web['vcpu'], $web['memory_mb'], $web['disk_mb'], $web['node'],
        ]);

        $other = ScpInventoryReader::vm([
            'uuid' => 'X-1', 'status' => 'stopped', 'sockets' => 2, 'cores_per_socket' => 3,
            'memory_status' => ['total_mb' => '2048'], 'storage_status' => ['total_mb' => 1024], 'host_id' => 'h-1',
        ]);
        $this->assertNotNull($other);
        $this->assertSame(['X-1', 'off', 6, 2048, 1024, 'h-1'], [
            $other['name'], $other['power'], $other['vcpu'], $other['memory_mb'], $other['disk_mb'], $other['node'],
        ]);
        $this->assertNull(ScpInventoryReader::vm(['name' => 'no id']));
        $this->assertSame('unknown', ScpInventoryReader::vm(['id' => 'a'])['power'] ?? '');
    }

    public function testNodesAcceptMissingFields(): void
    {
        $node = ScpInventoryReader::node(['id' => 'h', 'ip' => '192.0.2.9', 'cpu' => ['socket_count' => 2,
            'cores_per_socket' => 8]]);
        $this->assertSame(['192.0.2.9', 16, 0], [$node['name'], $node['cores'], $node['memory_mb']]);
    }

    public function testCostOfAPoweredOffMachineIsOnlyItsDisk(): void
    {
        $grid = ['vcpu' => 10.0, 'ram_gb' => 2.0, 'disk_gb' => 0.1];
        $vm = ['vm_id' => 'a', 'power' => 'on', 'vcpu' => 4, 'memory_mb' => 8192, 'disk_mb' => 102400];
        $this->assertEqualsWithDelta(40 + 16 + 10, ScpInventoryReader::monthlyCost($vm, $grid), 0.0001);
        $this->assertEqualsWithDelta(10, ScpInventoryReader::monthlyCost(['power' => 'off'] + $vm, $grid), 0.0001);
    }

    public function testCostRowsAreMonthlyAndKeyedPerPlatformMachineAndMonth(): void
    {
        $vm = ScpInventoryReader::vm(['id' => 'vm-1', 'name' => 'web', 'cores' => 1, 'memory_mb' => 1024]);
        $this->assertNotNull($vm);
        $grid = ['vcpu' => 5.0, 'ram_gb' => 1.0, 'disk_gb' => 0.0];
        $rows = ScpInventoryReader::costRows([$vm], $grid, 'EUR', 'Lab', 7, '2026-11')['rows'];
        $this->assertSame(['2026-11-01', 'sangfor', 'vm-1', '6.000000', 'EUR'], [
            $rows[0]['usage_date'], $rows[0]['provider'], $rows[0]['resource'], $rows[0]['cost'], $rows[0]['currency'],
        ]);
        $again = ScpInventoryReader::costRows([$vm], $grid, 'EUR', 'Lab', 7, '2026-11')['rows'];
        $next = ScpInventoryReader::costRows([$vm], $grid, 'EUR', 'Lab', 7, '2026-12')['rows'];
        $this->assertSame($rows[0]['source_key'], $again[0]['source_key']);
        $this->assertNotSame($rows[0]['source_key'], $next[0]['source_key']);
        $this->assertStringContainsString('"vcpu":"1"', $rows[0]['tags']);
    }
}
