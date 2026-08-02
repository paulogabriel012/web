<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Enums\Sessions\SessionResult;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class FinishSessionRequest extends FormRequest
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
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'result' => ['required', Rule::in(SessionResult::values())],
            'ended_at' => ['nullable', 'date'],
        ];
    }

    /**
     * Get the validation messages for the defined rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'result.required' => 'O resultado da sessão é obrigatório.',
            'result.in' => 'O resultado da sessão é inválido.',
            'ended_at.date' => 'A data de encerramento é inválida.',
        ];
    }

    /**
     * Return the validated finish input with a stable result type.
     *
     * @return array{result: string}
     */
    public function finishInput(): array
    {
        return ['result' => $this->string('result')->toString()];
    }
}
