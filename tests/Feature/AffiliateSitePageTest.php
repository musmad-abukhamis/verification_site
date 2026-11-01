<?php

namespace Tests\Feature;

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

    public function test_the_page_shows_the_fee_and_configured_whatsapp_group(): void
    {
        config()->set('services.affiliate_site.whatsapp_group_url', 'https://chat.whatsapp.com/example');

        $response = $this->actingAs(User::factory()->create())
            ->get(route('affiliate-site.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('AffiliateSite/Index')
            ->where('fee', 100000)
            ->where('whatsappGroupUrl', 'https://chat.whatsapp.com/example')
        );
    }
}
