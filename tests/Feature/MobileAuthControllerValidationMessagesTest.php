<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The live Flutter app (sisda_app/) hits these two endpoints. Both used
 * bare $request->validate([...]) with no message map, so Laravel fell
 * back to its English defaults (config('app.locale') is 'en' and there is
 * no lang/ directory) — showing e.g. "The name field is required." in a
 * Bahasa Melayu-only app. These tests assert the literal BM strings, not
 * assertJsonStructure, because a structure-only check passes on English
 * defaults just as well and would not have caught this bug.
 */
class MobileAuthControllerValidationMessagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_missing_fields_returns_bm_messages(): void
    {
        $response = $this->postJson('/api/mobile/login', []);

        $response->assertStatus(422);
        // Missing-field validation throws before login()'s own manual
        // {"success": false, ...} responses run, so this is Laravel's
        // built-in ValidationException envelope ({"message", "errors"}) —
        // only the per-field rule messages are in scope here.
        $response->assertJson([
            'errors' => [
                'telephone' => ['Nombor telefon diperlukan.'],
                'password' => ['Kata laluan diperlukan.'],
            ],
        ]);

        $this->assertStringNotContainsString('field is required', json_encode($response->json()));
    }

    public function test_register_missing_fields_returns_bm_messages(): void
    {
        $response = $this->postJson('/api/mobile/register', []);

        $response->assertStatus(422);
        // The generic wrapper ("message": "The given data was invalid.") is
        // Laravel's built-in ValidationException envelope, untouched by this
        // fix — only the per-field rule messages are in scope here.
        $response->assertJson([
            'errors' => [
                'name' => ['Nama diperlukan.'],
                'telephone' => ['Nombor telefon diperlukan.'],
                'password' => ['Kata laluan diperlukan.'],
                'negeri_id' => ['Sila pilih negeri.'],
                'bandar_id' => ['Sila pilih bandar/parlimen.'],
                'kadun_id' => ['Sila pilih DUN.'],
            ],
        ]);

        $this->assertStringNotContainsString('field is required', json_encode($response->json()));
    }

    public function test_register_invalid_exists_ids_returns_bm_messages(): void
    {
        $response = $this->postJson('/api/mobile/register', [
            'name' => 'Ahmad',
            'telephone' => '0123456789',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'negeri_id' => 999999,
            'bandar_id' => 999999,
            'kadun_id' => 999999,
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'errors' => [
                'negeri_id' => ['Negeri yang dipilih tidak sah.'],
                'bandar_id' => ['Bandar/Parlimen yang dipilih tidak sah.'],
                'kadun_id' => ['DUN yang dipilih tidak sah.'],
            ],
        ]);

        $this->assertStringNotContainsString('is invalid', json_encode($response->json()));
    }

    public function test_register_duplicate_telephone_returns_bm_message(): void
    {
        User::factory()->create([
            'telephone' => '0123456789',
            'role' => 'user',
            'status' => 'approved',
        ]);

        $response = $this->postJson('/api/mobile/register', [
            'name' => 'Ahmad',
            'telephone' => '0123456789',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'negeri_id' => 1,
            'bandar_id' => 1,
            'kadun_id' => 1,
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'errors' => [
                'telephone' => ['Nombor telefon ini telah didaftarkan.'],
            ],
        ]);

        $this->assertStringNotContainsString('has already been taken', json_encode($response->json()));
    }
}
