<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreParkingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('manage-parkings');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'separate_management' => ['boolean'],
            'management_name' => ['required_if:separate_management,1', 'nullable', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'total_spots' => ['required', 'integer', 'min:1'],
            'capacity_mode' => ['required', 'string', 'in:shared,per_product'],
            'is_active' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'management_name.required_if' => __('Indica il nome della nuova gestione.'),
            'required' => __('Compila il campo :attribute.'),
            'string' => __('Il valore del campo :attribute non è valido.'),
            'max' => __('Il campo :attribute supera il limite di :max caratteri.'),
            'in' => __('Il valore del campo :attribute non è valido.'),
            'separate_management.boolean' => __('Seleziona un’opzione valida per la gestione.'),
            'total_spots.integer' => __('Il numero di posti deve essere un numero intero.'),
            'total_spots.min' => __('Inserisci almeno un posto disponibile.'),
        ];
    }

    public function attributes(): array
    {
        return ['management_name' => __('Nome della gestione'), 'name' => __('Nome'), 'total_spots' => __('Posti totali'), 'capacity_mode' => __('Modalità capienza')];
    }
}
