<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'body_html' => ['nullable', 'string'],
            'vendor' => ['nullable', 'string', 'max:255'],
            'product_type' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'in:active,draft,archived'],
            'variants' => ['nullable', 'array'],
            'variants.*.option1' => ['nullable', 'string'],
            'variants.*.option2' => ['nullable', 'string'],
            'variants.*.price' => ['required_with:variants', 'numeric', 'min:0'],
            'variants.*.sku' => ['nullable', 'string'],
            'variants.*.inventory_quantity' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
