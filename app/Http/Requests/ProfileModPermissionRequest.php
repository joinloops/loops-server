<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ProfileModPermissionRequest extends FormRequest
{
    public const LABELS = [
        'enforce_ai_label' => 'contains_ai',
        'enforce_ad_label' => 'contains_ad',
        'enforce_nsfw_label' => 'is_sensitive',
    ];

    public function rules(): array
    {
        return [
            'enforce_ai_label' => ['sometimes', 'boolean'],
            'enforce_ad_label' => ['sometimes', 'boolean'],
            'enforce_nsfw_label' => ['sometimes', 'boolean'],
            'apply_to_existing' => ['sometimes', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $keys = array_keys($this->only(array_keys(self::LABELS)));

                if (count($keys) !== 1) {
                    $validator->errors()->add(
                        'enforce_ai_label',
                        'Exactly one label permission may be updated per request.'
                    );

                    return;
                }

                if ($this->boolean('apply_to_existing') && ! $this->boolean($keys[0])) {
                    $validator->errors()->add(
                        'apply_to_existing',
                        'apply_to_existing is only valid when enabling a label.'
                    );
                }
            },
        ];
    }

    /**
     * @return 'enforce_ai_label'|'enforce_ad_label'|'enforce_nsfw_label'
     */
    public function labelKey(): string
    {
        /** @var 'enforce_ai_label'|'enforce_ad_label'|'enforce_nsfw_label' */
        return array_keys($this->only(array_keys(self::LABELS)))[0];
    }

    /**
     * @return 'contains_ai'|'contains_ad'|'is_sensitive'
     */
    public function videoColumn(): string
    {
        return self::LABELS[$this->labelKey()];
    }
}
