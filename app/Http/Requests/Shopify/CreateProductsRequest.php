<?php

namespace App\Http\Requests\Shopify;

use Illuminate\Foundation\Http\FormRequest;

class CreateProductsRequest extends FormRequest
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
            'products' => ['required','array','min:1','max:300'],

            'products.*.external_id' => ['required','string','max:100'],
            'products.*.title' => ['required','string','max:255'],
            'products.*.vendor' => ['nullable','string','max:255'],
            'products.*.product_type' => ['nullable','string','max:255'],
            'products.*.description_html' => ['nullable','string'],

            // ✅ tags ahora es array
            'products.*.tags' => ['nullable','array'],
            'products.*.tags.*' => ['string','max:50'],

            // ✅ images array de urls
            'products.*.images' => ['nullable','array'],
            'products.*.images.*' => ['url'],

            'products.*.status' => ['required', 'string'],
            'products.*.variants.*.inventory_management' => ['nullable', 'string'],

            // ✅ variant
            'products.*.variants' => ['required','array','min:1','max:100'],
            'products.*.variants.*.sku' => ['required','string','max:100'],
            'products.*.variants.*.price' => ['required','numeric','min:0'],
            'products.*.variants.*.compare_at_price' => ['nullable','numeric','min:0'],
            'products.*.variants.*.inventory_quantity' => ['nullable','integer','min:0'],
            'products.*.variants.*.requires_shipping' => ['nullable','boolean'],
            'products.*.variants.*.taxable' => ['nullable','boolean'],
            'products.*.collections' => ['nullable','array'],
            'products.*.collections.*' => ['required','array'],
            'products.*.collections.*.id' => ['required','string'],

            // ✅ metafields key/value
            'products.*.metafields' => ['nullable','array'],
        ];
    }

}
