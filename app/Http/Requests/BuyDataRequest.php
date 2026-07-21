<?php

namespace App\Http\Requests;

use App\Models\Plan;
use App\Support\DataRequestNormalizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class BuyDataRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Accept the body shapes other data-vending APIs use before validating, so
     * an integrator already wired to one of them does not have to rename every
     * field. See DataRequestNormalizer for the accepted aliases.
     */
    protected function prepareForValidation(): void
    {
        $this->merge(DataRequestNormalizer::normalize($this->all()));
    }

    public function rules(): array
    {
        return [
            // The plan determines the real network server-side; the posted
            // network is only the caller's stated choice, so it is optional.
            // It is still checked against the known list: a caller sending
            // network 7 has a bug worth telling them about.
            'network' => ['nullable', Rule::in(array_values(DataRequestNormalizer::NETWORK_IDS))],
            // The PUBLIC plan id (plans.code), not the internal primary key --
            // that is the number quoted in the developer docs and stored in
            // integrators' own plan tables.
            'plan_id' => ['required', 'integer', 'exists:plans,code'],
            // The server NEVER validates the phone against network prefixes —
            // the user-selected network is authoritative (ported lines / new
            // NCC prefixes make prefix checks unreliable server-side).
            'phone' => ['required', 'digits:11'],
            'ported' => ['boolean'],
            // The caller's own order id, in whatever format they use. Not
            // constrained to a UUID: no other data API asks for one, and the
            // value is echoed back so it must survive verbatim.
            'client_ref' => ['required', 'string', 'max:100'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->has('plan_id')) {
                return;
            }

            $plan = Plan::byCode($this->input('plan_id'))->first();

            // Plan must be visible and its data type switched on.
            if (! $plan || $plan->plan_status !== 'on' || $plan->status !== 'on') {
                $validator->errors()->add('plan_id', 'This plan is not available.');

                return;
            }

            // The plan is authoritative, so a stated network that disagrees is
            // never acted on -- but it means the caller has mapped their plan
            // ids wrongly and is about to sell the wrong bundle. Say so.
            $network = $this->input('network');

            if ($network && $plan->network !== $network) {
                $validator->errors()->add(
                    'network',
                    "Plan {$plan->code} is a {$plan->network} plan, but you sent network \"{$network}\"."
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'phone.digits' => 'Enter a valid 11-digit phone number.',
            'network.in' => 'Unknown network. Use 1 (MTN), 2 (Airtel), 3 (Glo) or 4 (9mobile), or the network name.',
        ];
    }
}
