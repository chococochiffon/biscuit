<?php

namespace Tests\Feature;

use App\Enums\AdministratorRole;
use App\Models\Administrator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdministratorControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_from_administrator_pages(): void
    {
        $response = $this->get(route('admin.index'));

        $response->assertRedirect(route('admin.login'));
    }

    public function test_index_displays_administrators(): void
    {
        $actor = Administrator::factory()->create();
        $other = Administrator::factory()->create(['name' => 'Jane Doe']);

        $response = $this->actingAs($actor, 'admin')->get(route('admin.index'));

        $response->assertOk();
        $response->assertSee($other->name);
    }

    public function test_create_screen_can_be_rendered(): void
    {
        $actor = Administrator::factory()->create();

        $response = $this->actingAs($actor, 'admin')->get(route('admin.create'));

        $response->assertOk();
    }

    public function test_store_creates_administrator_with_valid_data(): void
    {
        $actor = Administrator::factory()->create();

        $response = $this->actingAs($actor, 'admin')->post(route('admin.store'), [
            'name' => 'New Administrator',
            'email' => 'new-administrator@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => AdministratorRole::Admin->value,
        ]);

        $response->assertRedirect(route('admin.index'));

        $administrator = Administrator::where('email', 'new-administrator@example.com')->firstOrFail();
        $this->assertTrue(Hash::check('password123', $administrator->password));
        $this->assertSame(AdministratorRole::Admin, $administrator->role);
    }

    public function test_store_fails_validation_with_missing_fields(): void
    {
        $actor = Administrator::factory()->create();

        $response = $this->actingAs($actor, 'admin')->post(route('admin.store'), []);

        $response->assertSessionHasErrors(['name', 'email', 'password', 'role']);
    }

    public function test_store_fails_when_email_already_exists(): void
    {
        $actor = Administrator::factory()->create();
        $existing = Administrator::factory()->create();

        $response = $this->actingAs($actor, 'admin')->post(route('admin.store'), [
            'name' => 'New Administrator',
            'email' => $existing->email,
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => AdministratorRole::Admin->value,
        ]);

        $response->assertSessionHasErrors('email');
    }

    public function test_store_succeeds_with_email_of_soft_deleted_administrator(): void
    {
        $actor = Administrator::factory()->create();
        $deleted = Administrator::factory()->create(['email' => 'reused@example.com']);
        $deleted->delete();

        $response = $this->actingAs($actor, 'admin')->post(route('admin.store'), [
            'name' => 'New Administrator',
            'email' => 'reused@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => AdministratorRole::Admin->value,
        ]);

        $response->assertRedirect(route('admin.index'));
        $this->assertDatabaseHas('administrators', [
            'id' => $deleted->id,
            'email' => 'reused@example.com',
        ]);
        $this->assertDatabaseHas('administrators', [
            'email' => 'reused@example.com',
            'deleted_at' => null,
        ]);
    }

    public function test_show_displays_administrator(): void
    {
        $actor = Administrator::factory()->create();
        $target = Administrator::factory()->create();

        $response = $this->actingAs($actor, 'admin')->get(route('admin.show', $target));

        $response->assertOk();
        $response->assertSee($target->email);
    }

    public function test_edit_screen_can_be_rendered(): void
    {
        $actor = Administrator::factory()->create();
        $target = Administrator::factory()->create();

        $response = $this->actingAs($actor, 'admin')->get(route('admin.edit', $target));

        $response->assertOk();
    }

    public function test_update_modifies_administrator_without_changing_password(): void
    {
        $actor = Administrator::factory()->create();
        $target = Administrator::factory()->create([
            'password' => Hash::make('original-password'),
        ]);

        $response = $this->actingAs($actor, 'admin')->put(route('admin.update', $target), [
            'name' => 'Updated Name',
            'email' => $target->email,
            'role' => AdministratorRole::SuperAdmin->value,
        ]);

        $response->assertRedirect(route('admin.index'));

        $target->refresh();
        $this->assertSame('Updated Name', $target->name);
        $this->assertSame(AdministratorRole::SuperAdmin, $target->role);
        $this->assertTrue(Hash::check('original-password', $target->password));
    }

    public function test_update_changes_password_when_provided(): void
    {
        $actor = Administrator::factory()->create();
        $target = Administrator::factory()->create([
            'password' => Hash::make('original-password'),
        ]);

        $this->actingAs($actor, 'admin')->put(route('admin.update', $target), [
            'name' => $target->name,
            'email' => $target->email,
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
            'role' => $target->role->value,
        ]);

        $this->assertTrue(Hash::check('new-password', $target->fresh()->password));
    }

    public function test_update_fails_when_email_belongs_to_another_administrator(): void
    {
        $actor = Administrator::factory()->create();
        $target = Administrator::factory()->create();
        $other = Administrator::factory()->create();

        $response = $this->actingAs($actor, 'admin')->put(route('admin.update', $target), [
            'name' => $target->name,
            'email' => $other->email,
            'role' => $target->role->value,
        ]);

        $response->assertSessionHasErrors('email');
    }

    public function test_destroy_deletes_administrator(): void
    {
        $actor = Administrator::factory()->create();
        $target = Administrator::factory()->create();

        $response = $this->actingAs($actor, 'admin')->delete(route('admin.destroy', $target));

        $response->assertRedirect(route('admin.index'));
        $this->assertSoftDeleted('administrators', ['id' => $target->id]);
    }
}
