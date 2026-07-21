<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Role is a commercial control, not only a permission one: it selects which
 * price a user pays (Plan::priceForRole) and gates the reseller API.
 */
class UserRoleTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => UserRole::ADMIN]);
    }

    public function test_an_admin_can_change_a_users_role(): void
    {
        $user = User::factory()->create(['role' => UserRole::USER]);

        $this->actingAs($this->admin())
            ->patch("/admin/users/{$user->id}/role", ['role' => 'AGENT'])
            ->assertSessionHasNoErrors();

        $this->assertSame(UserRole::AGENT, $user->fresh()->role);
    }

    /**
     * Demoting yourself locks you out of the page you are standing on.
     */
    public function test_an_admin_cannot_change_their_own_role(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->patch("/admin/users/{$admin->id}/role", ['role' => 'USER'])
            ->assertSessionHasErrors('role');

        $this->assertSame(UserRole::ADMIN, $admin->fresh()->role);
    }

    public function test_an_unknown_role_is_rejected(): void
    {
        $user = User::factory()->create(['role' => UserRole::USER]);

        $this->actingAs($this->admin())
            ->patch("/admin/users/{$user->id}/role", ['role' => 'SUPERUSER'])
            ->assertSessionHasErrors('role');

        $this->assertSame(UserRole::USER, $user->fresh()->role);
    }

    public function test_a_non_admin_cannot_change_roles(): void
    {
        $actor = User::factory()->create(['role' => UserRole::USER]);
        $target = User::factory()->create(['role' => UserRole::USER]);

        // AdminMiddleware bounces non-admins to the dashboard rather than 403.
        $this->actingAs($actor)
            ->patch("/admin/users/{$target->id}/role", ['role' => 'ADMIN'])
            ->assertRedirect(route('dashboard'));

        $this->assertSame(UserRole::USER, $target->fresh()->role);
    }

    public function test_the_list_can_be_filtered_by_role(): void
    {
        $api = User::factory()->create(['role' => UserRole::API]);
        $plain = User::factory()->create(['role' => UserRole::USER]);

        $users = $this->actingAs($this->admin())
            ->get('/admin/users?role=API')
            ->original->getData()['page']['props']['users']['data'];

        $ids = array_column($users, 'id');

        $this->assertContains($api->id, $ids);
        $this->assertNotContains($plain->id, $ids);
    }
}
