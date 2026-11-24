<?php

namespace Tests\Feature;

use App\Models\Machine;
use App\Models\ProductionRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_success_returns_token_and_user(): void
    {
        User::factory()->create(['username' => 'budi', 'name' => 'Budi']);

        $res = $this->postJson('/api/auth/login', ['username' => 'BUDI', 'password' => 'Password123']);

        $res->assertOk()
            ->assertJsonPath('data.tokenType', 'Bearer')
            ->assertJsonPath('data.user.username', 'budi')
            ->assertJsonPath('data.user.nik', null)
            ->assertJsonPath('data.user.permissions.canManageUsers', false)
            ->assertJsonStructure(['data' => ['token', 'expiresAt', 'user' => ['id', 'nik', 'role', 'isActive']]]);

        $this->assertNotNull(User::first()->last_login_at);
    }

    public function test_wrong_password_returns_422_not_401(): void
    {
        User::factory()->create(['username' => 'budi']);

        $this->postJson('/api/auth/login', ['username' => 'budi', 'password' => 'salah'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('username');
    }

    public function test_inactive_user_cannot_login(): void
    {
        User::factory()->inactive()->create(['username' => 'budi']);

        $this->postJson('/api/auth/login', ['username' => 'budi', 'password' => 'Password123'])
            ->assertStatus(403);
    }

    public function test_login_is_rate_limited_after_five_failures(): void
    {
        User::factory()->create(['username' => 'budi']);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/login', ['username' => 'budi', 'password' => 'salah'])->assertStatus(422);
        }

        $this->postJson('/api/auth/login', ['username' => 'budi', 'password' => 'Password123'])
            ->assertStatus(429);
    }

    public function test_protected_endpoints_require_token(): void
    {
        $this->getJson('/api/meta')->assertStatus(401);
        $this->getJson('/api/dashboard')->assertStatus(401);
        $this->getJson('/api/auth/me')->assertStatus(401);
    }

    public function test_token_works_and_logout_revokes_it(): void
    {
        User::factory()->create(['username' => 'budi']);

        $token = $this->postJson('/api/auth/login', ['username' => 'budi', 'password' => 'Password123'])
            ->json('data.token');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/auth/me')->assertOk()->assertJsonPath('data.username', 'budi');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/auth/logout')->assertNoContent();

        $this->app['auth']->forgetGuards();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/auth/me')->assertStatus(401);
    }

    public function test_operator_cannot_manage_users_or_delete_data(): void
    {
        $machine = Machine::create(['name' => 'BP-450']);
        $record = ProductionRecord::create([
            'date' => '2026-11-24', 'shift' => 1, 'machine_id' => $machine->id, 'part_number' => 'PN-1',
            'start_time' => '07:00:00', 'end_time' => '08:00:00', 'output' => 100, 'pic' => 'Budi',
        ]);

        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/users')->assertStatus(403);
        $this->deleteJson("/api/production-records/{$record->id}")->assertStatus(403);
        $this->putJson('/api/master-productions/BP-450', ['partNumber' => 'X'])->assertStatus(403);
        $this->getJson('/api/meta')->assertOk();
        $this->assertDatabaseHas('production_records', ['id' => $record->id]);

        Sanctum::actingAs(User::factory()->admin()->create());
        $this->deleteJson("/api/production-records/{$record->id}")->assertNoContent();
    }

    public function test_admin_creates_user_with_optional_nik_and_nik_is_locked_once_set(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $created = $this->postJson('/api/users', [
            'name' => 'Budi', 'username' => 'Budi', 'role' => 'operator', 'password' => 'Operator123',
        ])->assertCreated()->assertJsonPath('data.nik', null)->assertJsonPath('data.username', 'budi');

        $id = $created->json('data.id');
        $this->assertNotSame('Operator123', User::find($id)->password); // ter-hash, bukan plaintext

        $payload = ['name' => 'Budi', 'username' => 'budi', 'role' => 'operator', 'isActive' => true];

        $this->putJson("/api/users/{$id}", $payload + ['nik' => 'yfi-0001'])
            ->assertOk()->assertJsonPath('data.nik', 'YFI-0001');

        $this->putJson("/api/users/{$id}", $payload + ['nik' => 'YFI-9999'])->assertStatus(422);

        // NIK tidak ikut terhapus bila request tidak mengirim nik
        $this->putJson("/api/users/{$id}", $payload)->assertOk()->assertJsonPath('data.nik', 'YFI-0001');
    }

    public function test_nik_must_be_unique_but_many_nulls_allowed(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        foreach (['user-a', 'user-b', 'user-c'] as $u) {
            $this->postJson('/api/users', ['name' => $u, 'username' => $u, 'role' => 'operator', 'password' => 'Operator123'])
                ->assertCreated();
        }

        $this->postJson('/api/users', ['name' => 'x', 'username' => 'user-x', 'role' => 'operator', 'password' => 'Operator123', 'nik' => 'N-1'])->assertCreated();
        $this->postJson('/api/users', ['name' => 'y', 'username' => 'user-y', 'role' => 'operator', 'password' => 'Operator123', 'nik' => 'n-1'])
            ->assertStatus(422)->assertJsonValidationErrors('nik');
    }

    public function test_admin_cannot_demote_deactivate_or_delete_self(): void
    {
        $admin = User::factory()->admin()->create(['username' => 'boss']);
        Sanctum::actingAs($admin);

        $this->putJson("/api/users/{$admin->id}", ['name' => 'B', 'username' => 'boss', 'role' => 'operator', 'isActive' => true])->assertStatus(422);
        $this->putJson("/api/users/{$admin->id}", ['name' => 'B', 'username' => 'boss', 'role' => 'admin', 'isActive' => false])->assertStatus(422);
        $this->deleteJson("/api/users/{$admin->id}")->assertStatus(422);
    }

    public function test_deactivating_a_user_revokes_their_tokens(): void
    {
        $target = User::factory()->create(['username' => 'budi']);
        $target->createToken('web');

        Sanctum::actingAs(User::factory()->admin()->create());

        $this->putJson("/api/users/{$target->id}", ['name' => 'B', 'username' => 'budi', 'role' => 'operator', 'isActive' => false])->assertOk();

        $this->assertSame(0, $target->tokens()->count());
    }

    public function test_change_password(): void
    {
        $user = User::factory()->create(['username' => 'budi']);
        Sanctum::actingAs($user);

        $this->putJson('/api/auth/password', [
            'currentPassword' => 'salah', 'newPassword' => 'Baru12345', 'newPasswordConfirmation' => 'Baru12345',
        ])->assertStatus(422)->assertJsonValidationErrors('currentPassword');

        $this->putJson('/api/auth/password', [
            'currentPassword' => 'Password123', 'newPassword' => 'Baru12345', 'newPasswordConfirmation' => 'Baru12345',
        ])->assertOk();

        $this->postJson('/api/auth/login', ['username' => 'budi', 'password' => 'Baru12345'])->assertOk();
    }

    public function test_user_search_by_name_username_and_nik(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create(['name' => 'Zed Admin', 'username' => 'zed']));
        User::factory()->create(['name' => 'Siti Aminah', 'username' => 'siti', 'nik' => 'YFI-777']);

        $this->getJson('/api/users?search=yfi-777')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/users?search=AMINAH')->assertOk()->assertJsonCount(1, 'data');
    }
}