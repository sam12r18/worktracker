<?php

namespace Tests\Unit;

use App\Models\WorkTrackerAuditLog;
use PHPUnit\Framework\TestCase;

class WorkTrackerAuditLogModelTest extends TestCase
{
    public function test_model_uses_the_migrated_table_name(): void
    {
        $this->assertSame('worktracker_audit_logs', (new WorkTrackerAuditLog())->getTable());
    }
}
