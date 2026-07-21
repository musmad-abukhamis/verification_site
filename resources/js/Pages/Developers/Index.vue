<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    endpoint: String,
});

const sections = [
    { id: 'overview', title: 'Overview' },
    { id: 'auth', title: 'Authentication' },
    { id: 'errors', title: 'Errors & billing' },
    { id: 'balance', title: 'Check balance' },
    { id: 'services', title: 'List services & prices' },
    { id: 'nin', title: 'Verify a NIN' },
    { id: 'bvn', title: 'Verify a BVN' },
    { id: 'plans', title: 'Data plans' },
    { id: 'data', title: 'Buy data' },
    { id: 'recipes', title: 'Full example' },
];

const copied = ref(null);

const copy = async (text, key) => {
    await navigator.clipboard.writeText(text);
    copied.value = key;
    setTimeout(() => { copied.value = null; }, 1500);
};

const base = props.endpoint;

const snippets = {
    curlBalance: `curl ${base}/balance \\
  -H "Authorization: Bearer YOUR_API_TOKEN"`,

    curlNin: `curl -X POST ${base}/nin/verify \\
  -H "Authorization: Bearer YOUR_API_TOKEN" \\
  -H "Content-Type: application/json" \\
  -H "Accept: application/json" \\
  -d '{
    "method": "nin",
    "nin": "12345678901"
  }'`,

    curlNinPhone: `curl -X POST ${base}/nin/verify \\
  -H "Authorization: Bearer YOUR_API_TOKEN" \\
  -H "Content-Type: application/json" \\
  -d '{ "method": "phone", "phone": "08012345678" }'`,

    curlNinDemo: `curl -X POST ${base}/nin/verify \\
  -H "Authorization: Bearer YOUR_API_TOKEN" \\
  -H "Content-Type: application/json" \\
  -d '{
    "method": "demographic",
    "first_name": "John",
    "last_name": "Doe",
    "gender": "M",
    "date_of_birth": "1990-05-21"
  }'`,

    curlBvn: `curl -X POST ${base}/bvn/verify \\
  -H "Authorization: Bearer YOUR_API_TOKEN" \\
  -H "Content-Type: application/json" \\
  -d '{ "bvn": "22345678901", "slip_type": "premium" }'`,

    curlPlans: `curl ${base}/plans \\
  -H "Authorization: Bearer YOUR_API_TOKEN"`,

    curlData: `curl -X POST ${base}/data \\
  -H "Authorization: Bearer YOUR_API_TOKEN" \\
  -H "Content-Type: application/json" \\
  -d '{
    "network": 1,
    "plan_id": 12,
    "phone": "08012345678",
    "ref": "ORDER-4417"
  }'`,

    curlDataAlias: `# Already wired to another data API? Send its body as-is.
curl -X POST ${base}/data \\
  -H "Authorization: Bearer YOUR_API_TOKEN" \\
  -H "Content-Type: application/json" \\
  -d '{
    "network": 1,
    "mobile_number": "+2348012345678",
    "data_plan": 12,
    "Ported_number": true,
    "request-id": "Data_12345678900"
  }'`,

    php: `<?php

$response = Http::withToken(env('VERIFY_API_TOKEN'))
    ->acceptJson()
    ->post('${base}/nin/verify', [
        'method' => 'nin',
        'nin'    => $request->input('nin'),
    ]);

$body = $response->json();

if ($response->successful() && ($body['success'] ?? false)) {
    return $body['data'];          // verified identity fields
}

// 402 means YOUR wallet is empty, not the customer's problem.
if ($response->status() === 402) {
    Log::critical('Verification wallet needs funding');
}

return null;`,

    node: `const res = await fetch('${base}/nin/verify', {
  method: 'POST',
  headers: {
    Authorization: \`Bearer \${process.env.VERIFY_API_TOKEN}\`,
    'Content-Type': 'application/json',
    Accept: 'application/json',
  },
  body: JSON.stringify({ method: 'nin', nin }),
});

const body = await res.json();

if (!res.ok || !body.success) {
  throw new Error(body.error?.message ?? 'Verification failed');
}

return body.data;`,

    python: `import os, requests

res = requests.post(
    "${base}/nin/verify",
    headers={
        "Authorization": f"Bearer {os.environ['VERIFY_API_TOKEN']}",
        "Accept": "application/json",
    },
    json={"method": "nin", "nin": nin},
    timeout=40,
)

body = res.json()

if not res.ok or not body.get("success"):
    raise RuntimeError(body.get("error", {}).get("message", "Verification failed"))

return body["data"]`,
};

const ninSuccess = `{
  "success": true,
  "provider": "prembly",
  "method": "nin",
  "reference": "NIN_1753100000_4821",
  "data": {
    "nin": "12345678901",
    "firstname": "JOHN",
    "surname": "DOE",
    "validation_id": 1042
  }
}`;

const ninError = `{
  "success": false,
  "error": {
    "code": "insufficient_balance",
    "message": "Insufficient wallet balance. Please fund your wallet."
  }
}`;

const plansSuccess = `{
  "status": "success",
  "data": {
    "plans": [
      {
        "plan_id": 1,
        "network": "MTN",
        "network_id": 1,
        "type": "SME",
        "name": "1GB",
        "validity": "30 Days",
        "price": 600
      },
      {
        "plan_id": 12,
        "network": "AIRTEL",
        "network_id": 2,
        "type": "GIFTING",
        "name": "2GB",
        "validity": "30 Days",
        "price": 1150
      }
    ]
  }
}`;

const dataSuccess = `{
  "status": "success",
  "message": "You have gifted 1GB to 08031234567.",
  "request-id": "ORDER-4417",
  "transaction_status": "success",
  "network": "MTN",
  "amount": "600",
  "dataplan": "1GB",
  "plan_type": "SME",
  "phone_number": "08031234567",
  "oldbal": "5000",
  "newbal": 4400,
  "system": "API",
  "wallet_vending": "wallet",
  "response": "You have gifted 1GB to 08031234567.",
  "data": {
    "reference": "Data_1753100000_884213",
    "status": "success",
    "network": "mtn",
    "plan": "1GB",
    "phone": "08031234567",
    "amount": 600,
    "vendor_reference": "VA-99312",
    "created_at": "2026-07-21T11:04:22+00:00"
  }
}`;

const bvnSuccess = `{
  "status": "success",
  "reference": "Verify_1753100000_7734",
  "amount": 150,
  "data": {
    "bvn": "22345678901",
    "surname": "DOE",
    "firstname": "JOHN",
    "dob": "21-May-1990",
    "phone": "08012345678",
    "photo": "<base64 jpeg>"
  }
}`;
</script>

<template>
    <Head title="API Documentation" />

    <div class="min-h-screen bg-gray-50 dark:bg-slate-900">
        <!-- Top bar -->
        <header class="border-b border-gray-200 bg-white dark:border-gray-700 dark:bg-slate-800">
            <div class="mx-auto flex max-w-6xl items-center justify-between px-4 py-4 sm:px-6">
                <div>
                    <h1 class="text-lg font-bold text-gray-900 dark:text-white">API Documentation</h1>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Integrate identity verification and data top-ups into your own site</p>
                </div>
                <div class="flex items-center gap-4">
                    <Link :href="route('data-pricing')" class="text-sm font-medium text-indigo-600 hover:underline dark:text-indigo-400">
                        Data Pricing
                    </Link>
                    <Link :href="route('api-access.index')" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                        Get your token
                    </Link>
                </div>
            </div>
        </header>

        <div class="mx-auto flex max-w-6xl gap-8 px-4 py-8 sm:px-6">
            <!-- Sidebar -->
            <nav class="sticky top-8 hidden h-fit w-48 shrink-0 lg:block">
                <ul class="space-y-1 text-sm">
                    <li v-for="s in sections" :key="s.id">
                        <a :href="`#${s.id}`" class="block rounded px-3 py-1.5 text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white">
                            {{ s.title }}
                        </a>
                    </li>
                </ul>
            </nav>

            <main class="min-w-0 flex-1 space-y-10">
                <!-- Overview -->
                <section id="overview" class="scroll-mt-8">
                    <h2 class="text-xl font-bold text-gray-900 dark:text-white">Overview</h2>
                    <p class="mt-2 text-sm leading-relaxed text-gray-700 dark:text-gray-300">
                        A JSON HTTP API for reselling our services from your own website or app. Every call is billed to
                        your wallet at your account's rate, so you control what you charge your own customers.
                    </p>

                    <div class="mt-4 overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                        <table class="min-w-full text-sm">
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                <tr><td class="px-4 py-2 font-medium text-gray-900 dark:text-white">Base URL</td><td class="px-4 py-2"><code class="text-indigo-600 dark:text-indigo-400">{{ endpoint }}</code></td></tr>
                                <tr><td class="px-4 py-2 font-medium text-gray-900 dark:text-white">Format</td><td class="px-4 py-2 text-gray-700 dark:text-gray-300">JSON request and response bodies</td></tr>
                                <tr><td class="px-4 py-2 font-medium text-gray-900 dark:text-white">Auth</td><td class="px-4 py-2 text-gray-700 dark:text-gray-300">Bearer token</td></tr>
                                <tr><td class="px-4 py-2 font-medium text-gray-900 dark:text-white">Currency</td><td class="px-4 py-2 text-gray-700 dark:text-gray-300">NGN</td></tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-4 rounded-lg border-l-4 border-amber-400 bg-amber-50 p-4 dark:bg-amber-900/20">
                        <p class="text-sm text-amber-800 dark:text-amber-200">
                            <strong>Call this from your server, never from a browser.</strong> Your token spends your
                            wallet. If you put it in front-end JavaScript, anyone who opens dev tools can drain your balance.
                        </p>
                    </div>
                </section>

                <!-- Auth -->
                <section id="auth" class="scroll-mt-8">
                    <h2 class="text-xl font-bold text-gray-900 dark:text-white">Authentication</h2>
                    <p class="mt-2 text-sm leading-relaxed text-gray-700 dark:text-gray-300">
                        Send your token on every request. Get it from
                        <Link :href="route('api-access.index')" class="text-indigo-600 hover:underline dark:text-indigo-400">API Access</Link>
                        — your account must have API access enabled, which support can switch on.
                    </p>

                    <pre class="mt-3 overflow-x-auto rounded-lg bg-gray-900 p-4 text-xs text-gray-100"><code>Authorization: Bearer YOUR_API_TOKEN
Accept: application/json</code></pre>

                    <p class="mt-3 text-sm text-gray-700 dark:text-gray-300">
                        A missing or unrecognised token returns <code class="rounded bg-gray-100 px-1 dark:bg-gray-700">401</code>.
                        Regenerating a token invalidates the previous one immediately.
                    </p>
                </section>

                <!-- Errors -->
                <section id="errors" class="scroll-mt-8">
                    <h2 class="text-xl font-bold text-gray-900 dark:text-white">Errors &amp; billing</h2>
                    <p class="mt-2 text-sm leading-relaxed text-gray-700 dark:text-gray-300">
                        You are charged when a lookup succeeds. If the provider cannot find the record, or the request
                        fails on our side, <strong>you are automatically refunded</strong> — you do not pay for a failed lookup.
                    </p>

                    <div class="mt-4 overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-4 py-2 text-left font-medium text-gray-700 dark:text-gray-200">Status</th>
                                    <th class="px-4 py-2 text-left font-medium text-gray-700 dark:text-gray-200">Meaning</th>
                                    <th class="px-4 py-2 text-left font-medium text-gray-700 dark:text-gray-200">What to do</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700 text-gray-700 dark:text-gray-300">
                                <tr><td class="px-4 py-2"><code>200</code></td><td class="px-4 py-2">Success</td><td class="px-4 py-2">You were charged</td></tr>
                                <tr><td class="px-4 py-2"><code>401</code></td><td class="px-4 py-2">Bad or missing token</td><td class="px-4 py-2">Check the header; do not retry</td></tr>
                                <tr><td class="px-4 py-2"><code>402</code></td><td class="px-4 py-2">Your wallet is empty</td><td class="px-4 py-2">Fund your wallet, then retry</td></tr>
                                <tr><td class="px-4 py-2"><code>422</code></td><td class="px-4 py-2">Invalid input, or record not found</td><td class="px-4 py-2">Fix input; not charged</td></tr>
                                <tr><td class="px-4 py-2"><code>502</code> / <code>504</code></td><td class="px-4 py-2">Provider failed or timed out</td><td class="px-4 py-2">Safe to retry; not charged</td></tr>
                                <tr><td class="px-4 py-2"><code>503</code></td><td class="px-4 py-2">Service switched off</td><td class="px-4 py-2">Contact support; do not retry</td></tr>
                            </tbody>
                        </table>
                    </div>

                    <p class="mt-3 text-sm text-gray-700 dark:text-gray-300">
                        Every response carries a <code class="rounded bg-gray-100 px-1 dark:bg-gray-700">reference</code>.
                        Store it — it is what support will ask for.
                    </p>
                </section>

                <!-- Balance -->
                <section id="balance" class="scroll-mt-8">
                    <div class="flex items-baseline gap-3">
                        <h2 class="text-xl font-bold text-gray-900 dark:text-white">Check balance</h2>
                        <code class="rounded bg-green-100 px-2 py-0.5 text-xs font-semibold text-green-800 dark:bg-green-900 dark:text-green-200">GET /balance</code>
                    </div>
                    <p class="mt-2 text-sm text-gray-700 dark:text-gray-300">Free to call. Poll it to alert yourself before you run dry.</p>

                    <div class="relative mt-3">
                        <button @click="copy(snippets.curlBalance, 'balance')" class="absolute right-2 top-2 rounded bg-gray-700 px-2 py-1 text-xs text-gray-200 hover:bg-gray-600">{{ copied === 'balance' ? 'Copied' : 'Copy' }}</button>
                        <pre class="overflow-x-auto rounded-lg bg-gray-900 p-4 text-xs text-gray-100"><code>{{ snippets.curlBalance }}</code></pre>
                    </div>
                    <pre class="mt-2 overflow-x-auto rounded-lg bg-gray-800 p-4 text-xs text-gray-100"><code>{ "status": "success", "data": { "balance": 25400, "currency": "NGN" } }</code></pre>
                </section>

                <!-- Services -->
                <section id="services" class="scroll-mt-8">
                    <div class="flex items-baseline gap-3">
                        <h2 class="text-xl font-bold text-gray-900 dark:text-white">List services &amp; prices</h2>
                        <code class="rounded bg-green-100 px-2 py-0.5 text-xs font-semibold text-green-800 dark:bg-green-900 dark:text-green-200">GET /services</code>
                    </div>
                    <p class="mt-2 text-sm text-gray-700 dark:text-gray-300">
                        Returns <em>your</em> rates, not a public rate card. Use it to build your own pricing page instead of
                        hard-coding numbers that later change.
                    </p>
                    <pre class="mt-3 overflow-x-auto rounded-lg bg-gray-800 p-4 text-xs text-gray-100"><code>{
  "status": "success",
  "data": {
    "role": "API",
    "currency": "NGN",
    "services": [
      { "service": "nin.verify", "label": "NIN Verification", "price": 40, "available": true },
      { "service": "bvn.search.premium", "label": "BVN Slip", "price": 120, "available": true }
    ]
  }
}</code></pre>
                </section>

                <!-- NIN -->
                <section id="nin" class="scroll-mt-8">
                    <div class="flex items-baseline gap-3">
                        <h2 class="text-xl font-bold text-gray-900 dark:text-white">Verify a NIN</h2>
                        <code class="rounded bg-blue-100 px-2 py-0.5 text-xs font-semibold text-blue-800 dark:bg-blue-900 dark:text-blue-200">POST /nin/verify</code>
                    </div>
                    <p class="mt-2 text-sm text-gray-700 dark:text-gray-300">
                        Three lookup methods, selected with <code class="rounded bg-gray-100 px-1 dark:bg-gray-700">method</code>.
                        Each is priced separately — see <code class="rounded bg-gray-100 px-1 dark:bg-gray-700">GET /services</code>.
                    </p>

                    <div class="mt-4 overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-4 py-2 text-left font-medium text-gray-700 dark:text-gray-200">Field</th>
                                    <th class="px-4 py-2 text-left font-medium text-gray-700 dark:text-gray-200">Required</th>
                                    <th class="px-4 py-2 text-left font-medium text-gray-700 dark:text-gray-200">Notes</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700 text-gray-700 dark:text-gray-300">
                                <tr><td class="px-4 py-2"><code>method</code></td><td class="px-4 py-2">yes</td><td class="px-4 py-2"><code>nin</code>, <code>phone</code> or <code>demographic</code></td></tr>
                                <tr><td class="px-4 py-2"><code>nin</code></td><td class="px-4 py-2">if method=nin</td><td class="px-4 py-2">exactly 11 digits</td></tr>
                                <tr><td class="px-4 py-2"><code>phone</code></td><td class="px-4 py-2">if method=phone</td><td class="px-4 py-2">exactly 11 digits</td></tr>
                                <tr><td class="px-4 py-2"><code>first_name</code>, <code>last_name</code></td><td class="px-4 py-2">if method=demographic</td><td class="px-4 py-2">2–100 characters</td></tr>
                                <tr><td class="px-4 py-2"><code>gender</code></td><td class="px-4 py-2">if method=demographic</td><td class="px-4 py-2"><code>M</code> or <code>F</code></td></tr>
                                <tr><td class="px-4 py-2"><code>date_of_birth</code></td><td class="px-4 py-2">if method=demographic</td><td class="px-4 py-2"><code>YYYY-MM-DD</code></td></tr>
                                <tr><td class="px-4 py-2"><code>provider</code></td><td class="px-4 py-2">no</td><td class="px-4 py-2">from <code>GET /nin/providers</code>; omit for the default</td></tr>
                            </tbody>
                        </table>
                    </div>

                    <h3 class="mt-5 text-sm font-semibold text-gray-900 dark:text-white">By NIN</h3>
                    <div class="relative mt-2">
                        <button @click="copy(snippets.curlNin, 'nin')" class="absolute right-2 top-2 rounded bg-gray-700 px-2 py-1 text-xs text-gray-200 hover:bg-gray-600">{{ copied === 'nin' ? 'Copied' : 'Copy' }}</button>
                        <pre class="overflow-x-auto rounded-lg bg-gray-900 p-4 text-xs text-gray-100"><code>{{ snippets.curlNin }}</code></pre>
                    </div>

                    <h3 class="mt-5 text-sm font-semibold text-gray-900 dark:text-white">By phone number</h3>
                    <pre class="mt-2 overflow-x-auto rounded-lg bg-gray-900 p-4 text-xs text-gray-100"><code>{{ snippets.curlNinPhone }}</code></pre>

                    <h3 class="mt-5 text-sm font-semibold text-gray-900 dark:text-white">By demographic details</h3>
                    <pre class="mt-2 overflow-x-auto rounded-lg bg-gray-900 p-4 text-xs text-gray-100"><code>{{ snippets.curlNinDemo }}</code></pre>

                    <h3 class="mt-5 text-sm font-semibold text-gray-900 dark:text-white">Success</h3>
                    <pre class="mt-2 overflow-x-auto rounded-lg bg-gray-800 p-4 text-xs text-gray-100"><code>{{ ninSuccess }}</code></pre>

                    <h3 class="mt-5 text-sm font-semibold text-gray-900 dark:text-white">Failure</h3>
                    <pre class="mt-2 overflow-x-auto rounded-lg bg-gray-800 p-4 text-xs text-gray-100"><code>{{ ninError }}</code></pre>
                </section>

                <!-- BVN -->
                <section id="bvn" class="scroll-mt-8">
                    <div class="flex items-baseline gap-3">
                        <h2 class="text-xl font-bold text-gray-900 dark:text-white">Verify a BVN</h2>
                        <code class="rounded bg-blue-100 px-2 py-0.5 text-xs font-semibold text-blue-800 dark:bg-blue-900 dark:text-blue-200">POST /bvn/verify</code>
                    </div>
                    <p class="mt-2 text-sm text-gray-700 dark:text-gray-300">
                        <code class="rounded bg-gray-100 px-1 dark:bg-gray-700">slip_type</code> is
                        <code>premium</code>, <code>standard</code> or <code>regular</code>, and selects how much detail
                        you get back — and what you pay. <code>photo</code> comes back as raw base64 JPEG with no
                        <code>data:</code> prefix.
                    </p>

                    <div class="relative mt-3">
                        <button @click="copy(snippets.curlBvn, 'bvn')" class="absolute right-2 top-2 rounded bg-gray-700 px-2 py-1 text-xs text-gray-200 hover:bg-gray-600">{{ copied === 'bvn' ? 'Copied' : 'Copy' }}</button>
                        <pre class="overflow-x-auto rounded-lg bg-gray-900 p-4 text-xs text-gray-100"><code>{{ snippets.curlBvn }}</code></pre>
                    </div>
                    <pre class="mt-2 overflow-x-auto rounded-lg bg-gray-800 p-4 text-xs text-gray-100"><code>{{ bvnSuccess }}</code></pre>
                </section>

                <!-- Plans -->
                <section id="plans" class="scroll-mt-8">
                    <div class="flex items-baseline gap-3">
                        <h2 class="text-xl font-bold text-gray-900 dark:text-white">Data plans</h2>
                        <code class="rounded bg-green-100 px-2 py-0.5 text-xs font-semibold text-green-800 dark:bg-green-900 dark:text-green-200">GET /plans</code>
                    </div>
                    <p class="mt-2 text-sm text-gray-700 dark:text-gray-300">
                        Every plan you can sell, with the <code class="rounded bg-gray-100 px-1 dark:bg-gray-700">plan_id</code>
                        to send and the price <em>you</em> pay. Plan ids are short, stable numbers (1–999) — store them in
                        your own plan table and call this endpoint to pick up new plans rather than hard-coding a list.
                    </p>
                    <p class="mt-2 text-sm text-gray-700 dark:text-gray-300">
                        Prefer to read them rather than call them? The same list is on the
                        <Link :href="route('data-pricing')" class="text-indigo-600 hover:underline dark:text-indigo-400">Data Pricing</Link>
                        page — signed in, it shows your rates rather than retail. Build against
                        <code class="rounded bg-gray-100 px-1 dark:bg-gray-700">GET /plans</code> though: prices and plans
                        change, and that page is for people, not code.
                    </p>

                    <div class="relative mt-3">
                        <button @click="copy(snippets.curlPlans, 'plans')" class="absolute right-2 top-2 rounded bg-gray-700 px-2 py-1 text-xs text-gray-200 hover:bg-gray-600">{{ copied === 'plans' ? 'Copied' : 'Copy' }}</button>
                        <pre class="overflow-x-auto rounded-lg bg-gray-900 p-4 text-xs text-gray-100"><code>{{ snippets.curlPlans }}</code></pre>
                    </div>

                    <pre class="mt-2 overflow-x-auto rounded-lg bg-gray-800 p-4 text-xs text-gray-100"><code>{{ plansSuccess }}</code></pre>

                    <div class="mt-4 rounded-lg border-l-4 border-amber-400 bg-amber-50 p-4 dark:bg-amber-900/20">
                        <p class="text-sm text-amber-800 dark:text-amber-200">
                            A <code>plan_id</code> always refers to the same bundle and is never reissued to a different
                            one, so it is safe to store. A plan that is withdrawn simply stops appearing here — its id is
                            retired rather than reused.
                        </p>
                    </div>
                </section>

                <!-- Data -->
                <section id="data" class="scroll-mt-8">
                    <div class="flex items-baseline gap-3">
                        <h2 class="text-xl font-bold text-gray-900 dark:text-white">Buy data</h2>
                        <code class="rounded bg-blue-100 px-2 py-0.5 text-xs font-semibold text-blue-800 dark:bg-blue-900 dark:text-blue-200">POST /data</code>
                    </div>
                    <p class="mt-2 text-sm text-gray-700 dark:text-gray-300">
                        Data purchases are <strong>asynchronous</strong>. You get <code>201</code> with a reference while
                        the top-up is still being delivered, then poll
                        <code class="rounded bg-gray-100 px-1 dark:bg-gray-700">GET /data/&#123;reference&#125;</code>
                        until <code>status</code> is <code>success</code> or <code>failed</code>. A failed purchase is refunded.
                    </p>

                    <h3 class="mt-5 text-sm font-semibold text-gray-900 dark:text-white">Networks</h3>
                    <p class="mt-1 text-sm text-gray-700 dark:text-gray-300">
                        Send <code class="rounded bg-gray-100 px-1 dark:bg-gray-700">network</code> as the id or the name —
                        both are accepted, in any case.
                    </p>

                    <div class="mt-3 overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-4 py-2 text-left font-medium text-gray-700 dark:text-gray-200">ID</th>
                                    <th class="px-4 py-2 text-left font-medium text-gray-700 dark:text-gray-200">Network</th>
                                    <th class="px-4 py-2 text-left font-medium text-gray-700 dark:text-gray-200">Also accepted</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700 text-gray-700 dark:text-gray-300">
                                <tr><td class="px-4 py-2"><code>1</code></td><td class="px-4 py-2">MTN</td><td class="px-4 py-2"><code>mtn</code></td></tr>
                                <tr><td class="px-4 py-2"><code>2</code></td><td class="px-4 py-2">Airtel</td><td class="px-4 py-2"><code>airtel</code></td></tr>
                                <tr><td class="px-4 py-2"><code>3</code></td><td class="px-4 py-2">Glo</td><td class="px-4 py-2"><code>glo</code>, <code>globacom</code></td></tr>
                                <tr><td class="px-4 py-2"><code>4</code></td><td class="px-4 py-2">9mobile</td><td class="px-4 py-2"><code>9mobile</code>, <code>etisalat</code></td></tr>
                            </tbody>
                        </table>
                    </div>

                    <p class="mt-3 text-sm text-gray-700 dark:text-gray-300">
                        The <strong>plan decides the network</strong> — <code class="rounded bg-gray-100 px-1 dark:bg-gray-700">network</code>
                        is a cross-check. If it disagrees with the plan you get a <code>422</code> naming both, which
                        catches a mis-mapped plan table before it sells the wrong bundle.
                    </p>

                    <div class="relative mt-4">
                        <button @click="copy(snippets.curlData, 'data')" class="absolute right-2 top-2 rounded bg-gray-700 px-2 py-1 text-xs text-gray-200 hover:bg-gray-600">{{ copied === 'data' ? 'Copied' : 'Copy' }}</button>
                        <pre class="overflow-x-auto rounded-lg bg-gray-900 p-4 text-xs text-gray-100"><code>{{ snippets.curlData }}</code></pre>
                    </div>

                    <h3 class="mt-6 text-sm font-semibold text-gray-900 dark:text-white">Migrating from another provider</h3>
                    <p class="mt-1 text-sm text-gray-700 dark:text-gray-300">
                        You do not have to rename your fields. We accept the spellings the common data APIs use and
                        normalize them, so in most cases switching over is a change of URL and token only.
                    </p>

                    <div class="mt-3 overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-4 py-2 text-left font-medium text-gray-700 dark:text-gray-200">Field</th>
                                    <th class="px-4 py-2 text-left font-medium text-gray-700 dark:text-gray-200">Also accepted as</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700 text-gray-700 dark:text-gray-300">
                                <tr><td class="px-4 py-2"><code>network</code></td><td class="px-4 py-2"><code>network_id</code>, <code>operator</code>, <code>service</code></td></tr>
                                <tr><td class="px-4 py-2"><code>plan_id</code></td><td class="px-4 py-2"><code>data_plan</code>, <code>plan</code>, <code>dataplan</code>, <code>plan_code</code>, <code>variation_code</code> — the id from <code>GET /plans</code></td></tr>
                                <tr><td class="px-4 py-2"><code>phone</code></td><td class="px-4 py-2"><code>mobile_number</code>, <code>phone_number</code>, <code>msisdn</code>, <code>mobile</code>, <code>recipient</code></td></tr>
                                <tr><td class="px-4 py-2"><code>ported</code></td><td class="px-4 py-2"><code>ported_number</code>, <code>Ported_number</code>, <code>is_ported</code></td></tr>
                                <tr><td class="px-4 py-2"><code>client_ref</code></td><td class="px-4 py-2"><code>ref</code>, <code>request-id</code>, <code>request_id</code>, <code>reference</code>, <code>order_id</code></td></tr>
                            </tbody>
                        </table>
                    </div>

                    <p class="mt-3 text-sm text-gray-700 dark:text-gray-300">
                        Phone numbers are accepted as <code>08012345678</code>, <code>2348012345678</code>,
                        <code>+2348012345678</code> or <code>8012345678</code>.
                        <code class="rounded bg-gray-100 px-1 dark:bg-gray-700">bypass</code> and
                        <code class="rounded bg-gray-100 px-1 dark:bg-gray-700">payment_medium</code> are accepted and
                        ignored — we never validate prefixes, and there is only one wallet.
                    </p>

                    <div class="relative mt-4">
                        <button @click="copy(snippets.curlDataAlias, 'dataAlias')" class="absolute right-2 top-2 rounded bg-gray-700 px-2 py-1 text-xs text-gray-200 hover:bg-gray-600">{{ copied === 'dataAlias' ? 'Copied' : 'Copy' }}</button>
                        <pre class="overflow-x-auto rounded-lg bg-gray-900 p-4 text-xs text-gray-100"><code>{{ snippets.curlDataAlias }}</code></pre>
                    </div>

                    <div class="mt-4 rounded-lg border-l-4 border-green-400 bg-green-50 p-4 dark:bg-green-900/20">
                        <p class="text-sm text-green-800 dark:text-green-200">
                            <strong>Your reference is the idempotency key.</strong> Send your own order id as
                            <code>ref</code> (any format). Replaying the same id inside 10 minutes returns the original
                            purchase instead of buying twice — so a timeout is safe to retry. Omit it and we generate
                            one, which means a retry <em>will</em> buy again.
                        </p>
                    </div>

                    <h3 class="mt-6 text-sm font-semibold text-gray-900 dark:text-white">Response</h3>
                    <p class="mt-1 text-sm text-gray-700 dark:text-gray-300">
                        Returned in both the flat shape other data APIs use and our own
                        <code class="rounded bg-gray-100 px-1 dark:bg-gray-700">data</code> object, so you can read
                        whichever your existing code already expects. Your
                        <code class="rounded bg-gray-100 px-1 dark:bg-gray-700">request-id</code> comes back verbatim.
                    </p>
                    <pre class="mt-2 overflow-x-auto rounded-lg bg-gray-800 p-4 text-xs text-gray-100"><code>{{ dataSuccess }}</code></pre>

                    <div class="mt-4 rounded-lg border-l-4 border-amber-400 bg-amber-50 p-4 dark:bg-amber-900/20">
                        <p class="text-sm text-amber-800 dark:text-amber-200">
                            <strong>Read <code>transaction_status</code>, not <code>status</code>, to know if the data
                            landed.</strong> Top-level <code>status</code> means we accepted the request; delivery
                            happens asynchronously, so it can still be <code>pending</code>. Poll
                            <code>GET /data/&#123;reference&#125;</code> until <code>transaction_status</code> is
                            <code>success</code> or <code>fail</code>. A failed purchase is refunded.
                        </p>
                    </div>
                </section>

                <!-- Recipes -->
                <section id="recipes" class="scroll-mt-8">
                    <h2 class="text-xl font-bold text-gray-900 dark:text-white">Full example</h2>
                    <p class="mt-2 text-sm text-gray-700 dark:text-gray-300">
                        Verifying a NIN from your own backend, with the error handling that matters in production.
                    </p>

                    <h3 class="mt-5 text-sm font-semibold text-gray-900 dark:text-white">PHP (Laravel)</h3>
                    <pre class="mt-2 overflow-x-auto rounded-lg bg-gray-900 p-4 text-xs text-gray-100"><code>{{ snippets.php }}</code></pre>

                    <h3 class="mt-5 text-sm font-semibold text-gray-900 dark:text-white">Node.js</h3>
                    <pre class="mt-2 overflow-x-auto rounded-lg bg-gray-900 p-4 text-xs text-gray-100"><code>{{ snippets.node }}</code></pre>

                    <h3 class="mt-5 text-sm font-semibold text-gray-900 dark:text-white">Python</h3>
                    <pre class="mt-2 overflow-x-auto rounded-lg bg-gray-900 p-4 text-xs text-gray-100"><code>{{ snippets.python }}</code></pre>

                    <div class="mt-6 rounded-lg border-l-4 border-indigo-400 bg-indigo-50 p-4 dark:bg-indigo-900/20">
                        <h3 class="text-sm font-semibold text-indigo-900 dark:text-indigo-200">Before you go live</h3>
                        <ul class="mt-2 list-inside list-disc space-y-1 text-sm text-indigo-800 dark:text-indigo-300">
                            <li>Keep the token in an environment variable, never in source control.</li>
                            <li>Call from your server only — never from browser JavaScript.</li>
                            <li>Set a request timeout of at least 40 seconds; identity providers are slow.</li>
                            <li>Store the <code>reference</code> from every response against your own order.</li>
                            <li>Alert on <code>402</code> — that is your wallet, and every call fails until you fund it.</li>
                            <li>Retry <code>502</code> and <code>504</code> only. Never retry <code>422</code>.</li>
                        </ul>
                    </div>
                </section>

                <footer class="border-t border-gray-200 pt-6 text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">
                    Need help integrating?
                    <Link :href="route('help.index')" class="text-indigo-600 hover:underline dark:text-indigo-400">Contact support</Link>
                    with your reference and we will take a look.
                </footer>
            </main>
        </div>
    </div>
</template>
