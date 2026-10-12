<?php

namespace Tests\Feature;

use App\Enums\ApprovalAction;
use App\Enums\DemandApprovalMode;
use App\Enums\DemandStatus;
use App\Livewire\Setup\Settings;
use App\Models\ItemType;
use App\Services\DemandService;
use App\Services\SettingService;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Livewire;
use Tests\TestCase;

class DemandApprovalRoutingModeTest extends TestCase
{
    public function test_default_mode_is_sequential_and_climbs_step_by_step(): void
    {
        $chair = ItemType::where('code_prefix', 'CHAIR.S')->firstOrFail();
        $demands = app(DemandService::class);
        $settings = app(SettingService::class);

        $this->assertSame(DemandApprovalMode::SEQUENTIAL, $settings->demandApprovalMode());

        // Create demand for 40 chairs (Rs 60,000 -> reaches Tier 3: MD)
        $demand = $demands->create(
            lines: [['item_type_id' => $chair->id, 'item_name' => $chair->name, 'quantity' => 40, 'unit_rate' => 1500]],
            department: 'Administration',
            justification: 'Replacing chairs for classes.',
            user: $this->staff('p.karki@prativa.edu.np'),
        );

        $this->assertSame(1, $demand->current_tier);
        $this->assertSame(3, $demand->final_tier);

        // Tier 1 approves -> moves to tier 2
        $demands->decide($demand->id, ApprovalAction::APPROVE, $this->staff('hod.science@prativa.edu.np'));
        $this->assertSame(2, $demand->fresh()->current_tier);
    }

    public function test_direct_mode_routes_immediately_to_final_qualifying_tier(): void
    {
        $settings = app(SettingService::class);
        $settings->set(SettingService::DEMAND_APPROVAL_MODE, DemandApprovalMode::DIRECT->value, $this->staff('md@prativa.edu.np'));

        $chair = ItemType::where('code_prefix', 'CHAIR.S')->firstOrFail();
        $demands = app(DemandService::class);

        // Demand for 40 chairs (reaches Tier 3: MD)
        $demand = $demands->create(
            lines: [['item_type_id' => $chair->id, 'item_name' => $chair->name, 'quantity' => 40, 'unit_rate' => 1500]],
            department: 'Grade 8',
            justification: 'New session classroom chairs.',
            user: $this->staff('p.karki@prativa.edu.np'),
        );

        // It routes immediately to Tier 3
        $this->assertSame(3, $demand->current_tier);
        $this->assertSame(3, $demand->final_tier);

        // Appears in MD's queue (Tier 3), NOT in HOD's queue (Tier 1)
        $hodQueue = $demands->myQueue($this->staff('hod.science@prativa.edu.np'));
        $mdQueue = $demands->myQueue($this->staff('md@prativa.edu.np'));

        $this->assertFalse($hodQueue->pluck('id')->contains($demand->id));
        $this->assertTrue($mdQueue->pluck('id')->contains($demand->id));

        // MD approves with 1 click -> status is APPROVED immediately
        $demands->decide($demand->id, ApprovalAction::APPROVE, $this->staff('md@prativa.edu.np'));

        $fresh = $demand->fresh();
        $this->assertSame(DemandStatus::APPROVED, $fresh->status);
        $this->assertNull($fresh->current_tier);
        $this->assertCount(1, $fresh->approvals);
    }

    public function test_fast_track_mode_allows_higher_tier_to_approve_directly(): void
    {
        $settings = app(SettingService::class);
        $settings->set(SettingService::DEMAND_APPROVAL_MODE, DemandApprovalMode::FAST_TRACK->value, $this->staff('md@prativa.edu.np'));

        $chair = ItemType::where('code_prefix', 'CHAIR.S')->firstOrFail();
        $demands = app(DemandService::class);

        // Demand reaches Tier 3, but starts at Tier 1
        $demand = $demands->create(
            lines: [['item_type_id' => $chair->id, 'item_name' => $chair->name, 'quantity' => 40, 'unit_rate' => 1500]],
            department: 'Grade 8',
            justification: 'Urgent chairs needed for inspection.',
            user: $this->staff('p.karki@prativa.edu.np'),
        );

        $this->assertSame(1, $demand->current_tier);
        $this->assertSame(3, $demand->final_tier);

        // MD (Tier 3) can fast-track approve directly without waiting for Tier 1 or 2
        $demands->decide($demand->id, ApprovalAction::APPROVE, $this->staff('md@prativa.edu.np'));

        $fresh = $demand->fresh();
        $this->assertSame(DemandStatus::APPROVED, $fresh->status);
        $this->assertNull($fresh->current_tier);
        $this->assertCount(1, $fresh->approvals);
        $this->assertSame(3, $fresh->approvals->first()->tier_no);

        $this->assertDatabaseHas('audit_log', [
            'action' => 'DEMAND_APPROVED',
            'entity_id' => $demand->id,
        ]);
    }

    public function test_anti_self_approval_still_enforced_in_direct_mode(): void
    {
        $settings = app(SettingService::class);
        $settings->set(SettingService::DEMAND_APPROVAL_MODE, DemandApprovalMode::DIRECT->value, $this->staff('md@prativa.edu.np'));

        $chair = ItemType::where('code_prefix', 'CHAIR.S')->firstOrFail();
        $demands = app(DemandService::class);
        $md = $this->staff('md@prativa.edu.np');

        // MD raises demand that reaches Tier 3
        $demand = $demands->create(
            lines: [['item_type_id' => $chair->id, 'item_name' => $chair->name, 'quantity' => 40, 'unit_rate' => 1500]],
            department: 'Administration',
            justification: 'Executive office chairs.',
            user: $md,
        );

        $this->assertSame(3, $demand->current_tier);

        // MD cannot self-approve
        $this->expectException(AuthorizationException::class);
        $demands->decide($demand->id, ApprovalAction::APPROVE, $md);
    }

    public function test_settings_screen_saves_demand_approval_mode(): void
    {
        Livewire::actingAs($this->staff('md@prativa.edu.np'))
            ->test(Settings::class)
            ->set('demandApprovalMode', DemandApprovalMode::DIRECT->value)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(
            DemandApprovalMode::DIRECT,
            app(SettingService::class)->demandApprovalMode()
        );
    }
}
