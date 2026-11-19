<?php

namespace App\Http\Requests\Cashier;

use App\Models\Order;
use App\Support\Permissions;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreCashierOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permissions::ORDER_CREATE) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $productIds = $this->input('product_ids');
        $quantities = $this->input('quantities');

        if (! is_array($productIds) || ! is_array($quantities)) {
            return;
        }

        $selectedQuantities = [];

        foreach ($productIds as $productId) {
            if ((! is_string($productId) && ! is_int($productId)) || ! array_key_exists((string) $productId, $quantities)) {
                continue;
            }

            $selectedQuantities[$productId] = $quantities[$productId];
        }

        $this->merge(['quantities' => $selectedQuantities]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'order_type' => ['required', Rule::in([Order::TYPE_DINE_IN, Order::TYPE_TAKE_AWAY])],
            'table_id' => [
                'exclude_unless:order_type,'.Order::TYPE_DINE_IN,
                'required',
                'integer',
                Rule::exists('dining_tables', 'id')
                    ->where('is_active', true)
                    ->where('status', 'available')
                    ->whereNull('deleted_at'),
            ],
            'product_ids' => ['required', 'array', 'min:1'],
            'product_ids.*' => ['required', 'integer', 'distinct', 'exists:products,id'],
            'quantities' => ['required', 'array'],
            'quantities.*' => ['required', 'integer', 'min:1', 'max:99'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $quantities = $this->input('quantities', []);

                foreach ($this->input('product_ids', []) as $productId) {
                    if (! array_key_exists((string) $productId, $quantities)) {
                        $validator->errors()->add('quantities', 'Jumlah wajib diisi untuk setiap produk yang dipilih.');

                        return;
                    }
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'order_type.required' => 'Tipe pesanan wajib dipilih.',
            'order_type.in' => 'Tipe pesanan tidak valid.',
            'table_id.required' => 'Pilih meja untuk pesanan dine in.',
            'table_id.exists' => 'Meja yang dipilih tidak tersedia.',
            'product_ids.required' => 'Pilih minimal satu produk untuk transaksi.',
            'product_ids.min' => 'Pilih minimal satu produk untuk transaksi.',
            'product_ids.*.exists' => 'Produk yang dipilih tidak tersedia.',
            'product_ids.*.distinct' => 'Produk tidak boleh dipilih lebih dari satu kali.',
            'quantities.*.integer' => 'Jumlah produk harus berupa angka bulat.',
            'quantities.*.min' => 'Jumlah produk minimal satu.',
            'quantities.*.max' => 'Jumlah produk maksimal 99.',
        ];
    }
}
