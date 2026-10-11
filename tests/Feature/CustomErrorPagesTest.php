<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class CustomErrorPagesTest extends TestCase
{
    public function test_custom_404_page_renders_cleanly(): void
    {
        $response = $this->get('/route-that-does-not-exist-at-all');

        $response->assertNotFound();
        $response->assertSee('Page Not Found');
        $response->assertSee('HTTP 404');
        $response->assertSee('Prativa');
    }

    public function test_custom_403_page_renders_for_forbidden_actions(): void
    {
        $teacher = $this->staff('p.karki@prativa.edu.np');

        $response = $this->actingAs($teacher)->get('/setup');

        $response->assertForbidden();
        $response->assertSee('Access Restricted');
        $response->assertSee('HTTP 403');
        $response->assertSee('separation of duties', false);
    }

    public function test_custom_error_pages_render_without_syntax_errors(): void
    {
        Route::get('/test-error/{code}', function (int $code) {
            abort($code, 'Test error message for status '.$code);
        });

        foreach ([403, 404, 419, 500, 503, 429] as $code) {
            $response = $this->get('/test-error/'.$code);
            $response->assertStatus($code);
            $response->assertSee('HTTP '.$code);
        }
    }
}
