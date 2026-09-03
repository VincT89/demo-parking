<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use App\Models\ParkingProduct;
use App\Models\Reservation;

class UpdateParkingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage-parkings');
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'total_spots' => ['required', 'integer', 'min:1'],
            'capacity_mode' => ['required', 'string', 'in:shared,per_product'],
            'is_active' => ['boolean'],
        ];
    }
}
