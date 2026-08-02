<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Enums\Devices\ClientArchitecture;
use App\Enums\Devices\ClientPlatform;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class LatestReleaseRequest extends FormRequest
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
            'platform' => ['required', Rule::in(ClientPlatform::values())],
            'architecture' => ['required', Rule::in(ClientArchitecture::values())],
            'current_version' => ['nullable', 'string', 'max:50'],
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
            'platform.required' => 'A plataforma é obrigatória.',
            'platform.in' => 'A plataforma informada não é suportada.',
            'architecture.required' => 'A arquitetura é obrigatória.',
            'architecture.in' => 'A arquitetura informada não é suportada.',
            'current_version.max' => 'A versão atual deve ter no máximo 50 caracteres.',
        ];
    }

    /**
     * Return the validated release lookup input with stable scalar types.
     *
     * @return array{platform: string, architecture: string, current_version: string|null}
     */
    public function releaseInput(): array
    {
        $currentVersion = $this->string('current_version')->toString();

        return [
            'platform' => $this->string('platform')->toString(),
            'architecture' => $this->string('architecture')->toString(),
            'current_version' => $currentVersion !== '' ? $currentVersion : null,
        ];
    }
}
