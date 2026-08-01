<?php

declare(strict_types=1);

namespace App\Http\Requests\Billing;

use App\Enums\Billing\Plan;
use Illuminate\Foundation\Http\FormRequest;

class CheckoutRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'plan' => ['required', 'string', 'in:'.implode(',', Plan::catalogValues())],
        ];
    }

    /**
     * Get the validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'plan.required' => __('Select a plan.'),
            'plan.in' => __('The selected plan is not available.'),
        ];
    }
}
