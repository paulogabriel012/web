<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

final class HeartbeatRequest extends FormRequest
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
            'app_version' => ['nullable', 'string', 'max:50'],
            'os_version' => ['nullable', 'string', 'max:50'],
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
            'app_version.max' => 'A versão do aplicativo deve ter no máximo 50 caracteres.',
            'os_version.max' => 'A versão do sistema operacional deve ter no máximo 50 caracteres.',
        ];
    }

    /**
     * Return the optional heartbeat fields with stable scalar types.
     *
     * @return array{app_version: string|null, os_version: string|null}
     */
    public function heartbeatInput(): array
    {
        $appVersion = $this->string('app_version')->toString();
        $osVersion = $this->string('os_version')->toString();

        return [
            'app_version' => $appVersion !== '' ? $appVersion : null,
            'os_version' => $osVersion !== '' ? $osVersion : null,
        ];
    }
}
