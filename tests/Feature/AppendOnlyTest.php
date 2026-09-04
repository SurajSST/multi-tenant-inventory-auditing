<?php

namespace Tests\Feature;

use App\Enums\ApprovalAction;
use App\Models\ItemType;
use App\Models\Location;
use App\Services\DemandService;
use App\Services\InventoryService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * History cannot be rewritten. The audit trail, the approval record and the
 * stock count ledger accept inserts and reads. Nothing else.
 */
class AppendOnlyTest extends TestCase
{
    public function test_the_audit_trail_refuses_an_update(): void
    {
        $this->staff('md@prativa.edu.np');
        DB::table('audit_log')->insert([
            'id' => (string) Str::uuid(),
            'action' => 'TEST', 'entity' => 'tests', 'detail' => 'original',
        ]);

        $this->expectException(QueryException::class);
        DB::table('audit_log')->where('action', 'TEST')->update(['detail' => 'tampered']);
    }

    public function test_the_audit_trail_refuses_a_delete(): void
    {
        DB::table('audit_log')->insert([
            'id' => (string) Str::uuid(),
            'action' => 'TEST', 'entity' => 'tests', 'detail' => 'original',
        ]);

        $this->expectException(QueryException::class);
        DB::table('audit_log')->where('action', 'TEST')->delete();
    }

    public function test_the_stock_ledger_refuses_an_update(): void
    {
        $this->expectException(QueryException::class);
        DB::table('stock_count_entries')->limit(1)->update(['quantity' => 9999]);
    }

    public function test_the_stock_ledger_refuses_a_delete(): void
    {
        $this->expectException(QueryException::class);
        DB::table('stock_count_entries')->limit(1)->delete();
    }

    public function test_an_approval_record_refuses_an_update(): void
    {
        $demands = app(DemandService::class);
        $chair = ItemType::where('code_prefix', 'CHAIR.S')->firstOrFail();

        $demand = $demands->create(
            lines: [['item_type_id' => $chair->id, 'item_name' => $chair->name, 'quantity' => 2, 'unit_rate' => 1500]],
            department: 'Grade 8',
            justification: 'Two replacement chairs for the classroom.',
            user: $this->staff('p.karki@prativa.edu.np'),
        );

        $demands->decide($demand->id, ApprovalAction::APPROVE, $this->staff('hod.science@prativa.edu.np'));

        $this->expectException(QueryException::class);
        DB::table('demand_approvals')->where('demand_id', $demand->id)->update(['action' => 'REJECT']);
    }

    public function test_a_correction_to_a_count_adds_a_row_rather_than_changing_one(): void
    {
        $inventory = app(InventoryService::class);
        $auditor = $this->staff('auditor@prativa.edu.np');
        $chair = ItemType::where('code_prefix', 'CHAIR.S')->firstOrFail();
        $blockA = Location::where('code', 'A')->firstOrFail();

        $before = $inventory->history($chair->id, $blockA->id)->count();
        $standing = $inventory->currentQuantity($chair->id, $blockA->id);

        $inventory->submitCount(
            [['item_type_id' => $chair->id, 'location_id' => $blockA->id, 'quantity' => 33]],
            $auditor,
        );
        $inventory->submitCount(
            [['item_type_id' => $chair->id, 'location_id' => $blockA->id, 'quantity' => 35]],
            $auditor,
        );

        $history = $inventory->history($chair->id, $blockA->id);

        $this->assertCount($before + 2, $history, 'A correction must add a row, never replace one.');
        $this->assertSame(35, $inventory->currentQuantity($chair->id, $blockA->id));

        // Each row carries the figure it replaced, so the trail reads on its own.
        $this->assertSame(33, $history->first()->previous_qty);
        $this->assertSame($standing, $history->get(1)->previous_qty);
    }

    public function test_an_unchanged_recount_writes_nothing(): void
    {
        $inventory = app(InventoryService::class);
        $auditor = $this->staff('auditor@prativa.edu.np');
        $chair = ItemType::where('code_prefix', 'CHAIR.S')->firstOrFail();
        $blockA = Location::where('code', 'A')->firstOrFail();

        $standing = $inventory->currentQuantity($chair->id, $blockA->id);

        $written = $inventory->submitCount(
            [['item_type_id' => $chair->id, 'location_id' => $blockA->id, 'quantity' => $standing]],
            $auditor,
        );

        $this->assertTrue($written->isEmpty());
    }
}
