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
    { id: 'validation', title: 'Validate a NIN' },
    { id: 'ipe', title: 'IPE clearance' },
    { id: 'bvn', title: 'Verify a BVN' },
    { id: 'bvn-records', title: 'Search enrolments' },
    { id: 'bvn-modification', title: 'Modify a BVN' },
    { id: 'bvn-retrieval', title: 'Retrieve a BVN' },
    { id: 'bvn-onboarding', title: 'Onboard an agent' },
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
  -d '{ "nin": "12345678901" }'`,

    curlNinPhone: `curl -X POST ${base}/nin/verify \\
  -H "Authorization: Bearer YOUR_API_TOKEN" \\
  -H "Content-Type: application/json" \\
  -d '{ "phone": "08012345678" }'`,

    curlNinDemo: `curl -X POST ${base}/nin/verify \\
  -H "Authorization: Bearer YOUR_API_TOKEN" \\
  -H "Content-Type: application/json" \\
  -d '{
    "first_name": "John",
    "last_name": "Doe",
    "gender": "M",
    "date_of_birth": "1990-05-21"
  }'`,

    curlValidate: `curl -X POST ${base}/nin/validate \\
  -H "Authorization: Bearer YOUR_API_TOKEN" \\
  -H "Content-Type: application/json" \\
  -d '{ "nin": "12345678901" }'`,

    curlValidateStatus: `# By the id we returned, or by the NIN you sent.
curl ${base}/nin/validate/8841 \\
  -H "Authorization: Bearer YOUR_API_TOKEN"

curl ${base}/nin/validate/12345678901 \\
  -H "Authorization: Bearer YOUR_API_TOKEN"`,

    curlIpe: `curl -X POST ${base}/nin/ipe \\
  -H "Authorization: Bearer YOUR_API_TOKEN" \\
  -H "Content-Type: application/json" \\
  -d '{ "tracking_id": "ABC1234567890XY" }'`,

    curlIpeStatus: `# By the id we returned, or by the tracking id you sent.
curl ${base}/nin/ipe/2841 \\
  -H "Authorization: Bearer YOUR_API_TOKEN"

curl ${base}/nin/ipe/ABC1234567890XY \\
  -H "Authorization: Bearer YOUR_API_TOKEN"`,

    curlBvn: `curl -X POST ${base}/bvn/verify \\
  -H "Authorization: Bearer YOUR_API_TOKEN" \\
  -H "Content-Type: application/json" \\
  -d '{ "bvn": "22345678901" }'`,

    curlBvnSlip: `# Ask for a different slip only if you want one.
curl -X POST ${base}/bvn/verify \\
  -H "Authorization: Bearer YOUR_API_TOKEN" \\
  -H "Content-Type: application/json" \\
  -d '{ "bvn": "22345678901", "slip_type": "regular" }'`,

    curlRecords: `curl -G ${base}/bvn/records \\
  -H "Authorization: Bearer YOUR_API_TOKEN" \\
  --data-urlencode "query=TKT4471902"

# Or every enrolment captured by one agent.
curl -G ${base}/bvn/records \\
  -H "Authorization: Bearer YOUR_API_TOKEN" \\
  --data-urlencode "type=enroller_id" \\
  --data-urlencode "query=AGT00219"`,

    curlModification: `curl -X POST ${base}/bvn/modification \\
  -H "Authorization: Bearer YOUR_API_TOKEN" \\
  -H "Content-Type: application/json" \\
  -d '{
    "service_type": "modify-name",
    "bvn": "22345678901",
    "nin": "12345678901",
    "nin_slip": "JVBERi0xLjQKJcfs...",
    "old_first_name": "John",
    "old_last_name": "Doe",
    "new_first_name": "Jonathan",
    "new_last_name": "Doe"
  }'`,

    curlModificationStatus: `# By the id we returned, or by the BVN you sent.
curl ${base}/bvn/modification/clx8k2p9a0001 \\
  -H "Authorization: Bearer YOUR_API_TOKEN"

curl ${base}/bvn/modification/22345678901 \\
  -H "Authorization: Bearer YOUR_API_TOKEN"`,

    curlRetrieval: `curl -X POST ${base}/bvn/retrieval \\
  -H "Authorization: Bearer YOUR_API_TOKEN" \\
  -H "Content-Type: application/json" \\
  -d '{ "ticket_id": "48812037" }'`,

    curlRetrievalStatus: `# By the id we returned, or by the ticket id you sent.
curl ${base}/bvn/retrieval/clx8k2p9a0002 \\
  -H "Authorization: Bearer YOUR_API_TOKEN"

curl ${base}/bvn/retrieval/48812037 \\
  -H "Authorization: Bearer YOUR_API_TOKEN"`,

    curlOnboarding: `curl -X POST ${base}/bvn/onboarding \\
  -H "Authorization: Bearer YOUR_API_TOKEN" \\
  -H "Content-Type: application/json" \\
  -d '{
    "agent_location": "Kano Main Market",
    "agent_bvn": "22345678901",
    "bank_name": "First Bank of Nigeria Plc",
    "account_number": "0123456789",
    "account_name": "John Ade Doe",
    "first_name": "John",
    "last_name": "Doe",
    "email": "john.doe@example.com",
    "phone_number": "08012345678",
    "address": "12 Broad Street, Ikeja",
    "state_of_residence": "Kano",
    "lga": "Nassarawa",
    "zone": "north-west",
    "date_of_birth": "1990-05-21"
  }'`,

    curlOnboardingStatus: `# By the id we returned, or by the agent's email.
curl ${base}/bvn/onboarding/clx8k2p9a0003 \\
  -H "Authorization: Bearer YOUR_API_TOKEN"

curl ${base}/bvn/onboarding/john.doe@example.com \\
  -H "Authorization: Bearer YOUR_API_TOKEN"`,

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
        'nin' => $request->input('nin'),
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
  body: JSON.stringify({ nin }),
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
    json={"nin": nin},
    timeout=40,
)

body = res.json()

if not res.ok or not body.get("success"):
    raise RuntimeError(body.get("error", {}).get("message", "Verification failed"))

return body["data"]`,
};

const ninSuccess = `{
  "success": true,
  "provider": "auto",
  "method": "nin",
  "reference": "NIN_66A3F2C1B9D4E8721",
  "data": {
    "nin": "12345678901",
    "vnin": "AB1234567890CDEF",
    "tracking_id": "ABC1234567890XY",
    "central_id": "9912345678",

    "first_name": "JOHN",
    "middle_name": "ADE",
    "last_name": "DOE",
    "full_name": "JOHN ADE DOE",
    "title": "MR",

    "gender": "MALE",
    "date_of_birth": "1990-05-21",
    "marital_status": "SINGLE",
    "height": "175",
    "religion": "CHRISTIANITY",
    "profession": "ENGINEER",
    "employment_status": "EMPLOYED",
    "education_level": "TERTIARY",
    "spoken_language": "ENGLISH",
    "nationality": "NIGERIA",

    "phone": "08012345678",
    "phone2": "08087654321",
    "email": "john.doe@example.com",

    "birth_state": "KANO",
    "birth_lga": "NASSARAWA",
    "birth_country": "NIGERIA",
    "state_of_origin": "KANO",
    "lga_of_origin": "NASSARAWA",
    "place_of_origin": "KANO",

    "residence_address": "12 BROAD STREET",
    "residence_town": "IKEJA",
    "residence_lga": "IKEJA",
    "residence_state": "LAGOS",
    "residence_status": "OWN",

    "nok_first_name": "JANE",
    "nok_middle_name": "AMAKA",
    "nok_last_name": "DOE",
    "nok_address": "12 BROAD STREET",
    "nok_town": "IKEJA",
    "nok_lga": "IKEJA",
    "nok_state": "LAGOS",

    "photo": "<base64 jpeg>",
    "signature": "<base64 jpeg>",

    "provider": "NIMC Gateway",
    "validation_id": 1042
  }
}`;

const ninAliases = `{
  "data": {
    "surname": "DOE",              // = last_name
    "firstname": "JOHN",           // = first_name
    "middlename": "ADE",           // = middle_name
    "othernames": "JOHN ADE",      // first + middle
    "dob": "1990-05-21",           // = date_of_birth
    "birthdate": "1990-05-21",     // = date_of_birth
    "telephoneno": "08012345678",  // = phone
    "address": "12 BROAD STREET",  // = residence_address
    "residence_AdressLine": "12 BROAD STREET",
    "state": "LAGOS",              // = residence_state
    "lga": "IKEJA",                // = residence_lga
    "self_origin_state": "KANO",   // = state_of_origin
    "self_origin_lga": "NASSARAWA",
    "photo_path": "<base64 jpeg>", // = photo
    "signature_path": "<base64 jpeg>"
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

const validationAccepted = `{
  "status": "processing",
  "reference": "NIN_66A3F2C1B9D4E8721",
  "amount": 25,
  "message": "Validation submitted. Results typically take 3 days to a week — poll this submission for the outcome.",
  "data": {
    "id": 8841,
    "nin": "12345678901",
    "status": "processing",
    "result": "Pending",
    "comment": "[NIN_66A3F2C1B9D4E8721] Submitted to NIMC Gateway via API",
    "submitted_at": "2026-07-23T09:12:44+00:00",
    "updated_at": "2026-07-23T09:12:44+00:00"
  }
}`;

const validationStatus = `{
  "status": "success",
  "data": {
    "id": 8841,
    "nin": "12345678901",
    "status": "completed",
    "result": "{\\"success\\":true,\\"status\\":\\"completed\\", ... }",
    "comment": "nin: 12345678901, status: Approved, reply: SUCCESSFUL",
    "submitted_at": "2026-07-23T09:12:44+00:00",
    "updated_at": "2026-07-28T14:02:10+00:00"
  }
}`;

const validationUnconfirmed = `{
  "status": "unconfirmed",
  "reference": "NIN_66A3F2C1B9D4E8721",
  "refunded": true,
  "message": "The provider did not confirm this submission. It may still have been filed — do not resubmit. Poll this submission or contact support to reconcile it.",
  "data": {
    "id": 8842,
    "nin": "12345678901",
    "status": "processing",
    "result": "Unconfirmed",
    "submitted_at": "2026-07-23T09:12:44+00:00"
  }
}`;

const validationError = `{
  "status": "error",
  "code": "submission_failed",
  "message": "Record not found",
  "reference": "NIN_66A3F2C1B9D4E8721",
  "refunded": true,
  "data": {
    "id": 8843,
    "nin": "12345678901",
    "status": "failed",
    "result": "Failed"
  }
}`;

const ipeSuccess = `{
  "status": "success",
  "reference": "IPE_66A3F2C1B9D4E1234",
  "amount": 200,
  "data": {
    "id": 2841,
    "tracking_id": "ABC1234567890XY",
    "status": "processing",
    "result": "Pending",
    "comment": "[IPE_66A3F2C1B9D4E1234] Submitted to NIMC Gateway via API",
    "submitted_at": "2026-07-23T09:12:44+00:00",
    "updated_at": "2026-07-23T09:12:44+00:00"
  }
}`;

const ipeUnconfirmed = `{
  "status": "unconfirmed",
  "reference": "IPE_66A3F2C1B9D4E1234",
  "refunded": true,
  "message": "The provider did not confirm this submission. It may still have been filed — do not resubmit. Poll this submission or contact support to reconcile it.",
  "data": {
    "id": 2842,
    "tracking_id": "ABC1234567890XY",
    "status": "processing",
    "result": "Unconfirmed",
    "submitted_at": "2026-07-23T09:12:44+00:00"
  }
}`;

const ipeStatus = `{
  "status": "success",
  "data": {
    "id": 2841,
    "tracking_id": "ABC1234567890XY",
    "status": "completed",
    "result": "{\\"success\\":true,\\"cleared\\":true, ... }",
    "comment": "status: Cleared, reply: SUCCESSFUL, nin: 12345678900, name: MUSA TANKO",
    "submitted_at": "2026-07-23T09:12:44+00:00",
    "updated_at": "2026-07-23T11:48:10+00:00"
  }
}`;

const bvnSuccess = `{
  "status": "success",
  "reference": "Verify_1753100000_7734",
  "slip_type": "premium",
  "amount": 150,
  "data": {
    "bvn": "22345678901",
    "surname": "DOE",
    "firstname": "JOHN",
    "middlename": "ADE",
    "gender": "MALE",
    "dob": "1990-05-21",
    "phone": "08012345678",
    "phone2": "08087654321",
    "email": "john.doe@example.com",
    "marital_status": "SINGLE",
    "state_of_origin": "KANO",
    "lga_of_origin": "NASSARAWA",
    "residential_Address": "12 BROAD STREET, IKEJA",
    "nationality": "NIGERIA",
    "registration_date": "2014-03-11",
    "enrollment_bank": "First Bank of Nigeria Plc",
    "enrollment_bank_branch": "First Bank of Nigeria Plc",
    "photo": "<base64 jpeg>"
  }
}`;

const recordsSuccess = `{
  "status": "success",
  "data": {
    "type": "ticket_id",
    "query": "TKT4471902",
    "count": 1,
    "records": [
      {
        "ticket_id": "TKT4471902",
        "bvn": "22345678901",
        "enrollee_name": "JOHN ADE DOE",
        "enroller_id": "AGT00219",
        "status": "enrolled",
        "comment": null,
        "date_enrolled": "2026-03-14"
      }
    ]
  }
}`;

const modificationAccepted = `{
  "status": "success",
  "reference": "clx8k2p9a0001",
  "amount": 3500,
  "data": {
    "id": "clx8k2p9a0001",
    "bvn": "22345678901",
    "nin": "12345678901",
    "service_type": "modify-name",
    "service_label": "Name Modification",
    "status": "pending",
    "comment": null,
    "amount_charged": 3500,
    "old_first_name": "John",
    "old_middle_name": null,
    "old_last_name": "Doe",
    "new_first_name": "Jonathan",
    "new_middle_name": null,
    "new_last_name": "Doe",
    "submitted_at": "2026-08-06T09:12:44+00:00",
    "updated_at": "2026-08-06T09:12:44+00:00"
  }
}`;

const modificationStatus = `{
  "status": "success",
  "data": {
    "id": "clx8k2p9a0001",
    "bvn": "22345678901",
    "service_type": "modify-name",
    "status": "modified",
    "comment": "Name updated at NIBSS.",
    "amount_charged": 3500,
    "submitted_at": "2026-08-06T09:12:44+00:00",
    "updated_at": "2026-08-09T15:31:02+00:00"
  }
}`;

const modificationMissing = `{
  "status": "error",
  "code": "missing_fields",
  "message": "This service type requires: old_dob, new_dob.",
  "fields": ["old_dob", "new_dob"]
}`;

const retrievalAccepted = `{
  "status": "success",
  "reference": "clx8k2p9a0002",
  "amount": 1500,
  "data": {
    "id": "clx8k2p9a0002",
    "ticket_id": "48812037",
    "status": "pending",
    "bvn": null,
    "comment": null,
    "submitted_at": "2026-08-06T09:12:44+00:00",
    "updated_at": "2026-08-06T09:12:44+00:00"
  }
}`;

const retrievalStatus = `{
  "status": "success",
  "data": {
    "id": "clx8k2p9a0002",
    "ticket_id": "48812037",
    "status": "completed",
    "bvn": "22345678901",
    "comment": "Retrieved from enrolment batch.",
    "submitted_at": "2026-08-06T09:12:44+00:00",
    "updated_at": "2026-08-07T11:04:19+00:00"
  }
}`;

const onboardingAccepted = `{
  "status": "success",
  "reference": "clx8k2p9a0003",
  "amount": 15000,
  "data": {
    "id": "clx8k2p9a0003",
    "status": "Submitted",
    "comment": null,
    "agent_location": "Kano Main Market",
    "agent_bvn": "22345678901",
    "bank_name": "First Bank of Nigeria Plc",
    "account_number": "0123456789",
    "account_name": "John Ade Doe",
    "first_name": "John",
    "last_name": "Doe",
    "email": "john.doe@example.com",
    "phone_number": "08012345678",
    "address": "12 Broad Street, Ikeja",
    "state_of_residence": "Kano",
    "lga": "Nassarawa",
    "zone": "north-west",
    "date_of_birth": "1990-05-21",
    "submitted_at": "2026-08-06T09:12:44+00:00",
    "updated_at": "2026-08-06T09:12:44+00:00"
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
                    <p class="mt-2 text-sm leading-relaxed text-gray-700 dark:text-gray-300">
                        The services our staff work by hand —
                        <a href="#bvn-modification" class="text-indigo-600 hover:underline dark:text-indigo-400">modification</a>,
                        <a href="#bvn-retrieval" class="text-indigo-600 hover:underline dark:text-indigo-400">retrieval</a> and
                        <a href="#bvn-onboarding" class="text-indigo-600 hover:underline dark:text-indigo-400">onboarding</a> —
                        settle differently: the fee buys the work of processing the request, so a request rejected
                        <em>after</em> it was filed is not refunded automatically. Bad input is still free, because it
                        never gets filed.
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
                                <tr><td class="px-4 py-2"><code>201</code></td><td class="px-4 py-2">Submission accepted (IPE, data, BVN modification / retrieval / onboarding)</td><td class="px-4 py-2">Charged; read the outcome back later</td></tr>
                                <tr><td class="px-4 py-2"><code>202</code></td><td class="px-4 py-2">Job filed (NIN validation), or a submission sent but unconfirmed (IPE, validation)</td><td class="px-4 py-2">Read <code>status</code> to tell them apart: <code>processing</code> is charged and running, <code>unconfirmed</code> is refunded. <strong>Never resubmit either</strong> — read it back</td></tr>
                                <tr><td class="px-4 py-2"><code>401</code></td><td class="px-4 py-2">Bad or missing token</td><td class="px-4 py-2">Check the header; do not retry</td></tr>
                                <tr><td class="px-4 py-2"><code>402</code></td><td class="px-4 py-2">Your wallet is empty</td><td class="px-4 py-2">Fund your wallet, then retry</td></tr>
                                <tr><td class="px-4 py-2"><code>404</code></td><td class="px-4 py-2">No such record of yours</td><td class="px-4 py-2">Read-backs only see your own submissions</td></tr>
                                <tr><td class="px-4 py-2"><code>422</code></td><td class="px-4 py-2">Invalid input, or record not found</td><td class="px-4 py-2">Fix input; not charged</td></tr>
                                <tr><td class="px-4 py-2"><code>502</code> / <code>504</code></td><td class="px-4 py-2">Provider failed or timed out</td><td class="px-4 py-2">Safe to retry a <em>lookup</em>; not charged. Never an IPE</td></tr>
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
      { "service": "bvn.verify", "label": "BVN Verification", "price": 150, "available": true },
      { "service": "nin.validation", "label": "NIN Validation", "price": 25, "available": true },
      { "service": "nin.ipe", "label": "IPE Clearance", "price": 200, "available": true },
      { "service": "bvn.mod.name", "label": "Name Modification", "price": 3500, "available": true },
      { "service": "bvn.retrieve.id", "label": "Retrieve With ID", "price": 1500, "available": true },
      { "service": "bvn.onboarding1", "label": "BVN SDK Onboarding", "price": 15000, "available": true }
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
                        Three lookups, one endpoint. <strong>Send the identifier you have</strong> — a NIN, a phone
                        number, or the person's details — and we run the matching lookup. Each is priced separately, so
                        check <code class="rounded bg-gray-100 px-1 dark:bg-gray-700">GET /services</code> for what
                        you pay. The response tells you which lookup ran, in
                        <code class="rounded bg-gray-100 px-1 dark:bg-gray-700">method</code>.
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
                                <tr><td class="px-4 py-2"><code>nin</code></td><td class="px-4 py-2">to look up by NIN</td><td class="px-4 py-2">exactly 11 digits</td></tr>
                                <tr><td class="px-4 py-2"><code>phone</code></td><td class="px-4 py-2">to look up by phone</td><td class="px-4 py-2">exactly 11 digits</td></tr>
                                <tr><td class="px-4 py-2"><code>first_name</code>, <code>last_name</code></td><td class="px-4 py-2">to look up by details</td><td class="px-4 py-2">2–100 characters</td></tr>
                                <tr><td class="px-4 py-2"><code>gender</code></td><td class="px-4 py-2">with the above</td><td class="px-4 py-2"><code>M</code> or <code>F</code></td></tr>
                                <tr><td class="px-4 py-2"><code>date_of_birth</code></td><td class="px-4 py-2">with the above</td><td class="px-4 py-2"><code>YYYY-MM-DD</code></td></tr>
                            </tbody>
                        </table>
                    </div>

                    <p class="mt-3 text-sm text-gray-700 dark:text-gray-300">
                        Send one identifier per call. If both a <code>nin</code> and a <code>phone</code> arrive we use
                        the NIN, since it is the stronger of the two. A body with none of them is rejected
                        <code>422</code> without charging you.
                    </p>

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
                    <p class="mt-1 text-sm text-gray-700 dark:text-gray-300">
                        All three lookups return the same record — the full enrolment, not a summary. Every provider's
                        reply is normalized to these names, so switching provider behind the scenes never changes the
                        shape you parse.
                    </p>
                    <pre class="mt-2 overflow-x-auto rounded-lg bg-gray-800 p-4 text-xs text-gray-100"><code>{{ ninSuccess }}</code></pre>

                    <div class="mt-4 rounded-lg border-l-4 border-amber-400 bg-amber-50 p-4 dark:bg-amber-900/20">
                        <p class="text-sm text-amber-800 dark:text-amber-200">
                            <strong>Fields are present only when the provider returned them.</strong> Nothing empty is
                            sent, so a missing key means "not on file", not an error — read every field defensively
                            rather than assuming it exists. The set above is what a complete NIMC record looks like;
                            older enrolments carry less.
                        </p>
                    </div>

                    <h3 class="mt-5 text-sm font-semibold text-gray-900 dark:text-white">Field reference</h3>
                    <div class="mt-2 overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-4 py-2 text-left font-medium text-gray-700 dark:text-gray-200">Group</th>
                                    <th class="px-4 py-2 text-left font-medium text-gray-700 dark:text-gray-200">Fields</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700 text-gray-700 dark:text-gray-300">
                                <tr><td class="px-4 py-2 font-medium">Identifiers</td><td class="px-4 py-2"><code>nin</code>, <code>vnin</code>, <code>tracking_id</code>, <code>central_id</code></td></tr>
                                <tr><td class="px-4 py-2 font-medium">Name</td><td class="px-4 py-2"><code>first_name</code>, <code>middle_name</code>, <code>last_name</code>, <code>full_name</code>, <code>other_names</code>, <code>title</code></td></tr>
                                <tr><td class="px-4 py-2 font-medium">Person</td><td class="px-4 py-2"><code>gender</code>, <code>date_of_birth</code>, <code>marital_status</code>, <code>height</code>, <code>religion</code>, <code>profession</code>, <code>employment_status</code>, <code>education_level</code>, <code>spoken_language</code>, <code>nationality</code></td></tr>
                                <tr><td class="px-4 py-2 font-medium">Contact</td><td class="px-4 py-2"><code>phone</code>, <code>phone2</code>, <code>email</code></td></tr>
                                <tr><td class="px-4 py-2 font-medium">Origin</td><td class="px-4 py-2"><code>birth_state</code>, <code>birth_lga</code>, <code>birth_country</code>, <code>state_of_origin</code>, <code>lga_of_origin</code>, <code>place_of_origin</code></td></tr>
                                <tr><td class="px-4 py-2 font-medium">Residence</td><td class="px-4 py-2"><code>residence_address</code>, <code>residence_town</code>, <code>residence_lga</code>, <code>residence_state</code>, <code>residence_status</code></td></tr>
                                <tr><td class="px-4 py-2 font-medium">Next of kin</td><td class="px-4 py-2"><code>nok_first_name</code>, <code>nok_middle_name</code>, <code>nok_last_name</code>, <code>nok_address</code>, <code>nok_town</code>, <code>nok_lga</code>, <code>nok_state</code></td></tr>
                                <tr><td class="px-4 py-2 font-medium">Media</td><td class="px-4 py-2"><code>photo</code>, <code>signature</code> — raw base64 JPEG, no <code>data:</code> prefix</td></tr>
                                <tr><td class="px-4 py-2 font-medium">Ours</td><td class="px-4 py-2"><code>provider</code>, <code>validation_id</code> — quote these to support</td></tr>
                            </tbody>
                        </table>
                    </div>

                    <p class="mt-4 text-sm text-gray-700 dark:text-gray-300">
                        Values are cleaned on the way out, whatever the provider sent:
                        <code class="rounded bg-gray-100 px-1 dark:bg-gray-700">date_of_birth</code> is always
                        <code>YYYY-MM-DD</code>, <code class="rounded bg-gray-100 px-1 dark:bg-gray-700">gender</code>
                        is always <code>MALE</code> or <code>FEMALE</code>, and phone numbers are always the local
                        <code>0</code>-prefixed form.
                    </p>

                    <h3 class="mt-5 text-sm font-semibold text-gray-900 dark:text-white">Legacy aliases</h3>
                    <p class="mt-1 text-sm text-gray-700 dark:text-gray-300">
                        The record also repeats some fields under the older NIMC spellings, so integrations written
                        against those keep working. They are duplicates of the names above —
                        <strong>build against the canonical names</strong>; treat these as a compatibility shim.
                    </p>
                    <pre class="mt-2 overflow-x-auto rounded-lg bg-gray-800 p-4 text-xs text-gray-100"><code>{{ ninAliases }}</code></pre>

                    <h3 class="mt-5 text-sm font-semibold text-gray-900 dark:text-white">Failure</h3>
                    <pre class="mt-2 overflow-x-auto rounded-lg bg-gray-800 p-4 text-xs text-gray-100"><code>{{ ninError }}</code></pre>
                </section>

                <!-- Validation -->
                <section id="validation" class="scroll-mt-8">
                    <div class="flex items-baseline gap-3">
                        <h2 class="text-xl font-bold text-gray-900 dark:text-white">Validate a NIN</h2>
                        <code class="rounded bg-blue-100 px-2 py-0.5 text-xs font-semibold text-blue-800 dark:bg-blue-900 dark:text-blue-200">POST /nin/validate</code>
                    </div>
                    <p class="mt-2 text-sm leading-relaxed text-gray-700 dark:text-gray-300">
                        Files a validation request against the NIMC register — a correction job for a record that is
                        wrong or incomplete. Like <a href="#ipe" class="text-indigo-600 hover:underline dark:text-indigo-400">IPE clearance</a>
                        it is a <strong>submission, not a lookup</strong>: it returns <code>202</code> immediately and the
                        real outcome arrives days later. Priced separately as
                        <code class="rounded bg-gray-100 px-1 dark:bg-gray-700">nin.validation</code>.
                    </p>

                    <div class="mt-4 rounded-lg border-l-4 border-amber-400 bg-amber-50 p-4 dark:bg-amber-900/20">
                        <p class="text-sm text-amber-900 dark:text-amber-200">
                            <strong>This is not a NIN lookup.</strong> If you want the record — name, date of birth, photo,
                            a slip to render — that is
                            <a href="#nin" class="font-semibold underline">POST /nin/verify</a>, and it answers in seconds.
                            Validation does not return a record at all; it returns a job you poll. Reaching for it as a KYC
                            gate will hang your sign-up flow for a week.
                        </p>
                    </div>

                    <div class="mt-4 rounded-lg border-l-4 border-indigo-400 bg-indigo-50 p-4 dark:bg-indigo-900/20">
                        <p class="text-sm text-indigo-800 dark:text-indigo-300">
                            <strong>How long?</strong> Typically <strong>3 days to a week</strong>. Poll once or twice a
                            day — polling is free, but nothing changes minute to minute and there is no webhook. Build for
                            an answer that arrives long after the customer has closed your page.
                        </p>
                    </div>

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
                                <tr><td class="px-4 py-2"><code>nin</code></td><td class="px-4 py-2">yes</td><td class="px-4 py-2">exactly 11 digits</td></tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="relative mt-4">
                        <button @click="copy(snippets.curlValidate, 'validate')" class="absolute right-2 top-2 rounded bg-gray-700 px-2 py-1 text-xs text-gray-200 hover:bg-gray-600">{{ copied === 'validate' ? 'Copied' : 'Copy' }}</button>
                        <pre class="overflow-x-auto rounded-lg bg-gray-900 p-4 text-xs text-gray-100"><code>{{ snippets.curlValidate }}</code></pre>
                    </div>

                    <h3 class="mt-5 text-sm font-semibold text-gray-900 dark:text-white">Accepted — <code>202</code></h3>
                    <p class="mt-1 text-sm text-gray-700 dark:text-gray-300">
                        <code>status</code> is <code>processing</code>, never <code>success</code>. The provider has taken
                        the job, nothing more — no part of this response says the validation will succeed. Store the
                        <code>id</code>; it is how you read the outcome back.
                    </p>
                    <pre class="mt-2 overflow-x-auto rounded-lg bg-gray-800 p-4 text-xs text-gray-100"><code>{{ validationAccepted }}</code></pre>

                    <div class="mt-6 rounded-lg border-l-4 border-red-400 bg-red-50 p-4 dark:bg-red-900/20">
                        <h3 class="text-sm font-semibold text-red-900 dark:text-red-200">Never retry a validation automatically</h3>
                        <p class="mt-2 text-sm text-red-800 dark:text-red-300">
                            Same rule as IPE: a retry files the same job twice and you are charged twice. If a call times
                            out on your side, <strong>read the submission back</strong> with
                            <code>GET /nin/validate</code> before doing anything else. This is also why we never fail over
                            to a second provider here, the way we do for lookups.
                        </p>
                    </div>

                    <h3 class="mt-6 text-sm font-semibold text-gray-900 dark:text-white">Unconfirmed — <code>202</code></h3>
                    <p class="mt-1 text-sm text-gray-700 dark:text-gray-300">
                        Distinguished from the above by <code>status: "unconfirmed"</code>. The provider neither accepted
                        nor rejected it. <strong>You are refunded</strong>, but it may still have been filed, so it stays
                        on your record as <code>processing</code>. Treat as "unknown", never as "failed".
                    </p>
                    <pre class="mt-2 overflow-x-auto rounded-lg bg-gray-800 p-4 text-xs text-gray-100"><code>{{ validationUnconfirmed }}</code></pre>

                    <h3 class="mt-6 text-sm font-semibold text-gray-900 dark:text-white">Rejected — <code>422</code></h3>
                    <p class="mt-1 text-sm text-gray-700 dark:text-gray-300">
                        The provider refused the submission outright — you are refunded and no job exists. A
                        <code>502</code> means nobody answered at all, which is not the same thing: nothing was filed and
                        nothing was charged, so that one is safe to send again.
                    </p>
                    <pre class="mt-2 overflow-x-auto rounded-lg bg-gray-800 p-4 text-xs text-gray-100"><code>{{ validationError }}</code></pre>

                    <div class="mt-8 flex items-baseline gap-3">
                        <h3 class="text-base font-bold text-gray-900 dark:text-white">Read a submission back</h3>
                        <code class="rounded bg-green-100 px-2 py-0.5 text-xs font-semibold text-green-800 dark:bg-green-900 dark:text-green-200">GET /nin/validate/&#123;id&#125;</code>
                    </div>
                    <p class="mt-2 text-sm text-gray-700 dark:text-gray-300">
                        Free to call, and scoped to your own submissions. Takes either the <code>id</code> we returned or
                        the <code>nin</code> you sent — with a NIN you get the most recent submission for it.
                        <code class="rounded bg-gray-100 px-1 dark:bg-gray-700">GET /nin/validate</code> lists yours,
                        newest first (<code>?limit=</code>, default 50, max 200).
                    </p>
                    <p class="mt-2 text-sm text-gray-700 dark:text-gray-300">
                        Reading one back <strong>asks the provider</strong> while the job is still open, so this is a live
                        call, not a cache. Once it reaches <code>completed</code> or <code>failed</code> it is settled and
                        we stop asking — you can keep polling safely, but the answer will not change again.
                    </p>

                    <div class="relative mt-3">
                        <button @click="copy(snippets.curlValidateStatus, 'validateStatus')" class="absolute right-2 top-2 rounded bg-gray-700 px-2 py-1 text-xs text-gray-200 hover:bg-gray-600">{{ copied === 'validateStatus' ? 'Copied' : 'Copy' }}</button>
                        <pre class="overflow-x-auto rounded-lg bg-gray-900 p-4 text-xs text-gray-100"><code>{{ snippets.curlValidateStatus }}</code></pre>
                    </div>
                    <pre class="mt-2 overflow-x-auto rounded-lg bg-gray-800 p-4 text-xs text-gray-100"><code>{{ validationStatus }}</code></pre>

                    <div class="mt-4 overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-4 py-2 text-left font-medium text-gray-700 dark:text-gray-200">Status</th>
                                    <th class="px-4 py-2 text-left font-medium text-gray-700 dark:text-gray-200">Meaning</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700 text-gray-700 dark:text-gray-300">
                                <tr><td class="px-4 py-2"><code>processing</code></td><td class="px-4 py-2">With the provider. Keep polling — usually 3 days to a week</td></tr>
                                <tr><td class="px-4 py-2"><code>completed</code></td><td class="px-4 py-2">Validated. <code>comment</code> carries the provider's wording</td></tr>
                                <tr><td class="px-4 py-2"><code>failed</code></td><td class="px-4 py-2">Rejected upstream. <code>comment</code> says why; contact us about a refund</td></tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <!-- IPE -->
                <section id="ipe" class="scroll-mt-8">
                    <div class="flex items-baseline gap-3">
                        <h2 class="text-xl font-bold text-gray-900 dark:text-white">IPE clearance</h2>
                        <code class="rounded bg-blue-100 px-2 py-0.5 text-xs font-semibold text-blue-800 dark:bg-blue-900 dark:text-blue-200">POST /nin/ipe</code>
                    </div>
                    <p class="mt-2 text-sm leading-relaxed text-gray-700 dark:text-gray-300">
                        Files an IPE (Identity Proof of Enrolment) clearance for an enrolment tracking id. Like
                        <a href="#validation" class="text-indigo-600 hover:underline dark:text-indigo-400">NIN validation</a>
                        this is a <strong>submission, not a lookup</strong>: it creates work upstream, so it returns
                        <code>201</code> immediately and you read the outcome back later.
                    </p>
                    <p class="mt-2 text-sm text-gray-700 dark:text-gray-300">
                        The tracking id is the entire request. You do not send a NIN — producing one is what the
                        clearance is for.
                    </p>

                    <div class="mt-4 rounded-lg border-l-4 border-indigo-400 bg-indigo-50 p-4 dark:bg-indigo-900/20">
                        <p class="text-sm text-indigo-800 dark:text-indigo-300">
                            <strong>How long?</strong> Typically <strong>30 minutes to 3 hours</strong> — much faster than
                            validation, but still far too slow to hold a request open for. Poll every 15–30 minutes.
                        </p>
                    </div>

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
                                <tr><td class="px-4 py-2"><code>tracking_id</code></td><td class="px-4 py-2">yes</td><td class="px-4 py-2">exactly 15 characters. <code>trkid</code> is accepted as an alias</td></tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="relative mt-4">
                        <button @click="copy(snippets.curlIpe, 'ipe')" class="absolute right-2 top-2 rounded bg-gray-700 px-2 py-1 text-xs text-gray-200 hover:bg-gray-600">{{ copied === 'ipe' ? 'Copied' : 'Copy' }}</button>
                        <pre class="overflow-x-auto rounded-lg bg-gray-900 p-4 text-xs text-gray-100"><code>{{ snippets.curlIpe }}</code></pre>
                    </div>

                    <h3 class="mt-5 text-sm font-semibold text-gray-900 dark:text-white">Accepted — <code>201</code></h3>
                    <pre class="mt-2 overflow-x-auto rounded-lg bg-gray-800 p-4 text-xs text-gray-100"><code>{{ ipeSuccess }}</code></pre>

                    <div class="mt-6 rounded-lg border-l-4 border-red-400 bg-red-50 p-4 dark:bg-red-900/20">
                        <h3 class="text-sm font-semibold text-red-900 dark:text-red-200">Never retry an IPE submission automatically</h3>
                        <p class="mt-2 text-sm text-red-800 dark:text-red-300">
                            A retry can file the same clearance twice, and the second one is not free to undo. If a call
                            times out on your side, <strong>read the submission back</strong> with
                            <code>GET /nin/ipe</code> before doing anything else. This is also why we never fail over to
                            a second provider on an unclear reply, the way we do for lookups.
                        </p>
                    </div>

                    <h3 class="mt-6 text-sm font-semibold text-gray-900 dark:text-white">Unconfirmed — <code>202</code></h3>
                    <p class="mt-1 text-sm text-gray-700 dark:text-gray-300">
                        The provider neither accepted nor rejected it. <strong>You are refunded</strong>, but the
                        submission may still have been filed, so it is kept on your record as
                        <code>processing</code>. Poll it, or send us the <code>reference</code> and we will reconcile it.
                        Treat this as "unknown", never as "failed".
                    </p>
                    <pre class="mt-2 overflow-x-auto rounded-lg bg-gray-800 p-4 text-xs text-gray-100"><code>{{ ipeUnconfirmed }}</code></pre>

                    <div class="mt-8 flex items-baseline gap-3">
                        <h3 class="text-base font-bold text-gray-900 dark:text-white">Read a submission back</h3>
                        <code class="rounded bg-green-100 px-2 py-0.5 text-xs font-semibold text-green-800 dark:bg-green-900 dark:text-green-200">GET /nin/ipe/&#123;id&#125;</code>
                    </div>
                    <p class="mt-2 text-sm text-gray-700 dark:text-gray-300">
                        Free to call, and scoped to your own submissions. Takes either the <code>id</code> we returned or
                        the <code>tracking_id</code> you sent — with a tracking id you get the most recent submission for
                        it. <code class="rounded bg-gray-100 px-1 dark:bg-gray-700">GET /nin/ipe</code> lists yours,
                        newest first (<code>?limit=</code>, default 50, max 200).
                    </p>
                    <p class="mt-2 text-sm text-gray-700 dark:text-gray-300">
                        Reading one back <strong>asks the provider</strong> while the clearance is still open, so this is a
                        live call, not a cache. Once it reaches <code>completed</code> or <code>failed</code> it is settled
                        and we stop asking — you can keep polling safely, but the answer will not change again.
                    </p>

                    <div class="relative mt-3">
                        <button @click="copy(snippets.curlIpeStatus, 'ipeStatus')" class="absolute right-2 top-2 rounded bg-gray-700 px-2 py-1 text-xs text-gray-200 hover:bg-gray-600">{{ copied === 'ipeStatus' ? 'Copied' : 'Copy' }}</button>
                        <pre class="overflow-x-auto rounded-lg bg-gray-900 p-4 text-xs text-gray-100"><code>{{ snippets.curlIpeStatus }}</code></pre>
                    </div>
                    <pre class="mt-2 overflow-x-auto rounded-lg bg-gray-800 p-4 text-xs text-gray-100"><code>{{ ipeStatus }}</code></pre>

                    <div class="mt-4 overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-4 py-2 text-left font-medium text-gray-700 dark:text-gray-200">Status</th>
                                    <th class="px-4 py-2 text-left font-medium text-gray-700 dark:text-gray-200">Meaning</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700 text-gray-700 dark:text-gray-300">
                                <tr><td class="px-4 py-2"><code>processing</code></td><td class="px-4 py-2">With the provider. Keep polling — usually 30 minutes to 3 hours</td></tr>
                                <tr><td class="px-4 py-2"><code>completed</code></td><td class="px-4 py-2">Cleared. <code>comment</code> carries the provider's wording</td></tr>
                                <tr><td class="px-4 py-2"><code>failed</code></td><td class="px-4 py-2">Rejected upstream. <code>comment</code> says why; contact us about a refund</td></tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <!-- BVN -->
                <section id="bvn" class="scroll-mt-8">
                    <div class="flex items-baseline gap-3">
                        <h2 class="text-xl font-bold text-gray-900 dark:text-white">Verify a BVN</h2>
                        <code class="rounded bg-blue-100 px-2 py-0.5 text-xs font-semibold text-blue-800 dark:bg-blue-900 dark:text-blue-200">POST /bvn/verify</code>
                    </div>
                    <p class="mt-2 text-sm text-gray-700 dark:text-gray-300">
                        The BVN is all you need. <code>photo</code> comes back as raw base64 JPEG with no
                        <code>data:</code> prefix.
                    </p>

                    <div class="relative mt-3">
                        <button @click="copy(snippets.curlBvn, 'bvn')" class="absolute right-2 top-2 rounded bg-gray-700 px-2 py-1 text-xs text-gray-200 hover:bg-gray-600">{{ copied === 'bvn' ? 'Copied' : 'Copy' }}</button>
                        <pre class="overflow-x-auto rounded-lg bg-gray-900 p-4 text-xs text-gray-100"><code>{{ snippets.curlBvn }}</code></pre>
                    </div>

                    <h3 class="mt-5 text-sm font-semibold text-gray-900 dark:text-white">Choosing a slip <span class="font-normal text-gray-500 dark:text-gray-400">— optional</span></h3>
                    <p class="mt-1 text-sm text-gray-700 dark:text-gray-300">
                        <code class="rounded bg-gray-100 px-1 dark:bg-gray-700">slip_type</code> selects how much detail
                        you get back, and what you pay. Omit it and you get <code>premium</code>, the full slip — the
                        response echoes <code>slip_type</code> so you always know which one you were billed for.
                    </p>

                    <div class="mt-3 overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-4 py-2 text-left font-medium text-gray-700 dark:text-gray-200">slip_type</th>
                                    <th class="px-4 py-2 text-left font-medium text-gray-700 dark:text-gray-200">Priced as</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700 text-gray-700 dark:text-gray-300">
                                <tr><td class="px-4 py-2"><code>premium</code> <span class="text-xs text-gray-500 dark:text-gray-400">(default)</span></td><td class="px-4 py-2"><code>bvn.search.premium</code></td></tr>
                                <tr><td class="px-4 py-2"><code>standard</code></td><td class="px-4 py-2"><code>bvn.search.standard</code></td></tr>
                                <tr><td class="px-4 py-2"><code>regular</code></td><td class="px-4 py-2"><code>bvn.search.regular</code></td></tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="relative mt-3">
                        <button @click="copy(snippets.curlBvnSlip, 'bvnSlip')" class="absolute right-2 top-2 rounded bg-gray-700 px-2 py-1 text-xs text-gray-200 hover:bg-gray-600">{{ copied === 'bvnSlip' ? 'Copied' : 'Copy' }}</button>
                        <pre class="overflow-x-auto rounded-lg bg-gray-900 p-4 text-xs text-gray-100"><code>{{ snippets.curlBvnSlip }}</code></pre>
                    </div>

                    <h3 class="mt-5 text-sm font-semibold text-gray-900 dark:text-white">Success</h3>
                    <p class="mt-1 text-sm text-gray-700 dark:text-gray-300">
                        A BVN record is a fixed set of 18 fields — the enrolment details a slip is printed from,
                        including which bank and branch registered the customer and when.
                    </p>
                    <pre class="mt-2 overflow-x-auto rounded-lg bg-gray-800 p-4 text-xs text-gray-100"><code>{{ bvnSuccess }}</code></pre>

                    <div class="mt-4 rounded-lg border-l-4 border-indigo-400 bg-indigo-50 p-4 dark:bg-indigo-900/20">
                        <p class="text-sm text-indigo-800 dark:text-indigo-300">
                            <strong>BVN differs from NIN here.</strong> Every one of the 18 keys is always present; a
                            field the provider did not supply comes back as <code>null</code> rather than being left
                            out. So check for <code>null</code>, not for a missing key. Note also
                            <code>residential_Address</code> — that capital <code>A</code> is deliberate and stable.
                        </p>
                    </div>

                    <p class="mt-4 text-sm text-gray-700 dark:text-gray-300">
                        <code class="rounded bg-gray-100 px-1 dark:bg-gray-700">enrollment_bank</code> and
                        <code class="rounded bg-gray-100 px-1 dark:bg-gray-700">enrollment_bank_branch</code> come back
                        as <strong>names</strong>, not the CBN codes the provider reports — we resolve both for you
                        against the same institution table. A code that maps to no institution reads
                        <code>Agency enrollment</code>; a value already sent as text is passed through unchanged.
                    </p>
                </section>

                <!-- BVN records -->
                <section id="bvn-records" class="scroll-mt-8">
                    <div class="flex items-baseline gap-3">
                        <h2 class="text-xl font-bold text-gray-900 dark:text-white">Search enrolment records</h2>
                        <code class="rounded bg-green-100 px-2 py-0.5 text-xs font-semibold text-green-800 dark:bg-green-900 dark:text-green-200">GET /bvn/records</code>
                    </div>
                    <p class="mt-2 text-sm leading-relaxed text-gray-700 dark:text-gray-300">
                        Searches the enrolment records we hold — captures made through our agent network — by
                        ticket id or by the agent who took them. <strong>Free, and it is our own data</strong>, not a
                        provider lookup, so you can poll it as often as you like and build a dashboard on top of it.
                    </p>

                    <div class="mt-4 rounded-lg border-l-4 border-indigo-400 bg-indigo-50 p-4 dark:bg-indigo-900/20">
                        <p class="text-sm text-indigo-800 dark:text-indigo-300">
                            This is the one BVN endpoint that is <strong>not scoped to your account</strong>. An
                            enrolment record belongs to the enrolment, not to whoever searched for it, so you see the
                            same rows the web BVN Records screen does.
                        </p>
                    </div>

                    <div class="mt-4 overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-4 py-2 text-left font-medium text-gray-700 dark:text-gray-200">Parameter</th>
                                    <th class="px-4 py-2 text-left font-medium text-gray-700 dark:text-gray-200">Required</th>
                                    <th class="px-4 py-2 text-left font-medium text-gray-700 dark:text-gray-200">Notes</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700 text-gray-700 dark:text-gray-300">
                                <tr><td class="px-4 py-2"><code>query</code></td><td class="px-4 py-2">yes</td><td class="px-4 py-2">at least 6 characters; matched as a partial</td></tr>
                                <tr><td class="px-4 py-2"><code>type</code></td><td class="px-4 py-2">no</td><td class="px-4 py-2"><code>ticket_id</code> (default) or <code>enroller_id</code></td></tr>
                                <tr><td class="px-4 py-2"><code>limit</code></td><td class="px-4 py-2">no</td><td class="px-4 py-2">default 50, max 200</td></tr>
                            </tbody>
                        </table>
                    </div>

                    <p class="mt-3 text-sm text-gray-700 dark:text-gray-300">
                        The six-character floor is deliberate: a two-character query matches most of the table, and a
                        page of noise is not a search. A query shorter than that is a <code>422</code>.
                    </p>

                    <div class="relative mt-4">
                        <button @click="copy(snippets.curlRecords, 'records')" class="absolute right-2 top-2 rounded bg-gray-700 px-2 py-1 text-xs text-gray-200 hover:bg-gray-600">{{ copied === 'records' ? 'Copied' : 'Copy' }}</button>
                        <pre class="overflow-x-auto rounded-lg bg-gray-900 p-4 text-xs text-gray-100"><code>{{ snippets.curlRecords }}</code></pre>
                    </div>

                    <pre class="mt-2 overflow-x-auto rounded-lg bg-gray-800 p-4 text-xs text-gray-100"><code>{{ recordsSuccess }}</code></pre>

                    <p class="mt-3 text-sm text-gray-700 dark:text-gray-300">
                        A search that matches nothing is still a <code>200</code> with
                        <code>count: 0</code> — not a <code>404</code>. Nothing found is an answer, not an error.
                    </p>
                </section>

                <!-- Staff-fulfilled services: shared preamble -->
                <section class="scroll-mt-8">
                    <div class="rounded-lg border-l-4 border-amber-400 bg-amber-50 p-4 dark:bg-amber-900/20">
                        <h2 class="text-sm font-semibold text-amber-900 dark:text-amber-200">The next three are worked by hand</h2>
                        <p class="mt-2 text-sm text-amber-800 dark:text-amber-300">
                            BVN modification, retrieval and agent onboarding are <strong>requests our staff process</strong>,
                            not provider calls. That changes what a <code>201</code> means: we have taken your fee and
                            filed the request, and a human moves it forward over the following days. Nothing about the
                            response says the change will be accepted.
                        </p>
                        <ul class="mt-2 list-inside list-disc space-y-1 text-sm text-amber-800 dark:text-amber-300">
                            <li>There is no <code>unconfirmed</code> outcome — no upstream call can half-happen. You are either charged and filed, or refused and not charged.</li>
                            <li>There is no webhook. Poll the read-back endpoint, or show your customer the status from your own copy of it.</li>
                            <li>A request rejected by our staff after filing is <strong>not auto-refunded</strong> — the <code>comment</code> says why, and support settles it.</li>
                            <li>Each has a <code>GET</code> list (<code>?limit=</code> default 50, max 200, plus <code>?status=</code>) and a <code>GET /&#123;id&#125;</code>, both free and scoped to your own requests.</li>
                        </ul>
                    </div>
                </section>

                <!-- BVN modification -->
                <section id="bvn-modification" class="scroll-mt-8">
                    <div class="flex items-baseline gap-3">
                        <h2 class="text-xl font-bold text-gray-900 dark:text-white">Modify a BVN</h2>
                        <code class="rounded bg-blue-100 px-2 py-0.5 text-xs font-semibold text-blue-800 dark:bg-blue-900 dark:text-blue-200">POST /bvn/modification</code>
                    </div>
                    <p class="mt-2 text-sm leading-relaxed text-gray-700 dark:text-gray-300">
                        Files a correction to a BVN record — a name, a date of birth, a phone number, or a combination.
                        You send what the record says now and what it should say, plus the NIN slip that backs the
                        change up.
                    </p>

                    <h3 class="mt-5 text-sm font-semibold text-gray-900 dark:text-white">Service types</h3>
                    <p class="mt-1 text-sm text-gray-700 dark:text-gray-300">
                        <strong>Each combination is its own product at its own price</strong> — one call changing name
                        and date of birth together is cheaper than two calls. Pick the type that covers everything you
                        are changing, and check <code class="rounded bg-gray-100 px-1 dark:bg-gray-700">GET /services</code>
                        for what you pay.
                    </p>

                    <div class="mt-3 overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-4 py-2 text-left font-medium text-gray-700 dark:text-gray-200">service_type</th>
                                    <th class="px-4 py-2 text-left font-medium text-gray-700 dark:text-gray-200">Also send</th>
                                    <th class="px-4 py-2 text-left font-medium text-gray-700 dark:text-gray-200">Priced as</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700 text-gray-700 dark:text-gray-300">
                                <tr><td class="px-4 py-2"><code>modify-name</code></td><td class="px-4 py-2">the name pair</td><td class="px-4 py-2"><code>bvn.mod.name</code></td></tr>
                                <tr><td class="px-4 py-2"><code>modify-dob</code></td><td class="px-4 py-2">the DOB pair</td><td class="px-4 py-2"><code>bvn.mod.dob</code></td></tr>
                                <tr><td class="px-4 py-2"><code>modify-phone</code></td><td class="px-4 py-2">the phone pair</td><td class="px-4 py-2"><code>bvn.mod.phone</code></td></tr>
                                <tr><td class="px-4 py-2"><code>modify-name-dob</code></td><td class="px-4 py-2">name + DOB pairs</td><td class="px-4 py-2"><code>bvn.mod.name_dob</code></td></tr>
                                <tr><td class="px-4 py-2"><code>modify-name-dob-phone</code></td><td class="px-4 py-2">all three pairs</td><td class="px-4 py-2"><code>bvn.mod.name_dob_phone</code></td></tr>
                            </tbody>
                        </table>
                    </div>

                    <h3 class="mt-5 text-sm font-semibold text-gray-900 dark:text-white">Fields</h3>
                    <div class="mt-2 overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-4 py-2 text-left font-medium text-gray-700 dark:text-gray-200">Field</th>
                                    <th class="px-4 py-2 text-left font-medium text-gray-700 dark:text-gray-200">Required</th>
                                    <th class="px-4 py-2 text-left font-medium text-gray-700 dark:text-gray-200">Notes</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700 text-gray-700 dark:text-gray-300">
                                <tr><td class="px-4 py-2"><code>service_type</code></td><td class="px-4 py-2">yes</td><td class="px-4 py-2">one of the five above</td></tr>
                                <tr><td class="px-4 py-2"><code>bvn</code></td><td class="px-4 py-2">yes</td><td class="px-4 py-2">exactly 11 digits</td></tr>
                                <tr><td class="px-4 py-2"><code>nin</code></td><td class="px-4 py-2">yes</td><td class="px-4 py-2">exactly 11 digits</td></tr>
                                <tr><td class="px-4 py-2"><code>nin_slip</code></td><td class="px-4 py-2">yes</td><td class="px-4 py-2">JPEG, PNG or PDF — see below</td></tr>
                                <tr><td class="px-4 py-2"><code>old_first_name</code>, <code>old_last_name</code>, <code>new_first_name</code>, <code>new_last_name</code></td><td class="px-4 py-2">name types</td><td class="px-4 py-2"><code>old_middle_name</code> / <code>new_middle_name</code> optional</td></tr>
                                <tr><td class="px-4 py-2"><code>old_dob</code>, <code>new_dob</code></td><td class="px-4 py-2">DOB types</td><td class="px-4 py-2"><code>YYYY-MM-DD</code></td></tr>
                                <tr><td class="px-4 py-2"><code>old_phone</code>, <code>new_phone</code></td><td class="px-4 py-2">phone types</td><td class="px-4 py-2">as enrolled</td></tr>
                            </tbody>
                        </table>
                    </div>

                    <p class="mt-3 text-sm text-gray-700 dark:text-gray-300">
                        Fields that do not belong to the service type are ignored rather than recorded — sending a name
                        alongside <code>modify-phone</code> will not quietly file a name change too.
                    </p>

                    <h3 class="mt-5 text-sm font-semibold text-gray-900 dark:text-white">Sending the NIN slip</h3>
                    <p class="mt-1 text-sm text-gray-700 dark:text-gray-300">
                        Send <code class="rounded bg-gray-100 px-1 dark:bg-gray-700">nin_slip</code> as
                        <strong>base64 in your JSON body</strong> — a bare string or a <code>data:</code> URI, both are
                        accepted. If you would rather post <code>multipart/form-data</code>, send it as a file under the
                        same name. Either way the limit is <strong>5MB decoded</strong>, and we sniff the real type from
                        the bytes: a <code>.pdf</code> filename over something else is rejected <code>422</code>
                        without charging you.
                    </p>

                    <div class="relative mt-4">
                        <button @click="copy(snippets.curlModification, 'modification')" class="absolute right-2 top-2 rounded bg-gray-700 px-2 py-1 text-xs text-gray-200 hover:bg-gray-600">{{ copied === 'modification' ? 'Copied' : 'Copy' }}</button>
                        <pre class="overflow-x-auto rounded-lg bg-gray-900 p-4 text-xs text-gray-100"><code>{{ snippets.curlModification }}</code></pre>
                    </div>

                    <h3 class="mt-5 text-sm font-semibold text-gray-900 dark:text-white">Accepted — <code>201</code></h3>
                    <pre class="mt-2 overflow-x-auto rounded-lg bg-gray-800 p-4 text-xs text-gray-100"><code>{{ modificationAccepted }}</code></pre>

                    <h3 class="mt-5 text-sm font-semibold text-gray-900 dark:text-white">Missing fields — <code>422</code></h3>
                    <p class="mt-1 text-sm text-gray-700 dark:text-gray-300">
                        Checked before you are charged, and it names what is missing so you can fix it in one pass
                        rather than one field at a time.
                    </p>
                    <pre class="mt-2 overflow-x-auto rounded-lg bg-gray-800 p-4 text-xs text-gray-100"><code>{{ modificationMissing }}</code></pre>

                    <div class="mt-8 flex items-baseline gap-3">
                        <h3 class="text-base font-bold text-gray-900 dark:text-white">Read a request back</h3>
                        <code class="rounded bg-green-100 px-2 py-0.5 text-xs font-semibold text-green-800 dark:bg-green-900 dark:text-green-200">GET /bvn/modification/&#123;id&#125;</code>
                    </div>
                    <p class="mt-2 text-sm text-gray-700 dark:text-gray-300">
                        Takes the <code>id</code> we returned or the <code>bvn</code> you sent — with a BVN you get the
                        most recent request for it.
                    </p>

                    <div class="relative mt-3">
                        <button @click="copy(snippets.curlModificationStatus, 'modificationStatus')" class="absolute right-2 top-2 rounded bg-gray-700 px-2 py-1 text-xs text-gray-200 hover:bg-gray-600">{{ copied === 'modificationStatus' ? 'Copied' : 'Copy' }}</button>
                        <pre class="overflow-x-auto rounded-lg bg-gray-900 p-4 text-xs text-gray-100"><code>{{ snippets.curlModificationStatus }}</code></pre>
                    </div>
                    <pre class="mt-2 overflow-x-auto rounded-lg bg-gray-800 p-4 text-xs text-gray-100"><code>{{ modificationStatus }}</code></pre>

                    <div class="mt-4 overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-4 py-2 text-left font-medium text-gray-700 dark:text-gray-200">Status</th>
                                    <th class="px-4 py-2 text-left font-medium text-gray-700 dark:text-gray-200">Meaning</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700 text-gray-700 dark:text-gray-300">
                                <tr><td class="px-4 py-2"><code>pending</code></td><td class="px-4 py-2">Filed, not yet picked up</td></tr>
                                <tr><td class="px-4 py-2"><code>picked</code></td><td class="px-4 py-2">Being worked on</td></tr>
                                <tr><td class="px-4 py-2"><code>modified</code></td><td class="px-4 py-2">Done — the record has been changed</td></tr>
                                <tr><td class="px-4 py-2"><code>rejected</code></td><td class="px-4 py-2">Not done. <code>comment</code> says why; contact us about a refund</td></tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <!-- BVN retrieval -->
                <section id="bvn-retrieval" class="scroll-mt-8">
                    <div class="flex items-baseline gap-3">
                        <h2 class="text-xl font-bold text-gray-900 dark:text-white">Retrieve a BVN</h2>
                        <code class="rounded bg-blue-100 px-2 py-0.5 text-xs font-semibold text-blue-800 dark:bg-blue-900 dark:text-blue-200">POST /bvn/retrieval</code>
                    </div>
                    <p class="mt-2 text-sm leading-relaxed text-gray-700 dark:text-gray-300">
                        Recovers the BVN behind an enrolment when the customer never received it. The 8-digit BMS ticket
                        id from the enrolment is the entire request. Priced as
                        <code class="rounded bg-gray-100 px-1 dark:bg-gray-700">bvn.retrieve.id</code>.
                    </p>

                    <div class="mt-4 rounded-lg border-l-4 border-amber-400 bg-amber-50 p-4 dark:bg-amber-900/20">
                        <p class="text-sm text-amber-900 dark:text-amber-200">
                            <strong>This is not a BVN lookup.</strong> If you have the BVN and want the record behind it,
                            that is <a href="#bvn" class="font-semibold underline">POST /bvn/verify</a>, and it answers in
                            seconds. Retrieval is the other direction — it produces a BVN nobody has — and a human does it.
                        </p>
                    </div>

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
                                <tr><td class="px-4 py-2"><code>ticket_id</code></td><td class="px-4 py-2">yes</td><td class="px-4 py-2">exactly 8 digits. <code>bms_id</code> is accepted as an alias</td></tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="relative mt-4">
                        <button @click="copy(snippets.curlRetrieval, 'retrieval')" class="absolute right-2 top-2 rounded bg-gray-700 px-2 py-1 text-xs text-gray-200 hover:bg-gray-600">{{ copied === 'retrieval' ? 'Copied' : 'Copy' }}</button>
                        <pre class="overflow-x-auto rounded-lg bg-gray-900 p-4 text-xs text-gray-100"><code>{{ snippets.curlRetrieval }}</code></pre>
                    </div>

                    <h3 class="mt-5 text-sm font-semibold text-gray-900 dark:text-white">Accepted — <code>201</code></h3>
                    <p class="mt-1 text-sm text-gray-700 dark:text-gray-300">
                        <code>bvn</code> is <code>null</code> here, and that is the point of the endpoint — it is what
                        you are waiting for, not something we withheld.
                    </p>
                    <pre class="mt-2 overflow-x-auto rounded-lg bg-gray-800 p-4 text-xs text-gray-100"><code>{{ retrievalAccepted }}</code></pre>

                    <div class="mt-8 flex items-baseline gap-3">
                        <h3 class="text-base font-bold text-gray-900 dark:text-white">Read a request back</h3>
                        <code class="rounded bg-green-100 px-2 py-0.5 text-xs font-semibold text-green-800 dark:bg-green-900 dark:text-green-200">GET /bvn/retrieval/&#123;id&#125;</code>
                    </div>
                    <p class="mt-2 text-sm text-gray-700 dark:text-gray-300">
                        Takes the <code>id</code> we returned or the <code>ticket_id</code> you sent. Poll it until
                        <code>status</code> is <code>completed</code> and <code>bvn</code> is filled in.
                    </p>

                    <div class="relative mt-3">
                        <button @click="copy(snippets.curlRetrievalStatus, 'retrievalStatus')" class="absolute right-2 top-2 rounded bg-gray-700 px-2 py-1 text-xs text-gray-200 hover:bg-gray-600">{{ copied === 'retrievalStatus' ? 'Copied' : 'Copy' }}</button>
                        <pre class="overflow-x-auto rounded-lg bg-gray-900 p-4 text-xs text-gray-100"><code>{{ snippets.curlRetrievalStatus }}</code></pre>
                    </div>
                    <pre class="mt-2 overflow-x-auto rounded-lg bg-gray-800 p-4 text-xs text-gray-100"><code>{{ retrievalStatus }}</code></pre>

                    <div class="mt-4 overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-4 py-2 text-left font-medium text-gray-700 dark:text-gray-200">Status</th>
                                    <th class="px-4 py-2 text-left font-medium text-gray-700 dark:text-gray-200">Meaning</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700 text-gray-700 dark:text-gray-300">
                                <tr><td class="px-4 py-2"><code>pending</code></td><td class="px-4 py-2">Filed, not yet picked up</td></tr>
                                <tr><td class="px-4 py-2"><code>processing</code></td><td class="px-4 py-2">Being worked on</td></tr>
                                <tr><td class="px-4 py-2"><code>completed</code></td><td class="px-4 py-2">Done — <code>bvn</code> carries the answer</td></tr>
                                <tr><td class="px-4 py-2"><code>rejected</code></td><td class="px-4 py-2">Not found or not retrievable. <code>comment</code> says why</td></tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <!-- BVN onboarding -->
                <section id="bvn-onboarding" class="scroll-mt-8">
                    <div class="flex items-baseline gap-3">
                        <h2 class="text-xl font-bold text-gray-900 dark:text-white">Onboard an agent</h2>
                        <code class="rounded bg-blue-100 px-2 py-0.5 text-xs font-semibold text-blue-800 dark:bg-blue-900 dark:text-blue-200">POST /bvn/onboarding</code>
                    </div>
                    <p class="mt-2 text-sm leading-relaxed text-gray-700 dark:text-gray-300">
                        Registers an agent for BVN enrolment on the SDK — the API form of the onboarding wizard. One
                        call carries the whole registration: who the agent is, where they work, and the account they
                        are settled to. Priced as
                        <code class="rounded bg-gray-100 px-1 dark:bg-gray-700">bvn.onboarding1</code>.
                    </p>

                    <div class="mt-4 overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-4 py-2 text-left font-medium text-gray-700 dark:text-gray-200">Group</th>
                                    <th class="px-4 py-2 text-left font-medium text-gray-700 dark:text-gray-200">Fields — all required</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700 text-gray-700 dark:text-gray-300">
                                <tr><td class="px-4 py-2 font-medium">Settlement</td><td class="px-4 py-2"><code>bank_name</code>, <code>account_number</code> (10 digits), <code>account_name</code></td></tr>
                                <tr><td class="px-4 py-2 font-medium">Agent</td><td class="px-4 py-2"><code>first_name</code>, <code>last_name</code>, <code>agent_bvn</code> (11 digits), <code>date_of_birth</code> (<code>YYYY-MM-DD</code>, in the past)</td></tr>
                                <tr><td class="px-4 py-2 font-medium">Contact</td><td class="px-4 py-2"><code>email</code>, <code>phone_number</code> (11+ digits)</td></tr>
                                <tr><td class="px-4 py-2 font-medium">Location</td><td class="px-4 py-2"><code>agent_location</code>, <code>address</code>, <code>state_of_residence</code>, <code>lga</code>, <code>zone</code></td></tr>
                            </tbody>
                        </table>
                    </div>

                    <p class="mt-3 text-sm text-gray-700 dark:text-gray-300">
                        <code class="rounded bg-gray-100 px-1 dark:bg-gray-700">zone</code> is one of
                        <code>north-central</code>, <code>north-east</code>, <code>north-west</code>,
                        <code>south-east</code>, <code>south-south</code>, <code>south-west</code>.
                    </p>

                    <div class="mt-4 rounded-lg border-l-4 border-green-400 bg-green-50 p-4 dark:bg-green-900/20">
                        <p class="text-sm text-green-800 dark:text-green-200">
                            <strong><code>email</code> and <code>phone_number</code> are unique across every
                            onboarding</strong>, not just yours — an agent is one person, and the same person registered
                            twice is a duplicate upstream whoever sent it. A repeat is <code>422</code> and is not
                            charged, which also makes a timed-out call safe to send again.
                        </p>
                    </div>

                    <div class="relative mt-4">
                        <button @click="copy(snippets.curlOnboarding, 'onboarding')" class="absolute right-2 top-2 rounded bg-gray-700 px-2 py-1 text-xs text-gray-200 hover:bg-gray-600">{{ copied === 'onboarding' ? 'Copied' : 'Copy' }}</button>
                        <pre class="overflow-x-auto rounded-lg bg-gray-900 p-4 text-xs text-gray-100"><code>{{ snippets.curlOnboarding }}</code></pre>
                    </div>

                    <h3 class="mt-5 text-sm font-semibold text-gray-900 dark:text-white">Accepted — <code>201</code></h3>
                    <pre class="mt-2 overflow-x-auto rounded-lg bg-gray-800 p-4 text-xs text-gray-100"><code>{{ onboardingAccepted }}</code></pre>

                    <div class="mt-8 flex items-baseline gap-3">
                        <h3 class="text-base font-bold text-gray-900 dark:text-white">Read a registration back</h3>
                        <code class="rounded bg-green-100 px-2 py-0.5 text-xs font-semibold text-green-800 dark:bg-green-900 dark:text-green-200">GET /bvn/onboarding/&#123;id&#125;</code>
                    </div>
                    <p class="mt-2 text-sm text-gray-700 dark:text-gray-300">
                        Takes the <code>id</code> we returned or the agent's <code>email</code>, which identifies exactly
                        one registration.
                    </p>

                    <div class="relative mt-3">
                        <button @click="copy(snippets.curlOnboardingStatus, 'onboardingStatus')" class="absolute right-2 top-2 rounded bg-gray-700 px-2 py-1 text-xs text-gray-200 hover:bg-gray-600">{{ copied === 'onboardingStatus' ? 'Copied' : 'Copy' }}</button>
                        <pre class="overflow-x-auto rounded-lg bg-gray-900 p-4 text-xs text-gray-100"><code>{{ snippets.curlOnboardingStatus }}</code></pre>
                    </div>

                    <div class="mt-4 overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-4 py-2 text-left font-medium text-gray-700 dark:text-gray-200">Status</th>
                                    <th class="px-4 py-2 text-left font-medium text-gray-700 dark:text-gray-200">Meaning</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700 text-gray-700 dark:text-gray-300">
                                <tr><td class="px-4 py-2"><code>Submitted</code></td><td class="px-4 py-2">Filed, not yet picked up</td></tr>
                                <tr><td class="px-4 py-2"><code>picked</code></td><td class="px-4 py-2">Being worked on</td></tr>
                                <tr><td class="px-4 py-2"><code>onboarded</code></td><td class="px-4 py-2">Done — the agent is registered on the SDK</td></tr>
                                <tr><td class="px-4 py-2"><code>rejected</code></td><td class="px-4 py-2">Not done. <code>comment</code> says why</td></tr>
                            </tbody>
                        </table>
                    </div>
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
                            <li>Retry <code>502</code> and <code>504</code> only, and only on lookups. Never retry <code>422</code>.</li>
                            <li>Never retry an IPE or validation submission — read it back with <code>GET /nin/ipe</code> or <code>GET /nin/validate</code> instead.</li>
                            <li>Do not use <code>POST /nin/validate</code> as a KYC check. It is a days-long job, not a lookup — <code>POST /nin/verify</code> is the one that answers in seconds.</li>
                            <li>Treat BVN modification, retrieval and onboarding as tickets, not calls: store the <code>id</code>, poll it on a schedule, and show your customer a status rather than a spinner.</li>
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
