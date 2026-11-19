<?php

namespace App\Http\Requests\Cashier;

use App\Support\Permissions;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateOrderItemsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can(Permissions::ORDER_UPDATE_STATUS) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'items' => ['sometimes', 'array'],
            'items.*' => ['integer', 'min:0', 'max:99'],
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'quantity' => ['required_with:product_id', 'integer', 'min:1', 'max:99'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.*.integer' => 'Jumlah item harus berupa angka bulat.',
            'items.*.min' => 'Jumlah item tidak boleh kurang dari nol.',
            'items.*.max' => 'Jumlah item maksimal 99.',
            'product_id.exists' => 'Produk yang dipilih tidak tersedia.',
            'quantity.required_with' => 'Jumlah produk baru wajib diisi.',
            'quantity.integer' => 'Jumlah produk harus berupa angka bulat.',
            'quantity.min' => 'Jumlah produk minimal satu.',
            'quantity.max' => 'Jumlah produk maksimal 99.',
        ];
    }
}
