<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Livewire\Setup\Staff;
use App\Models\TenantUser;
use App\Models\User;
use App\Services\AuditLogger;
use Livewire\Livewire;
use Tests\TestCase;

class StaffManagementTest extends TestCase
{
    public function test_can_save_new_staff_member(): void
    {
        $admin = $this->staff('md@prativa.edu.np');
        $this->actingAs($admin);

        Livewire::test(Staff::class)
            ->call('newStaff')
            ->set('staffCode', 'TEST-999')
            ->set('fullName', 'John Doe')
            ->set('designationSelect', 'Teacher — Secondary')
            ->set('email', 'johndoe@prativa.edu.np')
            ->set('phone', '9800000000')
            ->set('roles', [Role::INITIATOR->value])
            ->set('approvalTier', 0)
            ->call('save')
            ->assertHasNoErrors();

        $user = User::where('email', 'johndoe@prativa.edu.np')->first();
        $this->assertNotNull($user);
        $this->assertSame('John Doe', $user->full_name);

        $posting = TenantUser::where('user_id', $user->id)->first();
        $this->assertNotNull($posting);
        $this->assertSame('TEST-999', $posting->staff_code);
    }

    public function test_can_edit_existing_staff_member_fields(): void
    {
        $admin = $this->staff('md@prativa.edu.np');
        $this->actingAs($admin);

        $target = TenantUser::where('staff_code', 'PSS-010')->firstOrFail();

        Livewire::test(Staff::class)
            ->call('edit', $target->id)
            ->set('fullName', 'P. Karki Updated')
            ->set('email', 'p.karki.new@prativa.edu.np')
            ->set('staffCode', 'PSS-010-NEW')
            ->set('approvalTier', 2)
            ->set('designationSelect', 'OTHER')
            ->set('customDesignation', 'Lead Coordinator')
            ->set('roles', [Role::INITIATOR->value, Role::APPROVER->value])
            ->call('save')
            ->assertHasNoErrors();

        $freshTarget = $target->fresh();
        $freshUser = $target->user->fresh();

        $this->assertSame('P. Karki Updated', $freshUser->full_name);
        $this->assertSame('p.karki.new@prativa.edu.np', $freshUser->email);
        $this->assertSame('PSS-010-NEW', $freshTarget->staff_code);
        $this->assertSame('Lead Coordinator', $freshTarget->designation);
        $this->assertSame(2, $freshTarget->approval_tier);
        $this->assertTrue($freshTarget->hasRole(Role::APPROVER));
    }

    public function test_cannot_add_duplicate_posting_for_same_email_at_school(): void
    {
        $admin = $this->staff('md@prativa.edu.np');
        $this->actingAs($admin);

        Livewire::test(Staff::class)
            ->call('newStaff')
            ->set('staffCode', 'DUP-001')
            ->set('fullName', 'Duplicate Person')
            ->set('designationSelect', 'Teacher — Secondary')
            ->set('email', 'p.karki@prativa.edu.np') // already at this school
            ->set('roles', [Role::INITIATOR->value])
            ->set('approvalTier', 0)
            ->call('save')
            ->assertHasErrors(['email']);
    }

    public function test_toggle_active_works_when_called_from_wire(): void
    {
        $admin = $this->staff('md@prativa.edu.np');
        $this->actingAs($admin);

        $target = TenantUser::where('staff_code', 'PSS-010')->firstOrFail();
        $this->assertTrue($target->is_active);

        Livewire::test(Staff::class)
            ->call('toggleActive', $target->id);

        $this->assertFalse($target->fresh()->is_active);

        // Reactivate
        Livewire::test(Staff::class)
            ->call('toggleActive', $target->id);

        $this->assertTrue($target->fresh()->is_active);
    }

    public function test_audit_log_timestamp_is_in_the_present_not_future(): void
    {
        $admin = $this->staff('md@prativa.edu.np');
        $this->actingAs($admin);

        $log = app(AuditLogger::class)->record(
            action: 'TEST_ACTION',
            entity: 'test',
            entityId: null,
            detail: 'Test action detail',
            actor: $admin,
        );

        $this->assertFalse($log->fresh()->at->isFuture());
        $this->assertStringNotContainsString('from now', $log->fresh()->at->diffForHumans());
    }
}
