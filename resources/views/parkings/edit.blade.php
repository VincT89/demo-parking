<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="pm-page-title">{{ __('Configurazione') }}: {{ __($parking->name) }}</h1>
            <div class="pm-page-subtitle">{{ __('Gestione fisica, inventario vendibile e posti riservati') }}</div>
        </div>
        <a href="{{ route('parkings.index') }}" class="pm-btn pm-btn-secondary">
            {{ __('Torna alla lista') }}
        </a>
    </x-slot>

    <div class="pm-animate">
        <x-flash-message />

        @if ($errors->any())
            <div class="pm-card pm-alert-row danger" style="margin-bottom: 24px;">
                <div style="font-weight: 600; margin-bottom: 8px;">{{ __('Errore di validazione:') }}</div>
                <ul style="list-style-type: disc; padding-left: 20px; font-size: 14px;">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <!-- Form 1: Dati Generali Parcheggio -->
        <form action="{{ route('parkings.update', $parking) }}" method="POST">
            @csrf
            @method('PATCH')

            <div class="pm-card" style="margin-bottom: 24px;">
                <div class="pm-card-header pm-parking-card-header" style="justify-content: space-between;">
                    <div class="pm-card-title">{{ __('Dati generali del parcheggio') }}</div>
                    <button type="submit" class="pm-btn pm-btn-primary pm-btn-sm">
                        {{ __('Salva dati generali') }}
                    </button>
                </div>
                
                <div class="pm-parking-general-grid">
                    <div class="pm-form-group">
                        <label class="pm-label" for="parking_name">{{ __('Nome parcheggio') }}</label>
                        <input id="parking_name" type="text" name="name" class="pm-input" value="{{ old('name', $parking->name) }}" required>
                    </div>

                    <div class="pm-form-group">
                        <label class="pm-label" for="base_total_spots">{{ __('Posti fisici totali') }}</label>
                        <input type="number" id="base_total_spots" name="total_spots" class="pm-input" style="font-family: var(--pm-mono);" value="{{ old('total_spots', $parking->total_spots) }}" min="1" required>
                        <div style="font-size: 11px; color: var(--pm-text-muted); margin-top: 6px; line-height: 1.4;">
                            {{ __('Limite amministrativo di sicurezza; in modalità per prodotto la disponibilità dipende dalle singole categorie.') }}
                        </div>
                    </div>

                    <div class="pm-form-group">
                        <label class="pm-label" for="capacity_mode">{{ __('Gestione capacità') }}</label>
                        <select id="capacity_mode" name="capacity_mode" class="pm-select">
                            <option value="shared" {{ old('capacity_mode', $parking->capacity_mode) === 'shared' ? 'selected' : '' }}>{{ __('Condivisa (unico bacino)') }}</option>
                            <option value="per_product" {{ old('capacity_mode', $parking->capacity_mode) === 'per_product' ? 'selected' : '' }}>{{ __('Per prodotto (aree separate)') }}</option>
                        </select>
                        <div style="font-size: 11px; color: var(--pm-text-muted); margin-top: 6px;">{{ __('La modalità condivisa protegge la capienza totale; quella per prodotto usa capacità separate.') }}</div>
                    </div>

                    <div class="pm-form-group">
                        <label class="pm-label" for="parking_is_active">{{ __('Stato parcheggio') }}</label>
                        <select id="parking_is_active" name="is_active" class="pm-select">
                            <option value="1" {{ old('is_active', $parking->is_active) ? 'selected' : '' }}>{{ __('Attivo') }}</option>
                            <option value="0" {{ old('is_active', $parking->is_active) ? '' : 'selected' }}>{{ __('Disattivato') }}</option>
                        </select>
                        <div style="font-size: 11px; color: var(--pm-amber); margin-top: 6px;">{{ __('Disattivandolo verrà sospeso l’intero parcheggio.') }}</div>
                    </div>
                </div>
            </div>
        </form>

        <!-- Form 2: Prodotti / Categorie Vendibili -->
        <form action="{{ route('parkings.products.upsert', $parking) }}" method="POST" id="config-form">
            @csrf
            @method('PUT')

            <div class="pm-card" style="margin-bottom: 24px;">
                <div class="pm-card-header pm-parking-card-header" style="justify-content: space-between;">
                    <div class="pm-card-title">{{ __('Prodotti e categorie vendibili') }}</div>
                    <div class="pm-parking-card-actions">
                        <button type="button" class="pm-btn pm-btn-secondary pm-btn-sm" onclick="generateBasicSetup()">{{ __('Configurazione di base') }}</button>
                        <button type="button" class="pm-btn pm-btn-secondary pm-btn-sm" onclick="addProductRow()">{{ __('Aggiungi categoria') }}</button>
                    </div>
                </div>

                <div class="pm-table-wrapper">
                    <table class="pm-parking-table pm-parking-products-table">
                        <thead>
                            <tr style="border-bottom: 1px solid var(--pm-border); color: var(--pm-text-muted);">
                                <th scope="col" style="padding: 12px 8px; font-weight: 500;">{{ __('Nome') }}</th>
                                <th scope="col" style="padding: 12px 8px; font-weight: 500;">{{ __('Codice') }}</th>
                                <th scope="col" style="padding: 12px 8px; font-weight: 500;">{{ __('Capacità') }}</th>
                                <th scope="col" style="padding: 12px 8px; font-weight: 500;">{{ __('Prezzo') }}</th>
                                <th scope="col" style="padding: 12px 8px; font-weight: 500;">{{ __('Ordine') }}</th>
                                <th scope="col" style="padding: 12px 8px; font-weight: 500;">{{ __('Attivo') }}</th>
                                <th scope="col" style="padding: 12px 8px; font-weight: 500;">{{ __('Azione') }}</th>
                            </tr>
                        </thead>
                        <tbody id="products-tbody">
                            <!-- Injected by JS -->
                        </tbody>
                    </table>
                </div>

                <!-- Dashboard Riepilogo Live -->
                <div id="live-validation-panel" style="margin-top: 16px; padding: 16px; border: 1px solid var(--pm-border); border-radius: var(--pm-radius); transition: all 0.2s;">
                    <div class="pm-parking-capacity-summary">
                        <div>
                            <div style="font-weight: 600; margin-bottom: 4px; font-size:14px;">{{ __('Riepilogo capacità prodotti') }}</div>
                            <div id="live-validation-msg" style="font-size: 13px; color: var(--pm-text-muted);">...</div>
                        </div>
                        <div class="pm-parking-capacity-metrics">
                            <div style="text-align: right;">
                                <div style="font-size: 11px; text-transform: uppercase; color: var(--pm-text-dim);">{{ __('Capienza fisica') }}</div>
                                <div id="hud-total" style="font-size: 20px; font-weight: 600; font-family: var(--pm-mono);">0</div>
                            </div>
                            <div style="text-align: right;">
                                <div style="font-size: 11px; text-transform: uppercase; color: var(--pm-text-dim);">{{ __('Capienza attiva allocata') }}</div>
                                <div id="hud-allocated" style="font-size: 20px; font-weight: 600; font-family: var(--pm-mono);">0</div>
                            </div>
                            <div style="text-align: right; padding-left: 16px; border-left: 1px solid var(--pm-border);">
                                <div style="font-size: 11px; text-transform: uppercase; color: var(--pm-text-dim);">{{ __('Differenza') }}</div>
                                <div id="hud-diff" style="font-size: 20px; font-weight: 600; font-family: var(--pm-mono);">0</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Submit -->
                <div style="display: flex; justify-content: flex-end; gap: 16px; align-items: center; margin-top: 16px;">
                    <div id="submit-warning" style="color: var(--pm-red); font-size: 13px; font-weight: 500; display: none;">
                        {{ __('Risolvi l’errore di capienza prima di salvare i prodotti.') }}
                    </div>
                    <button type="submit" id="main-submit-btn" class="pm-btn pm-btn-primary" style="padding: 10px 24px;">
                        {{ __('Salva prodotti') }}
                    </button>
                </div>
            </div>
        </form>

        <!-- Form 3: Allocazioni (Sequenza B) -->
        <div class="pm-card" style="margin-bottom: 24px;">
            <div class="pm-card-header pm-parking-card-header" style="justify-content: space-between;">
                <div class="pm-card-title">{{ __('Posti riservati e allocazioni') }}</div>
                @if($parking->capacity_mode === 'shared')
                <button type="button" class="pm-btn pm-btn-secondary pm-btn-sm" onclick="document.getElementById('new-allocation-form').style.display='block'">
                    {{ __('Nuova allocazione globale') }}
                </button>
                @endif
            </div>

            @if($parking->capacity_mode === 'per_product')
            <div class="pm-card" style="margin-top: 16px; margin-bottom: 16px; background-color: var(--pm-bg); border: 1px solid var(--pm-border);">
                <div style="font-weight: 500; font-size: 14px;">{{ __('Funzionalità disabilitata') }}</div>
                <div style="font-size: 13px; color: var(--pm-text-muted); margin-top: 4px;">
                    {{ __('Le allocazioni globali non sono disponibili in modalità per prodotto. Applica ogni blocco a una categoria specifica.') }}
                </div>
            </div>
            @endif

            <!-- New Allocation Form -->
            <div id="new-allocation-form" style="display: none; background: var(--pm-bg); padding: 16px; border: 1px solid var(--pm-border); border-radius: var(--pm-radius); margin-bottom: 16px;">
                <h4 style="margin-top: 0; margin-bottom: 12px; font-weight: 600; font-size: 14px;">{{ __('Aggiungi posti riservati') }}</h4>
                <form action="{{ route('parkings.allocations.store', $parking->id) }}" method="POST">
                    @csrf
                    <input type="hidden" name="parking_id" value="{{ $parking->id }}">
                    
                    <div class="pm-parking-allocation-grid">
                        <div class="pm-form-group" style="margin-bottom: 0;">
                            <label class="pm-label" for="allocation_type">{{ __('Tipo destinazione') }}</label>
                            <select id="allocation_type" name="allocation_type" class="pm-select" required>
                                <option value="rentcar">Rent Car</option>
                                <option value="internal_use">{{ __('Uso interno') }}</option>
                                <option value="partner">{{ __('Partner commerciale') }}</option>
                                <option value="maintenance">{{ __('Manutenzione') }}</option>
                                <option value="other">{{ __('Altro') }}</option>
                            </select>
                        </div>
                        <div class="pm-form-group" style="margin-bottom: 0;">
                            <label class="pm-label" for="allocation_spots">{{ __('Numero posti') }}</label>
                            <input id="allocation_spots" type="number" name="spots" class="pm-input" min="1" required>
                        </div>
                        <div class="pm-form-group" style="margin-bottom: 0;">
                            <label class="pm-label" for="allocation_notes">{{ __('Note o riferimento') }}</label>
                            <input id="allocation_notes" type="text" name="notes" class="pm-input" placeholder="Es. Hertz furgoni">
                        </div>
                        <div class="pm-form-group" style="margin-bottom: 0;">
                            <label class="pm-label" for="allocation_starts_at">{{ __('Data inizio inclusa') }}</label>
                            <input id="allocation_starts_at" type="datetime-local" name="starts_at" class="pm-input" required>
                        </div>
                        <div class="pm-form-group" style="margin-bottom: 0;">
                            <label class="pm-label" for="allocation_ends_at">{{ __('Data fine esclusa') }}</label>
                            <input id="allocation_ends_at" type="datetime-local" name="ends_at" class="pm-input" required>
                        </div>
                        <div class="pm-form-group" style="margin-bottom: 0;">
                            <label class="pm-label pm-flex pm-items-center" for="allocation_is_active" style="gap:8px; cursor:pointer;">
                                <input id="allocation_is_active" type="checkbox" name="is_active" value="1" checked style="width:18px; height:18px;">
                                <span>{{ __('Attiva subito') }}</span>
                            </label>
                        </div>
                    </div>
                    
                    <div style="margin-top: 16px; text-align: right;">
                        <button type="button" class="pm-btn pm-btn-secondary" onclick="document.getElementById('new-allocation-form').style.display='none'">{{ __('Annulla') }}</button>
                        <button type="submit" class="pm-btn pm-btn-primary">{{ __('Salva allocazione') }}</button>
                    </div>
                </form>
            </div>

            <!-- Allocations List -->
            <div class="pm-table-wrapper">
                <table class="pm-parking-table pm-parking-allocations-table">
                    <thead>
                        <tr style="border-bottom: 1px solid var(--pm-border); color: var(--pm-text-muted);">
                            <th scope="col" style="padding: 12px 8px; font-weight: 500;">{{ __('Stato') }}</th>
                            <th scope="col" style="padding: 12px 8px; font-weight: 500;">{{ __('Destinazione') }}</th>
                            <th scope="col" style="padding: 12px 8px; font-weight: 500;">{{ __('Posti') }}</th>
                            <th scope="col" style="padding: 12px 8px; font-weight: 500;">{{ __('Inizio') }}</th>
                            <th scope="col" style="padding: 12px 8px; font-weight: 500;">{{ __('Fine') }}</th>
                            <th scope="col" style="padding: 12px 8px; font-weight: 500;">{{ __('Azione') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($parking->allocations as $alloc)
                            <tr style="border-bottom: 1px solid var(--pm-border-light); {{ !$alloc->is_active ? 'opacity: 0.5;' : '' }}">
                                <td data-label="{{ __('Stato') }}" style="padding: 12px 8px;">
                                    @if($alloc->is_active && $alloc->ends_at > now())
                                        <span class="pm-badge green">{{ __('Attivo') }}</span>
                                    @elseif($alloc->ends_at <= now())
                                        <span class="pm-badge gray">{{ __('Scaduto') }}</span>
                                    @else
                                        <span class="pm-badge gray">{{ __('Disattivato') }}</span>
                                    @endif
                                </td>
                                <td data-label="{{ __('Destinazione') }}" style="padding: 12px 8px;">
                                    <div style="font-weight: 500;">{{ strtoupper($alloc->allocation_type) }}</div>
                                    <div style="font-size: 12px; color: var(--pm-text-muted);">{{ $alloc->notes }}</div>
                                </td>
                                <td data-label="{{ __('Posti') }}" style="padding: 12px 8px;">
                                    <span class="pm-badge gray">{{ $alloc->spots }}</span>
                                </td>
                                <td data-label="{{ __('Inizio') }}" style="padding: 12px 8px; font-family: var(--pm-mono); font-size: 13px;">
                                    {{ $alloc->starts_at->format('d/m/Y H:i') }}
                                </td>
                                <td data-label="{{ __('Fine') }}" style="padding: 12px 8px; font-family: var(--pm-mono); font-size: 13px;">
                                    {{ $alloc->ends_at->format('d/m/Y H:i') }}
                                </td>
                                <td data-label="{{ __('Azione') }}" style="padding: 12px 8px;">
                                    <form action="{{ route('parkings.allocations.destroy', ['parking' => $parking->id, 'allocation' => $alloc->id]) }}" method="POST" style="display:inline;" onsubmit="return confirm('{{ __('Eliminare questa allocazione?') }}');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="pm-btn pm-btn-danger pm-btn-sm" style="padding: 4px 8px; font-size: 11px;">{{ __('Elimina') }}</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr class="pm-parking-empty-row">
                                <td colspan="6" class="pm-text-muted" style="text-align: center; padding: 24px;">{{ __('Nessun posto riservato o allocazione configurata.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    </div>

@php
    $parkingLabels = [
        'confirmBasicSetup' => __('Caricare le quattro categorie standard? I prodotti esistenti non verranno rimossi finché non salvi.'),
        'autoOpen' => __('Auto/Moto scoperto'),
        'autoCovered' => __('Auto/Moto coperto'),
        'truckOpen' => __('Camion scoperto'),
        'truckCovered' => __('Camion coperto'),
        'name' => __('Nome'),
        'code' => __('Codice'),
        'capacity' => __('Capacità'),
        'price' => __('Prezzo'),
        'sortOrder' => __('Ordine'),
        'active' => __('Attivo'),
        'delete' => __('Elimina'),
        'cancel' => __('Annulla'),
        'deleteWarning' => __('Questa categoria verrà eliminata al salvataggio. Se contiene prenotazioni storiche, disattivala invece di eliminarla.'),
        'unallocated' => __('Una parte della capacità fisica non è ancora assegnata ai prodotti vendibili.'),
        'aligned' => __('La configurazione è perfettamente allineata alla capacità fisica.'),
        'overCapacity' => __('La somma delle capacità supera la capienza del parcheggio.'),
    ];
@endphp

<script>
    // Server data payload
    const initialProducts = @json(old('products', $parking->products));
    const parkingLabels = @json($parkingLabels);
    let productIndex = 0;

    const tbody = document.getElementById('products-tbody');
    const totalSpotsInput = document.getElementById('base_total_spots');
    
    // HUD Elements
    const hudPanel = document.getElementById('live-validation-panel');
    const hudTotal = document.getElementById('hud-total');
    const hudAllocated = document.getElementById('hud-allocated');
    const hudDiff = document.getElementById('hud-diff');
    const hudMsg = document.getElementById('live-validation-msg');
    const submitBtn = document.getElementById('main-submit-btn');
    const submitWarn = document.getElementById('submit-warning');

    document.addEventListener('DOMContentLoaded', () => {
        if (initialProducts && initialProducts.length > 0) {
            initialProducts.forEach(p => appendProductRow(p));
        }
        
        totalSpotsInput.addEventListener('input', updateHUD);
        updateHUD();
    });

    function generateBasicSetup() {
        if (confirm(parkingLabels.confirmBasicSetup)) {
            appendProductRow({name: parkingLabels.autoOpen, code: 'auto_open', capacity: 1000, price: 5.0, is_active: 1});
            appendProductRow({name: parkingLabels.autoCovered, code: 'auto_covered', capacity: 500, price: 8.0, is_active: 1});
            appendProductRow({name: parkingLabels.truckOpen, code: 'camion_open', capacity: 100, price: 15.0, is_active: 1});
            appendProductRow({name: parkingLabels.truckCovered, code: 'camion_covered', capacity: 50, price: 20.0, is_active: 1});
        }
    }

    function addProductRow() {
        appendProductRow({});
    }

    function appendProductRow(data) {
        const tr = document.createElement('tr');
        tr.className = 'product-row';
        tr.style.background = data.delete ? 'rgba(239, 68, 68, 0.05)' : 'transparent';
        
        const isDelete = data.delete == 1 || data.delete == '1' || data.delete === true;
        
        tr.innerHTML = `
            <td data-label="${parkingLabels.name}" style="padding: 8px;">
                ${data.id ? `<input type="hidden" name="products[${productIndex}][id]" value="${data.id}">` : ''}
                <input type="hidden" name="products[${productIndex}][delete]" class="delete-flag" value="${isDelete ? '1' : '0'}">
                <input type="text" name="products[${productIndex}][name]" class="pm-input" aria-label="${parkingLabels.name}" placeholder="${parkingLabels.autoCovered}" value="${data.name || ''}" style="width: 100%" required>
            </td>
            <td data-label="${parkingLabels.code}" style="padding: 8px;">
                <input type="text" name="products[${productIndex}][code]" class="pm-input" aria-label="${parkingLabels.code}" placeholder="auto_covered" value="${data.code || ''}" style="width: 100%; font-family: var(--pm-mono)" required>
            </td>
            <td data-label="${parkingLabels.capacity}" style="padding: 8px;">
                <input type="number" name="products[${productIndex}][capacity]" class="pm-input capacity-input" aria-label="${parkingLabels.capacity}" placeholder="100" value="${data.capacity ?? 100}" min="0" style="width: 100px; font-family: var(--pm-mono)" required>
            </td>
            <td data-label="${parkingLabels.price}" style="padding: 8px;">
                <input type="number" name="products[${productIndex}][price]" class="pm-input" aria-label="${parkingLabels.price}" placeholder="0.00" value="${data.price ?? '0.00'}" step="0.01" min="0" style="width: 80px;" required>
            </td>
            <td data-label="${parkingLabels.sortOrder}" style="padding: 8px;">
                <input type="number" name="products[${productIndex}][sort_order]" class="pm-input" aria-label="${parkingLabels.sortOrder}" value="${data.sort_order ?? 0}" style="width: 60px;">
            </td>
            <td data-label="${parkingLabels.active}" style="padding: 8px; text-align: center;">
                <input type="hidden" name="products[${productIndex}][is_active]" value="0">
                <input type="checkbox" name="products[${productIndex}][is_active]" value="1" class="active-flag" aria-label="${parkingLabels.active}" style="transform: scale(1.2)" ${((data.is_active ?? 1) == 1) ? 'checked' : ''}>
            </td>
            <td data-label="${parkingLabels.delete}" style="padding: 8px;">
                <button type="button" class="pm-btn pm-btn-secondary pm-btn-sm" onclick="toggleDelete(this, ${data.id ? 'true' : 'false'})">
                    ${isDelete ? parkingLabels.cancel : parkingLabels.delete}
                </button>
            </td>
        `;
        
        // Listeners for HUD
        const capInput = tr.querySelector('.capacity-input');
        const actFlag = tr.querySelector('.active-flag');
        
        capInput.addEventListener('input', updateHUD);
        actFlag.addEventListener('change', updateHUD);
        
        tbody.appendChild(tr);
        productIndex++;
        updateHUD();
    }

    function toggleDelete(btn, isExisting) {
        const tr = btn.closest('tr');
        const delInput = tr.querySelector('.delete-flag');
        const wasDeleted = delInput.value === '1';
        
        if (!wasDeleted && isExisting) {
            alert(parkingLabels.deleteWarning);
        }

        if (wasDeleted) {
            delInput.value = '0';
            tr.style.background = 'transparent';
            tr.style.opacity = '1';
            btn.textContent = parkingLabels.delete;
        } else {
            delInput.value = '1';
            tr.style.background = 'rgba(239, 68, 68, 0.05)';
            tr.style.opacity = '0.5';
            btn.textContent = parkingLabels.cancel;
        }
        updateHUD();
    }

    function updateHUD() {
        const total = parseInt(totalSpotsInput.value) || 0;
        let allocated = 0;

        document.querySelectorAll('.product-row').forEach(tr => {
            const isDel = tr.querySelector('.delete-flag').value === '1';
            const isAct = tr.querySelector('.active-flag').checked;
            const cap = parseInt(tr.querySelector('.capacity-input').value) || 0;
            
            if (!isDel && isAct) {
                allocated += cap;
            }
        });

        const diff = total - allocated;

        hudTotal.textContent = total;
        hudAllocated.textContent = allocated;
        
        if (diff > 0) {
            hudDiff.textContent = `+${diff}`;
            hudPanel.style.borderColor = 'var(--pm-amber)';
            hudPanel.style.background = 'rgba(245, 158, 11, 0.05)';
            hudMsg.textContent = parkingLabels.unallocated;
            hudMsg.style.color = 'var(--pm-amber)';
            hudDiff.style.color = 'var(--pm-amber)';
            submitBtn.disabled = false;
            submitWarn.style.display = 'none';
        } else if (diff === 0) {
            hudDiff.textContent = 'OK';
            hudPanel.style.borderColor = 'var(--pm-green)';
            hudPanel.style.background = 'rgba(16, 185, 129, 0.05)';
            hudMsg.textContent = parkingLabels.aligned;
            hudMsg.style.color = 'var(--pm-green)';
            hudDiff.style.color = 'var(--pm-green)';
            submitBtn.disabled = false;
            submitWarn.style.display = 'none';
        } else {
            hudDiff.textContent = diff;
            hudPanel.style.borderColor = 'var(--pm-red)';
            hudPanel.style.background = 'rgba(239, 68, 68, 0.15)';
            hudMsg.textContent = parkingLabels.overCapacity;
            hudMsg.style.color = 'var(--pm-red)';
            hudDiff.style.color = 'var(--pm-red)';
            submitBtn.disabled = true;
            submitWarn.style.display = 'block';
        }
    }
</script>

</x-app-layout>
