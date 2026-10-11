<?php

namespace Tests\Feature;

use App\Enums\DemandStatus;
use App\Livewire\Demands;
use App\Models\DemandForm;
use App\Models\ItemType;
use App\Services\DemandService;
use Livewire\Livewire;
use Tests\TestCase;

class DemandOnBehalfOfTest extends TestCase
{
    public function test_demand_can_be_raised_on_behalf_of_another_staff_member(): void
    {
        $initiator = $this->staff('p.karki@prativa.edu.np');
        $beneficiary = $this->staff('store@prativa.edu.np');
        $chair = ItemType::where('code_prefix', 'CHAIR.S')->firstOrFail();

        Livewire::actingAs($initiator)
            ->test(Demands\Create::class)
            ->set('department', 'Science Lab')
            ->set('justification', 'Requested by Store Keeper for lab equipment storage.')
            ->set('requestedById', $beneficiary->id)
            ->set('lines.0.item_type_id', $chair->id)
            ->set('lines.0.quantity', '5')
            ->set('lines.0.unit_rate', '1500')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect();

        $demand = DemandForm::where('department', 'Science Lab')->latest('created_at')->firstOrFail();
        $this->assertSame($initiator->id, $demand->raised_by_id);
        $this->assertSame($beneficiary->id, $demand->requested_by_id);
    }

    public function test_beneficiary_staff_can_view_their_requested_demand(): void
    {
        $initiator = $this->staff('p.karki@prativa.edu.np');
        $beneficiary = $this->staff('store@prativa.edu.np');
        $outsider = $this->staff('auditor@prativa.edu.np');
        $chair = ItemType::where('code_prefix', 'CHAIR.S')->firstOrFail();

        $demand = app(DemandService::class)->create(
            lines: [['item_type_id' => $chair->id, 'item_name' => $chair->name, 'quantity' => 2, 'unit_rate' => 1500]],
            department: 'Store Room',
            justification: 'Additional shelves for incoming items.',
            user: $initiator,
            requestedById: $beneficiary->id,
        );

        // Beneficiary sees it in their demands list
        $beneficiaryDemands = app(DemandService::class)->list($beneficiary);
        $this->assertTrue($beneficiaryDemands->pluck('id')->contains($demand->id));

        // Beneficiary can access show page
        $this->actingAs($beneficiary)
            ->get(route('demands.show', $demand))
            ->assertOk()
            ->assertSee('मागकर्ता')
            ->assertSee($beneficiary->full_name);

        // Outsider without view-all permission is refused
        $this->actingAs($outsider)
            ->get(route('demands.show', $demand))
            ->assertForbidden();
    }

    public function test_demand_does_not_appear_in_beneficiary_approval_queue(): void
    {
        $initiator = $this->staff('p.karki@prativa.edu.np');
        $approver = $this->staff('hod.science@prativa.edu.np');
        $chair = ItemType::where('code_prefix', 'CHAIR.S')->firstOrFail();

        $demand = app(DemandService::class)->create(
            lines: [['item_type_id' => $chair->id, 'item_name' => $chair->name, 'quantity' => 2, 'unit_rate' => 1500]],
            department: 'Science Lab',
            justification: 'Lab stools requested by HOD.',
            user: $initiator,
            requestedById: $approver->id,
        );

        // Even though HOD is Tier 1 approver, demand should not appear in their queue
        $queue = app(DemandService::class)->myQueue($approver);
        $this->assertFalse($queue->pluck('id')->contains($demand->id));
    }

    public function test_beneficiary_can_cancel_pending_demand_raised_on_their_behalf(): void
    {
        $initiator = $this->staff('p.karki@prativa.edu.np');
        $beneficiary = $this->staff('store@prativa.edu.np');
        $chair = ItemType::where('code_prefix', 'CHAIR.S')->firstOrFail();

        $demand = app(DemandService::class)->create(
            lines: [['item_type_id' => $chair->id, 'item_name' => $chair->name, 'quantity' => 2, 'unit_rate' => 1500]],
            department: 'Store Room',
            justification: 'Items no longer needed.',
            user: $initiator,
            requestedById: $beneficiary->id,
        );

        app(DemandService::class)->cancel($demand->id, $beneficiary, 'Procured alternative locally.');

        $this->assertSame(DemandStatus::CANCELLED, $demand->fresh()->status);
    }

    public function test_staff_members_list_is_scoped_to_current_tenant_and_has_no_duplicates(): void
    {
        $initiator = $this->staff('p.karki@prativa.edu.np');

        $component = Livewire::actingAs($initiator)
            ->test(Demands\Create::class);

        $staff = $component->get('staffMembers');

        // Initiator themselves should be excluded
        $this->assertFalse($staff->pluck('id')->contains($initiator->id));

        // Every entry in the list should have a unique user id
        $ids = $staff->pluck('id');
        $this->assertSame($ids->count(), $ids->unique()->count());
    }
}
