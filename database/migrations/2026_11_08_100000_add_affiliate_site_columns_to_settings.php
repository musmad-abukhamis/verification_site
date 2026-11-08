<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The "Own a site like ours" offer moves from code and `.env` to Site Settings,
 * so an admin can change it without a deploy.
 *
 * Whole naira only: the page has never shown kobo for this fee.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->unsignedInteger('affiliate_site_fee')->default(100000);
            $table->string('affiliate_whatsapp_group_url')->nullable();
        });

        // The deploy runs this before re-caching config, so a cached config
        // still holds the old value; without a cache, env() is the one that does.
        $url = config('services.affiliate_site.whatsapp_group_url') ?: env('AFFILIATE_WHATSAPP_GROUP_URL');

        if ($url) {
            $settings = Setting::query()->orderBy('id')->first() ?? new Setting;
            $settings->affiliate_whatsapp_group_url = $url;
            $settings->save();
        }
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn(['affiliate_site_fee', 'affiliate_whatsapp_group_url']);
        });
    }
};
