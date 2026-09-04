<?php

namespace Tests\Feature;

use App\Enums\ApprovalAction;
use App\Enums\DemandStatus;
use App\Models\ItemType;
use App\Services\DemandService;
use App\Services\SettingService;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ApprovalLadderTest extends TestCase
{
    private function raise(int $quantity, int $rate, string $raiser = 'p.karki@prativa.edu.np')
    {
        $chair = ItemType::where('code_prefix', 'CHAIR.S')->firstOrFail();

        return app(DemandService::class)->create(
            lines: [[
                'item_type_id' => $chair->id,
                'item_name' => $chair->name,
                'quantity' => $quantity,
                'unit_rate' => $rate,
            ]],
            department: 'Grade 8',
            justification: 'Replacing broken classroom chairs before the new session.',
            user: $this->staff($raiser),
        );
    }

    public function test_a_small_request_closes_at_tier_one(): void
    {
        $demands = app(DemandService::class);

        $demand = $this->raise(2, 1500); // Rs. 3,000 — tier 1 band

        $this->assertSame(1, $demand->final_tier);
        $this->assertSame(1, $demand->current_tier);

        $demands->decide($demand->id, ApprovalAction::APPROVE, $this->staff('hod.science@prativa.edu.np'));

        $demand->refresh();
        $this->assertSame(DemandStatus::APPROVED, $demand->status);
        $this->assertNull($demand->current_tier);
    }

    public function test_a_sixty_thousand_rupee_request_climbs_three_tiers(): void
    {
        $demands = app(DemandService::class);

        $demand = $this->raise(40, 1500); // Rs. 60,000 — tier 3 band
        $this->assertSame(3, $demand->final_tier);
        $this->assertSame(1, $demand->current_tier, 'A form always enters at the bottom band.');

        $demands->decide($demand->id, ApprovalAction::APPROVE, $this->staff('hod.science@prativa.edu.np'));
        $this->assertSame(2, $demand->fresh()->current_tier);

        $demands->decide($demand->id, ApprovalAction::APPROVE, $this->staff('admin.officer@prativa.edu.np'));
        $this->assertSame(3, $demand->fresh()->current_tier);

        $demands->decide($demand->id, ApprovalAction::APPROVE, $this->staff('md@prativa.edu.np'));
        $this->assertSame(DemandStatus::APPROVED, $demand->fresh()->status);

        // Every signature on record, one per tier.
        $this->assertCount(3, $demand->fresh()->approvals);
    }

    public function test_the_committee_band_refuses_approval_without_a_minute_reference(): void
    {
        $demands = app(DemandService::class);

        $demand = $this->raise(200, 1500); // Rs. 300,000 — the committee band
        $this->assertSame(4, $demand->final_tier);

        foreach (['hod.science', 'admin.officer', 'md'] as $approver) {
            $demands->decide($demand->id, ApprovalAction::APPROVE, $this->staff($approver.'@prativa.edu.np'));
        }

        $this->assertSame(4, $demand->fresh()->current_tier);

        try {
            $demands->decide($demand->id, ApprovalAction::APPROVE, $this->staff('chairman@prativa.edu.np'));
            $this->fail('The committee band must require a minute reference.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('minute reference', $e->validator->errors()->first());
        }

        $demands->decide(
            $demand->id, ApprovalAction::APPROVE,
            $this->staff('chairman@prativa.edu.np'),
            minuteRef: 'Minute 2082/14',
        );

        $this->assertSame(DemandStatus::APPROVED, $demand->fresh()->status);
    }

    public function test_a_rejection_needs_a_written_reason_and_stops_the_form(): void
    {
        $demands = app(DemandService::class);
        $demand = $this->raise(40, 1500);
        $hod = $this->staff('hod.science@prativa.edu.np');

        try {
            $demands->decide($demand->id, ApprovalAction::REJECT, $hod, reason: 'no');
            $this->fail('A rejection must carry a reason.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('written reason', $e->validator->errors()->first());
        }

        $demands->decide($demand->id, ApprovalAction::REJECT, $hod,
            reason: 'The existing chairs were repaired last month. Re-raise in the next fiscal year.');

        $demand->refresh();
        $this->assertSame(DemandStatus::REJECTED, $demand->status);
        $this->assertNull($demand->current_tier);
    }

    public function test_a_value_below_the_floor_does_not_go_through_this_process(): void
    {
        $this->expectException(ValidationException::class);

        $this->raise(1, 50); // Rs. 50 — under the tier 1 floor of Rs. 100
    }

    public function test_the_ladder_refuses_a_gap_between_bands(): void
    {
        $settings = app(SettingService::class);

        $this->expectException(ValidationException::class);

        $settings->setTiers([
            ['tier_no' => 1, 'min_amount' => 100, 'max_amount' => 15000, 'decider_label' => 'Head of Department'],
            // A gap: 15,001 to 19,999 would be signed by nobody.
            ['tier_no' => 2, 'min_amount' => 20000, 'max_amount' => null, 'decider_label' => 'Managing Director'],
        ], $this->staff('md@prativa.edu.np'));
    }

    public function test_the_ladder_refuses_more_than_one_open_ended_band(): void
    {
        $settings = app(SettingService::class);

        $this->expectException(ValidationException::class);

        $settings->setTiers([
            ['tier_no' => 1, 'min_amount' => 100, 'max_amount' => null, 'decider_label' => 'Head of Department'],
            ['tier_no' => 2, 'min_amount' => 15001, 'max_amount' => null, 'decider_label' => 'Managing Director'],
        ], $this->staff('md@prativa.edu.np'));
    }

    public function test_a_valid_ladder_saves(): void
    {
        $settings = app(SettingService::class);

        $tiers = $settings->setTiers([
            ['tier_no' => 1, 'min_amount' => 100, 'max_amount' => 20000, 'decider_label' => 'Class Incharge'],
            ['tier_no' => 2, 'min_amount' => 20001, 'max_amount' => 200000, 'decider_label' => 'Managing Director'],
            ['tier_no' => 3, 'min_amount' => 200001, 'max_amount' => null, 'decider_label' => 'Chairman & Committee', 'requires_minute' => true],
        ], $this->staff('md@prativa.edu.np'));

        $this->assertCount(3, $tiers);
        $this->assertTrue($tiers->last()->requires_minute);
        $this->assertNull($tiers->last()->max_amount);
    }

    public function test_the_approval_queue_never_shows_a_person_their_own_request(): void
    {
        $demands = app(DemandService::class);
        $md = $this->staff('md@prativa.edu.np');

        // Raised by the MD, who also decides at tier 3.
        $chair = ItemType::where('code_prefix', 'CHAIR.S')->firstOrFail();
        $demands->create(
            lines: [['item_type_id' => $chair->id, 'item_name' => $chair->name, 'quantity' => 40, 'unit_rate' => 1500]],
            department: 'Administration',
            justification: 'Chairs for the administration block, replacing broken ones.',
            user: $md,
        );

        $this->assertTrue($demands->myQueue($md)->isEmpty());
    }
}
