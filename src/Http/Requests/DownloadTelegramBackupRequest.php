<?php

namespace FieldTechVN\TelegramBackup\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DownloadTelegramBackupRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Only authenticated users can download backups
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'id' => ['required', 'integer', 'exists:telegram_backups,id'],
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Merge route parameter into request data for validation
        $this->merge([
            'id' => $this->route('id'),
        ]);
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'id.required' => 'Backup ID is required.',
            'id.integer' => 'Backup ID must be an integer.',
            'id.exists' => 'The specified backup does not exist.',
        ];
    }
}
