<?php

namespace Tests\Unit;

use App\Models\StaffShiftAssignment;
use PHPUnit\Framework\TestCase;

class StaffShiftAssignmentTest extends TestCase
{
    public function test_week_start_of_midweek_date_is_its_sunday(): void
    {
        $this->assertSame('2026-10-11', StaffShiftAssignment::weekStartOf('2026-10-14'));
    }

    public function test_week_start_of_sunday_is_itself(): void
    {
        $this->assertSame('2026-10-11', StaffShiftAssignment::weekStartOf('2026-10-11'));
    }

    public function test_week_start_of_saturday_is_its_sunday(): void
    {
        $this->assertSame('2026-10-11', StaffShiftAssignment::weekStartOf('2026-10-17'));
    }
}
