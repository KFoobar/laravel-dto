<?php

namespace KFoobar\Data\Tests\Fixtures;

use Illuminate\Foundation\Http\FormRequest;

class PostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id' => ['required', 'integer'],
            'title' => ['required', 'string'],
        ];
    }
}
