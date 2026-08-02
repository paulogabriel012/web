<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Enums\Devices\ClientArchitecture;
use App\Enums\Devices\ClientPlatform;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class RegisterDeviceRequest extends FormRequest
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
            'installation_id' => ['required', 'uuid'],
            'name' => ['required', 'string', 'max:255'],
            'platform' => ['required', Rule::in(ClientPlatform::values())],
            'architecture' => ['required', Rule::in(ClientArchitecture::values())],
            'app_version' => ['required', 'string', 'max:50'],
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
            'installation_id.required' => 'O identificador de instalação é obrigatório.',
            'installation_id.uuid' => 'O identificador de instalação deve ser um UUID válido.',
            'name.required' => 'O nome do dispositivo é obrigatório.',
            'name.max' => 'O nome do dispositivo deve ter no máximo 255 caracteres.',
            'platform.required' => 'A plataforma é obrigatória.',
            'platform.in' => 'A plataforma informada não é suportada.',
            'architecture.required' => 'A arquitetura é obrigatória.',
            'architecture.in' => 'A arquitetura informada não é suportada.',
            'app_version.required' => 'A versão do aplicativo é obrigatória.',
            'app_version.max' => 'A versão do aplicativo deve ter no máximo 50 caracteres.',
            'os_version.max' => 'A versão do sistema operacional deve ter no máximo 50 caracteres.',
        ];
    }

    /**
     * Return the validated device input with stable scalar types.
     *
     * @return array{installation_id: string, name: string, platform: string, architecture: string, app_version: string, os_version: string|null}
     */
    public function deviceInput(): array
    {
        $osVersion = $this->string('os_version')->toString();

        return [
            'installation_id' => $this->string('installation_id')->toString(),
            'name' => $this->string('name')->toString(),
            'platform' => $this->string('platform')->toString(),
            'architecture' => $this->string('architecture')->toString(),
            'app_version' => $this->string('app_version')->toString(),
            'os_version' => $osVersion !== '' ? $osVersion : null,
        ];
    }
}
