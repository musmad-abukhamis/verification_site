<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\VerificationProviderRequest;
use App\Models\VerificationEndpoint;
use App\Models\VerificationProvider;
use App\Services\Verification\AuthStyle;
use App\Services\Verification\ProviderCaller;
use App\Services\Verification\ServiceCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Admin > Verification > Providers.
 *
 * Adds a provider from the browser: pick the auth (header) style, then one
 * endpoint per service with its path, body type and field mapping. No code and
 * no deploy — which is the whole point, since every provider in this market
 * spells the same request differently.
 *
 * Secrets are write-only: the form shows whether a credential is set, never its
 * value, and a field left blank on save keeps what is stored.
 */
class VerificationProviderController extends Controller
{
    private const BODY_TYPES = [
        ['value' => 'json', 'label' => 'JSON body (application/json)'],
        ['value' => 'form', 'label' => 'Form-encoded body'],
        ['value' => 'query', 'label' => 'Query string only'],
    ];

    public function index()
    {
        $providers = VerificationProvider::with('endpoints')
            ->orderBy('priority')
            ->orderBy('name')
            ->get()
            ->map(fn (VerificationProvider $p) => $this->present($p));

        return Inertia::render('Admin/Verification/Providers', [
            'providers' => $providers,
            'services' => ServiceCatalog::forFrontend(),
            'authStyles' => AuthStyle::forFrontend(),
            'bodyTypes' => self::BODY_TYPES,
        ]);
    }

    public function store(VerificationProviderRequest $request)
    {
        DB::transaction(function () use ($request) {
            $provider = VerificationProvider::create($this->providerAttributes($request));

            $this->syncEndpoints($provider, $request->input('endpoints', []));
        });

        return redirect()->route('admin.verification-providers.index')
            ->with('success', 'Provider created.');
    }

    public function update(VerificationProviderRequest $request, VerificationProvider $provider)
    {
        DB::transaction(function () use ($request, $provider) {
            $attributes = $this->providerAttributes($request);

            // Merge credentials rather than replace: only fields the admin
            // actually typed are overwritten, so masked secrets left untouched
            // keep their stored value.
            $credentials = (array) $provider->credentials;
            foreach ($request->input('credentials', []) as $key => $value) {
                if ($value !== null && $value !== '') {
                    $credentials[$key] = $value;
                }
            }
            $attributes['credentials'] = $credentials;

            $provider->update($attributes);

            $this->syncEndpoints($provider, $request->input('endpoints', []));
        });

        return redirect()->route('admin.verification-providers.index')
            ->with('success', 'Provider updated.');
    }

    public function toggle(VerificationProvider $provider)
    {
        $provider->update(['is_active' => ! $provider->is_active]);

        return back()->with('success', 'Provider '.($provider->is_active ? 'activated' : 'deactivated').'.');
    }

    public function destroy(VerificationProvider $provider)
    {
        $provider->delete(); // cascades endpoints and routes

        return redirect()->route('admin.verification-providers.index')
            ->with('success', 'Provider deleted.');
    }

    /**
     * Fire a real request at one provider and show what came back.
     *
     * This is the only way to be sure a field map is right before customers
     * hit it. It charges nobody and bypasses the routing chain — it calls the
     * chosen provider directly — but it IS a live call and the provider may
     * bill the account for it.
     */
    public function test(Request $request, VerificationProvider $provider, ProviderCaller $caller)
    {
        $validated = $request->validate([
            'service' => ['required', Rule::in(ServiceCatalog::keys())],
            'input' => ['array'],
            'input.*' => ['nullable', 'string', 'max:255'],
        ]);

        $endpoint = $provider->endpoints->firstWhere('service', $validated['service']);

        if (! $endpoint) {
            return back()->withErrors(['test' => 'This provider has no endpoint for that service.']);
        }

        if (! AuthStyle::isConfigured($provider->auth_type, (array) $provider->credentials)) {
            return back()->withErrors(['test' => 'Add the provider credentials before testing.']);
        }

        $input = array_filter(
            $validated['input'] ?? [],
            fn ($value) => $value !== null && $value !== '',
        );

        $call = $caller->call($provider, $endpoint, $input, 'TEST-'.now()->format('YmdHis'));

        $outcome = $call['outcome'];

        return back()->with('testResult', [
            'provider' => $provider->name,
            'service' => $validated['service'],
            'outcome' => $outcome->outcome,
            'http_status' => $outcome->httpStatus,
            'message' => $outcome->message,
            'duration_ms' => $call['duration_ms'],
            'request' => $call['request'],       // credentials already stripped
            'normalized' => $outcome->data,
            'raw' => $this->trimForDisplay($outcome->raw),
        ]);
    }

    /**
     * The provider row, minus credentials (handled separately on update).
     *
     * @return array<string, mixed>
     */
    private function providerAttributes(VerificationProviderRequest $request): array
    {
        $data = $request->validated();

        return [
            'name' => $data['name'],
            'slug' => $data['slug'],
            'base_url' => rtrim($data['base_url'], '/'),
            'auth_type' => $data['auth_type'],
            'auth_config' => array_filter(
                $request->input('auth_config', []),
                fn ($v) => $v !== null && $v !== '',
            ),
            'credentials' => array_filter(
                $request->input('credentials', []),
                fn ($v) => $v !== null && $v !== '',
            ),
            'extra_headers' => $this->pairsToMap($request->input('extra_headers', [])),
            'timeout_seconds' => (int) $data['timeout_seconds'],
            'priority' => (int) $data['priority'],
            'is_active' => $request->boolean('is_active'),
            'notes' => $data['notes'] ?? null,
        ];
    }

    /**
     * Replace the provider's endpoints with the submitted set.
     *
     * Rows are matched on `service` so an edit updates in place; services no
     * longer listed are removed, which also drops that provider out of the
     * routing chain for them.
     *
     * @param  array<int, array<string, mixed>>  $endpoints
     */
    private function syncEndpoints(VerificationProvider $provider, array $endpoints): void
    {
        $kept = [];

        foreach ($endpoints as $endpoint) {
            $service = $endpoint['service'] ?? null;

            if (! $service || ! ServiceCatalog::has($service)) {
                continue;
            }

            VerificationEndpoint::updateOrCreate(
                ['provider_id' => $provider->getKey(), 'service' => $service],
                [
                    'http_method' => strtoupper($endpoint['http_method'] ?? 'POST'),
                    'path' => $endpoint['path'] ?? '/',
                    'body_type' => $endpoint['body_type'] ?? 'json',
                    'field_map' => $this->buildFieldMap($endpoint['field_map'] ?? []),
                    'static_fields' => $this->pairsToMap($endpoint['static_fields'] ?? []),
                    'success_rule' => $this->buildSuccessRule($endpoint['success_rule'] ?? []),
                    'response_map' => $this->buildResponseMap($endpoint['response_map'] ?? []),
                    'is_active' => (bool) ($endpoint['is_active'] ?? true),
                ],
            );

            $kept[] = $service;
        }

        $provider->endpoints()->whereNotIn('service', $kept)->delete();
    }

    /**
     * UI rows → the field_map JSON the RequestBuilder reads.
     *
     * A row with only a field name collapses to the string shorthand; one with
     * a date format, a case transform or a value table keeps the object form.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    private function buildFieldMap(array $rows): array
    {
        $map = [];

        foreach ($rows as $row) {
            $input = trim((string) ($row['input'] ?? ''));
            $field = trim((string) ($row['field'] ?? ''));

            if ($input === '' || $field === '') {
                continue;
            }

            $spec = array_filter([
                'field' => $field,
                'format' => trim((string) ($row['format'] ?? '')) ?: null,
                'transform' => trim((string) ($row['transform'] ?? '')) ?: null,
                'values' => $this->parseValueTable((string) ($row['values'] ?? '')),
            ], fn ($v) => $v !== null && $v !== []);

            $map[$input] = array_keys($spec) === ['field'] ? $field : $spec;
        }

        return $map;
    }

    /**
     * "male=M, female=F" → ['male' => 'M', 'female' => 'F'].
     *
     * @return array<string, string>
     */
    private function parseValueTable(string $raw): array
    {
        $table = [];

        foreach (explode(',', $raw) as $pair) {
            if (! str_contains($pair, '=')) {
                continue;
            }

            [$from, $to] = array_map('trim', explode('=', $pair, 2));

            if ($from !== '') {
                $table[$from] = $to;
            }
        }

        return $table;
    }

    /**
     * @param  array<string, mixed>  $rule
     * @return array<string, mixed>
     */
    private function buildSuccessRule(array $rule): array
    {
        $accepted = array_values(array_filter(array_map(
            'trim',
            explode(',', (string) ($rule['in'] ?? '')),
        )));

        return array_filter([
            'path' => trim((string) ($rule['path'] ?? '')) ?: null,
            'in' => $accepted ?: null,
            'error_path' => trim((string) ($rule['error_path'] ?? '')) ?: null,
            'data_path' => trim((string) ($rule['data_path'] ?? '')) ?: null,
        ], fn ($v) => $v !== null);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, string>
     */
    private function buildResponseMap(array $rows): array
    {
        $map = [];

        foreach ($rows as $row) {
            $field = trim((string) ($row['field'] ?? ''));
            $path = trim((string) ($row['path'] ?? ''));

            if ($field !== '' && $path !== '') {
                $map[$field] = $path;
            }
        }

        return $map;
    }

    /**
     * @param  array<int, array<string, mixed>>  $pairs
     * @return array<string, string>
     */
    private function pairsToMap(array $pairs): array
    {
        $map = [];

        foreach ($pairs as $pair) {
            $key = trim((string) ($pair['key'] ?? ''));

            if ($key !== '') {
                $map[$key] = (string) ($pair['value'] ?? '');
            }
        }

        return $map;
    }

    /**
     * Shape a provider for the admin table and the edit form. Credentials are
     * reduced to set/not-set flags — the values never leave the server.
     *
     * @return array<string, mixed>
     */
    private function present(VerificationProvider $provider): array
    {
        return [
            'id' => $provider->getKey(),
            'name' => $provider->name,
            'slug' => $provider->slug,
            'base_url' => $provider->base_url,
            'auth_type' => $provider->auth_type,
            'auth_config' => (object) ($provider->auth_config ?? []),
            'credential_status' => (object) $provider->credentialStatus(),
            'extra_headers' => $this->mapToPairs((array) $provider->extra_headers),
            'timeout_seconds' => $provider->timeout_seconds,
            'priority' => $provider->priority,
            'is_active' => $provider->is_active,
            'is_usable' => $provider->isUsable(),
            'notes' => $provider->notes,
            'endpoints' => $provider->endpoints
                ->sortBy('service')
                ->values()
                ->map(fn (VerificationEndpoint $e) => [
                    'service' => $e->service,
                    'service_label' => ServiceCatalog::label($e->service),
                    'http_method' => $e->http_method,
                    'path' => $e->path,
                    'body_type' => $e->body_type,
                    'is_active' => $e->is_active,
                    'field_map' => $this->fieldMapToRows((array) $e->field_map),
                    'static_fields' => $this->mapToPairs((array) $e->static_fields),
                    'success_rule' => [
                        'path' => $e->success_rule['path'] ?? '',
                        'in' => implode(', ', (array) ($e->success_rule['in'] ?? [])),
                        'error_path' => $e->success_rule['error_path'] ?? '',
                        'data_path' => $e->success_rule['data_path'] ?? '',
                    ],
                    'response_map' => array_map(
                        fn ($field, $path) => ['field' => $field, 'path' => $path],
                        array_keys((array) $e->response_map),
                        array_values((array) $e->response_map),
                    ),
                ])->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $map
     * @return array<int, array{input: string, field: string, format: string, transform: string, values: string}>
     */
    private function fieldMapToRows(array $map): array
    {
        $rows = [];

        foreach ($map as $input => $spec) {
            if (is_string($spec)) {
                $spec = ['field' => $spec];
            }

            if (! is_array($spec)) {
                continue;
            }

            $values = (array) ($spec['values'] ?? []);

            $rows[] = [
                'input' => (string) $input,
                'field' => (string) ($spec['field'] ?? ''),
                'format' => (string) ($spec['format'] ?? ''),
                'transform' => (string) ($spec['transform'] ?? ''),
                'values' => implode(', ', array_map(
                    fn ($from, $to) => "{$from}={$to}",
                    array_keys($values),
                    array_values($values),
                )),
            ];
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $map
     * @return array<int, array{key: string, value: string}>
     */
    private function mapToPairs(array $map): array
    {
        return array_map(
            fn ($key, $value) => ['key' => (string) $key, 'value' => (string) $value],
            array_keys($map),
            array_values($map),
        );
    }

    /**
     * Keep the test panel readable — identity replies carry base64 photos that
     * would otherwise be megabytes of noise on screen.
     *
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    private function trimForDisplay(array $raw): array
    {
        $prune = function ($value) use (&$prune) {
            if (is_array($value)) {
                return array_map($prune, $value);
            }

            if (is_string($value) && strlen($value) > 300) {
                return substr($value, 0, 120).'... ['.strlen($value).' bytes]';
            }

            return $value;
        };

        return array_map($prune, $raw);
    }
}
