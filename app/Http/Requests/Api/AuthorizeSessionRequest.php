<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class AuthorizeSessionRequest extends FormRequest
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
            'device_id' => ['required', 'uuid'],
            'operation' => ['required', Rule::in(['browser_run'])],
            'client_version' => ['nullable', 'string', 'max:50'],
            'profile_id' => ['nullable', 'uuid'],
            'idempotency_key' => ['required', 'string', 'max:255'],
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
            'device_id.required' => 'O dispositivo é obrigatório.',
            'device_id.uuid' => 'O dispositivo informado é inválido.',
            'operation.required' => 'A operação é obrigatória.',
            'operation.in' => 'A operação informada não é suportada.',
            'client_version.max' => 'A versão do aplicativo deve ter no máximo 50 caracteres.',
            'profile_id.uuid' => 'O perfil informado é inválido.',
            'idempotency_key.required' => 'A chave de idempotência é obrigatória.',
            'idempotency_key.max' => 'A chave de idempotência deve ter no máximo 255 caracteres.',
        ];
    }

    /**
     * Prepare the request for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'operation' => $this->input('operation', 'browser_run'),
            'idempotency_key' => $this->header('Idempotency-Key'),
        ]);
    }

    /**
     * Return the validated browser-session input with stable scalar types.
     *
     * @return array{device_id: string, operation: string, client_version: string|null, idempotency_key: string}
     */
    public function browserInput(): array
    {
        $clientVersion = $this->string('client_version')->toString();

        return [
            'device_id' => $this->string('device_id')->toString(),
            'operation' => $this->string('operation')->toString(),
            'client_version' => $clientVersion !== '' ? $clientVersion : null,
            'idempotency_key' => $this->string('idempotency_key')->toString(),
        ];
    }
}
