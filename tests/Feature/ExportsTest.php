<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExportsTest extends TestCase
{
    private function download(string $url, string $email = 'md@prativa.edu.np'): string
    {
        $response = $this->actingAs($this->staff($email))->get($url);

        $response->assertOk();
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        return $response->streamedContent();
    }

    /** A real .xlsx is a zip archive, so it starts with the PK signature. */
    private function assertIsXlsx(string $content): void
    {
        $this->assertGreaterThan(1000, strlen($content), 'The workbook came back suspiciously small.');
        $this->assertSame('PK', substr($content, 0, 2), 'That is not a real .xlsx file.');
    }

    public function test_the_stock_register_downloads_as_a_real_workbook(): void
    {
        $this->assertIsXlsx($this->download('/export/stock-register'));
    }

    public function test_the_unit_list_downloads(): void
    {
        $this->assertIsXlsx($this->download('/export/unit-list'));
    }

    public function test_the_procurement_register_downloads(): void
    {
        $this->assertIsXlsx($this->download('/export/procurement'));
    }

    public function test_the_petty_cash_register_downloads(): void
    {
        $this->assertIsXlsx($this->download('/export/petty-cash', 'accounts@prativa.edu.np'));
    }

    public function test_the_audit_trail_downloads(): void
    {
        $this->assertIsXlsx($this->download('/export/audit-trail'));
    }

    public function test_a_teacher_cannot_export_the_audit_trail(): void
    {
        $this->actingAs($this->staff('p.karki@prativa.edu.np'))
            ->get('/export/audit-trail')
            ->assertForbidden();
    }

    public function test_a_teacher_cannot_export_the_petty_cash_register(): void
    {
        $this->actingAs($this->staff('p.karki@prativa.edu.np'))
            ->get('/export/petty-cash')
            ->assertForbidden();
    }

    public function test_exporting_is_itself_recorded_in_the_trail(): void
    {
        $md = $this->staff('md@prativa.edu.np');

        $this->actingAs($md)->get('/export/stock-register')->streamedContent();

        $this->assertDatabaseHas('audit_log', [
            'action' => 'EXPORTED',
            'actor_id' => $md->id,
        ]);
    }
}
