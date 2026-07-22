<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Config-driven verification providers.
 *
 * Nigerian NIN/BVN providers all speak JSON over HTTPS but agree on nothing
 * else: the auth header may be `Authorization: Bearer`, `x-api-key`, a
 * key+secret pair, or the key may live in the body. The identifier field is
 * `nin`, `number`, `idValue` or `value` depending on who you ask, and the
 * response wraps the person's details in `data`, `user_data` or nothing at all
 * with a dozen spellings of "surname".
 *
 * Rather than a PHP class per provider (which is what App\Services\Nin\Providers
 * does and why adding one needs a deploy), a provider is a row here: an auth
 * style, plus one endpoint row per service describing its path, its field
 * mapping and how to read success out of the reply. Admin > Verification adds
 * one with no code.
 *
 * Routing mirrors the data module exactly: `verification_routes` is an ordered
 * priority list per service, position 1 is primary, 2+ are failover.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('verification_providers', function (Blueprint $table) {
            $table->string('id')->primary(); // cuid
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('base_url');
            // none | bearer | token | header_key | key_secret | basic | body_key | query_key
            $table->string('auth_type')->default('bearer');
            // Non-secret auth knobs: header names, body/query field names, token
            // prefix. Kept out of `credentials` so the UI can show them.
            $table->jsonb('auth_config')->nullable();
            $table->text('credentials')->nullable(); // encrypted JSON
            $table->jsonb('extra_headers')->nullable(); // static headers, e.g. Accept
            $table->smallInteger('timeout_seconds')->default(30);
            $table->boolean('is_active')->default(true);
            $table->smallInteger('priority')->default(100);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // One row per (provider, service). A provider that cannot do a service
        // simply has no row for it and is skipped when routing.
        Schema::create('verification_endpoints', function (Blueprint $table) {
            $table->id();
            $table->string('provider_id');
            $table->string('service'); // App\Services\Verification\ServiceCatalog keys
            $table->string('http_method')->default('POST');
            $table->string('path');                 // appended to the provider base_url
            $table->string('body_type')->default('json'); // json | form | query
            // canonical input name => provider field name (or {field, format,
            // transform, values} for dob/gender translation).
            $table->jsonb('field_map')->nullable();
            // Constants merged into every request, e.g. {"slipType":"standard"}.
            $table->jsonb('static_fields')->nullable();
            // How to read success out of the reply; null = built-in heuristic.
            $table->jsonb('success_rule')->nullable();
            // Optional overrides for fields the normalizer's alias table misses.
            $table->jsonb('response_map')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['provider_id', 'service']);
            $table->index('service');
            $table->foreign('provider_id')->references('id')->on('verification_providers')->cascadeOnDelete();
        });

        // Ordered failover chain per service. Position 1 is the primary.
        Schema::create('verification_routes', function (Blueprint $table) {
            $table->id();
            $table->string('service');
            $table->string('provider_id');
            $table->smallInteger('position');
            $table->timestamps();

            $table->unique(['service', 'position']);
            $table->index('service');
            $table->foreign('provider_id')->references('id')->on('verification_providers')->cascadeOnDelete();
        });

        // Audit of every hop, including the ones that failed over. Credentials
        // are stripped before the payload is written.
        Schema::create('verification_attempts', function (Blueprint $table) {
            $table->id();
            $table->string('service');
            $table->string('provider_id')->nullable(); // null once a provider is deleted
            $table->string('provider_name');           // denormalized, survives deletion
            $table->string('user_id')->nullable();
            $table->string('reference')->nullable();   // the caller's transaction reference
            $table->jsonb('request_payload')->nullable();
            $table->jsonb('response')->nullable();
            $table->string('outcome');                 // success | fail | timeout
            $table->smallInteger('http_status')->nullable();
            $table->integer('duration_ms')->nullable();
            $table->text('message')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['service', 'created_at']);
            $table->index(['provider_id', 'created_at']);
            $table->index('reference');
            $table->foreign('provider_id')->references('id')->on('verification_providers')->nullOnDelete();
        });

        // Failover knobs. Separate from data_settings so the two modules cannot
        // clobber each other's keys.
        Schema::create('verification_settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('verification_settings');
        Schema::dropIfExists('verification_attempts');
        Schema::dropIfExists('verification_routes');
        Schema::dropIfExists('verification_endpoints');
        Schema::dropIfExists('verification_providers');
    }
};
