<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use App\Models\ParkingProduct;
use App\Models\Reservation;

class UpsertParkingProductsRequest extends FormRequest
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
            'products' => ['array'],
            'products.*.id' => ['nullable', 'integer', 'exists:parking_products,id'],
            'products.*.delete' => ['boolean'],
            'products.*.name' => ['required_if:products.*.delete,false', 'string', 'max:255', 'distinct:strict'],
            'products.*.code' => ['required_if:products.*.delete,false', 'string', 'max:255', 'distinct:strict', 'regex:/^[a-z0-9_]+$/'],
            'products.*.capacity' => ['required_if:products.*.delete,false', 'integer', 'min:0'],
            'products.*.price' => ['required_if:products.*.delete,false', 'numeric', 'min:0'],
            'products.*.is_active' => ['boolean'],
            'products.*.sort_order' => ['nullable', 'integer'],
        ];
    }

    /**
     * Get the "after" validation callables for the request.
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                // Ignore if basic validation fails
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $products = $this->input('products', []);
                $activeCapacitySum = 0;

                $parking = $this->route('parking');

                foreach ($products as $index => $prod) {
                    $isDelete = $prod['delete'] ?? false;
                    $isActive = $prod['is_active'] ?? false;
                    $cap = (int) ($prod['capacity'] ?? 0);
                    $id = $prod['id'] ?? null;

                    // Regola: prodotto attivo non può avere capienza zero
                    if (!$isDelete && $isActive && $cap <= 0) {
                        $validator->errors()->add(
                            "products.{$index}.capacity", 
                            "Un prodotto attivo deve avere capacità maggiore di zero. Se intendi bloccare le vendite, spegni l'interruttore 'Attivo'."
                        );
                    }

                    // Regola di Sicurezza: Protezione contro Cancellazione Storica
                    if ($isDelete && $id) {
                        $reservationCount = Reservation::where('parking_product_id', $id)->count();
                        if ($reservationCount > 0) {
                            $validator->errors()->add(
                                "products.{$index}.delete",
                                "Il prodotto '{$prod['name']}' è associato a {$reservationCount} prenotazioni storiche. Non può essere eliminato dal DB. Per interrompere le vendite, togli la spunta da 'Attivo' invece di premere elimina."
                            );
                        }
                    }

                    // Validazione riduzione capienza rischiosa
                    if (!$isDelete && $id && $cap > 0) {
                        $maxOccupied = 0;
                        for ($i = 0; $i < 30; $i++) {
                            $day = \Carbon\Carbon::today()->addDays($i);
                            $dayOccupied = Reservation::where('parking_product_id', $id)
                                ->active()
                                ->overlapping($day, $day->copy()->addDay())
                                ->sum('spots');
                            if ($dayOccupied > $maxOccupied) {
                                $maxOccupied = $dayOccupied;
                            }
                        }

                        if ($cap < $maxOccupied) {
                            $validator->errors()->add(
                                "products.{$index}.capacity",
                                "Impossibile abbassare la capacità a {$cap}. Ci sono giornate future con già {$maxOccupied} veicoli prenotati per questa tipologia."
                            );
                        }
                    }

                    // Se è attivo e non marcato delete, fa volume
                    if (!$isDelete && $isActive) {
                        $activeCapacitySum += $cap;
                    }
                }

                // REGOLA PRINCIPALE: Prevenzione doppia verità e overbooking
                if ($activeCapacitySum > $parking->total_spots) {
                    $validator->errors()->add(
                        'general', 
                        "La somma delle capacità dei prodotti attivi ({$activeCapacitySum}) eccede i posti fisici totali configurati ({$parking->total_spots}). Riduci le quantità o alza la capienza fisica per poter salvare."
                    );
                }
            }
        ];
    }
}
