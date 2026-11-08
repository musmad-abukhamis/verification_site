<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AffiliateSitePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_page_requires_authentication(): void
    {
        $this->get(route('affiliate-site.index'))->assertRedirect(route('login'));
    }

    public function test_the_page_shows_the_fee_and_whatsapp_group_set_by_admin(): void
    {
        $this->actingAs($this->admin())
            ->put(route('admin.site-settings.update'), [
                'affiliate_site_fee' => 250000,
                'affiliate_whatsapp_group_url' => 'https://chat.whatsapp.com/example',
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs(User::factory()->create())
            ->get(route('affiliate-site.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('AffiliateSite/Index')
                ->where('fee', 250000)
                ->where('whatsappGroupUrl', 'https://chat.whatsapp.com/example')
            );
    }

    public function test_the_page_keeps_working_before_anything_is_configured(): void
    {
        $this->assertSame(0, Setting::count());

        $this->actingAs(User::factory()->create())
            ->get(route('affiliate-site.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('fee', Setting::DEFAULT_AFFILIATE_SITE_FEE)
                ->where('whatsappGroupUrl', null)
            );
    }

    public function test_the_admin_form_is_given_the_current_values(): void
    {
        Setting::create(['affiliate_site_fee' => 75000, 'affiliate_whatsapp_group_url' => 'https://chat.whatsapp.com/current']);

        $this->actingAs($this->admin())
            ->get(route('admin.site-settings.index'))
            ->assertInertia(fn ($page) => $page
                ->where('settings.affiliate_site_fee', 75000)
                ->where('settings.affiliate_whatsapp_group_url', 'https://chat.whatsapp.com/current')
            );
    }

    public function test_the_link_can_be_cleared(): void
    {
        Setting::create(['affiliate_whatsapp_group_url' => 'https://chat.whatsapp.com/old']);

        $this->actingAs($this->admin())
            ->put(route('admin.site-settings.update'), [
                'affiliate_site_fee' => 100000,
                'affiliate_whatsapp_group_url' => '',
            ])
            ->assertSessionHasNoErrors();

        $this->assertNull(Setting::first()->affiliate_whatsapp_group_url);
    }

    public function test_the_link_must_be_a_web_address_and_the_fee_positive(): void
    {
        $this->actingAs($this->admin())
            ->put(route('admin.site-settings.update'), [
                'affiliate_site_fee' => 0,
                'affiliate_whatsapp_group_url' => 'javascript:alert(1)',
            ])
            ->assertSessionHasErrors(['affiliate_site_fee', 'affiliate_whatsapp_group_url']);

        $this->assertSame(0, Setting::count());
    }

    public function test_non_admins_cannot_change_the_offer(): void
    {
        $this->actingAs(User::factory()->create())
            ->put(route('admin.site-settings.update'), [
                'affiliate_site_fee' => 1,
                'affiliate_whatsapp_group_url' => 'https://chat.whatsapp.com/nope',
            ]);

        $this->assertSame(0, Setting::count());
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->forceFill(['role' => UserRole::ADMIN])->save();

        return $admin;
    }
}
