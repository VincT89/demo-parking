<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="pm-page-title">{{ __('Calendario occupazione') }}</h1>
            <div class="pm-page-subtitle" id="calendar-subtitle">{{ __('caricamento...') }}</div>
        </div>
        <div style="display:flex;gap:8px;align-items:center">
            @if($parkings->count() > 1)
                <select class="pm-input" style="padding: 6px 12px; font-size: 14px; border-radius: var(--pm-radius-sm); border-color: var(--pm-border); background-color: var(--pm-bg); color: var(--pm-text);" onchange="window.location.href='?parking_id='+this.value">
                    @foreach($parkings as $p)
                        <option value="{{ $p->id }}" {{ $parking->id == $p->id ? 'selected' : '' }}>{{ __($p->name) }}</option>
                    @endforeach
                </select>
            @endif
            <button onclick="prevMonth()" class="pm-btn pm-btn-secondary pm-btn-sm">&#8249;</button>
            <button onclick="nextMonth()" class="pm-btn pm-btn-secondary pm-btn-sm">&#8250;</button>
            <a href="{{ route('reservations.create', ['parking_id' => $parking->id]) }}" class="pm-btn pm-btn-primary">
                 {{ __('Nuova prenotazione') }}
            </a>
        </div>
    </x-slot>

    <div style="display:flex;gap:8px;margin-bottom:24px" class="pm-animate">
        <a href="{{ route('calendar', ['parking_id' => $parking->id ?? '']) }}" class="pm-btn pm-btn-primary">
            {{ __('Vista calendario') }}
        </a>
        <a href="{{ route('calendar.day', ['type' => 'entries', 'parking_id' => $parking->id ?? '', 'date' => now(config('app.timezone'))->toDateString()]) }}" class="pm-btn pm-btn-secondary">
            {{ __('Entrate') }}
        </a>
        <a href="{{ route('calendar.day', ['type' => 'exits', 'parking_id' => $parking->id ?? '', 'date' => now(config('app.timezone'))->toDateString()]) }}" class="pm-btn pm-btn-secondary">
            {{ __('Uscite') }}
        </a>
    </div>

    <div style="display:flex;gap:4px;margin-bottom:16px" class="pm-animate">
        <button onclick="switchTab('daily')" id="tab-daily"
            class="pm-btn pm-btn-secondary pm-btn-sm">{{ __('Giornaliera') }}</button>
        <button onclick="switchTab('timeline')" id="tab-timeline"
            class="pm-btn pm-btn-primary pm-btn-sm">{{ __('Settimanale') }}</button>
        <button onclick="switchTab('monthly')" id="tab-monthly"
            class="pm-btn pm-btn-secondary pm-btn-sm">{{ __('Mensile') }}</button>
    </div>

    <div id="view-timeline" class="pm-animate-2">
        <div class="pm-card" style="padding-top: 0; max-height: calc(100vh - 220px); overflow-y: auto; position: relative;">
            <div id="timeline-container"></div>
        </div>
    </div>

    <div id="view-monthly" style="display:none" class="pm-animate-2">
        <div class="pm-card">
            <div id="monthly-container"></div>
        </div>
    </div>

    <div id="view-daily" style="display:none" class="pm-animate-2">
        <div class="pm-card" style="padding:24px;">
            <div id="daily-container"></div>
        </div>
    </div>

    <div id="tooltip" style="
        display:none;
        position:fixed;
        background:var(--pm-bg-card);
        border:1px solid var(--pm-border-hover);
        border-radius:var(--pm-radius);
        padding:14px 16px;
        font-size:12px;
        color:var(--pm-text);
        z-index:1000;
        pointer-events:none;
        min-width:200px;
        font-family:var(--pm-font);
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.5);
    "></div>

<script>
const MONTHS = [
    @js(__('Gennaio')), @js(__('Febbraio')), @js(__('Marzo')), @js(__('Aprile')),
    @js(__('Maggio')), @js(__('Giugno')), @js(__('Luglio')), @js(__('Agosto')),
    @js(__('Settembre')), @js(__('Ottobre')), @js(__('Novembre')), @js(__('Dicembre')),
];
const DAYS = [
    @js(__('Lun')), @js(__('Mar')), @js(__('Mer')), @js(__('Gio')),
    @js(__('Ven')), @js(__('Sab')), @js(__('Dom')),
];

const LABEL = {
    occupied: @js(__('Occupati')),
    free: @js(__('Liberi')),
    capacity: @js(__('Capacità')),
    noRes: @js(__('Nessuna prenotazione')),
    free_short: @js(__('lib.')),
    loading: @js(__('caricamento...')),
    empty: @js(__('Nessuna prenotazione in questo mese.')),
    free_day: @js(__('libero')),
    previousShort: @js(__('Prec')),
    nextShort: @js(__('Succ')),
    channel: @js(__('Canale')),
    entry: @js(__('Ingresso')),
    exit: @js(__('Uscita')),
    noStay: @js(__('Nessuna sosta in questa giornata per :product.')),
    sevenDayView: @js(__('Vista 7 giorni')),
    previous: @js(__('Precedente')),
    next: @js(__('Successiva')),
    channels: @js(__('Dettaglio canali')),
};

// Genera snippet di configurazione injectati da Blade
const PRODUCTS = {};
@foreach($products as $prod)
PRODUCTS['{{ $prod->code }}'] = {
    name: @js(__($prod->name)),
    capacity: {{ $prod->capacity }},
};
@endforeach

// Colori dinamici per i prodotti - o fissi se vogliamo
const PRODUCT_COLORS = {
    'auto_open':         '#3b82f6', // blue
    'auto_covered':      '#10b981', // emerald
    'truck_open':        '#f59e0b', // amber
    'truck_covered':     '#8b5cf6', // violet
    'camper_open':       '#ec4899', // pink
};

function getColor(code) {
    return PRODUCT_COLORS[code] || '#64748b'; // slate as fallback
}

function occupancyColor(occupied, capacity) {
    if (capacity === 0) return '#334155';
    const pct = occupied / capacity;
    if (pct >= 0.95) return '#ef4444'; // Critico
    if (pct >= 0.80) return '#f59e0b'; // Alto
    return '#10b981'; // Normale
}

function occupancyBg(occupied, capacity) {
    if (capacity === 0) return 'rgba(255,255,255,0.03)';
    const pct = occupied / capacity;
    if (pct >= 0.95) return 'rgba(239,68,68,0.15)';
    if (pct >= 0.80) return 'rgba(245,158,11,0.15)';
    return 'rgba(16,185,129,0.08)';
}

let physicalCapacity = {{ $totalSpots }};
let currentMonth = {{ now()->month }};
let currentYear  = {{ now()->year }};
let currentTab       = 'timeline';
let data             = null;
let timelineStartDay = null;
let currentDailyDay  = null;

function prevMonth() {
    currentMonth--;
    if (currentMonth < 1) { currentMonth = 12; currentYear--; }
    loadData();
}

function nextMonth() {
    currentMonth++;
    if (currentMonth > 12) { currentMonth = 1; currentYear++; }
    loadData();
}

function switchTab(tab) {
    currentTab = tab;
    document.getElementById('view-timeline').style.display = tab === 'timeline' ? 'block' : 'none';
    document.getElementById('view-monthly').style.display  = tab === 'monthly'  ? 'block' : 'none';
    document.getElementById('view-daily').style.display    = tab === 'daily'    ? 'block' : 'none';
    
    document.getElementById('tab-timeline').className = 'pm-btn pm-btn-sm ' + (tab === 'timeline' ? 'pm-btn-primary' : 'pm-btn-secondary');
    document.getElementById('tab-monthly').className  = 'pm-btn pm-btn-sm ' + (tab === 'monthly'  ? 'pm-btn-primary' : 'pm-btn-secondary');
    document.getElementById('tab-daily').className    = 'pm-btn pm-btn-sm ' + (tab === 'daily'    ? 'pm-btn-primary' : 'pm-btn-secondary');
    if (data) render();
}

async function loadData() {
    document.getElementById('calendar-subtitle').textContent = LABEL.loading;
    const res = await fetch(`{{ route('calendar.data') }}?month=${currentMonth}&year=${currentYear}&parking_id={{ $parking->id }}`);
    data = await res.json();
    document.getElementById('calendar-subtitle').textContent =
        MONTHS[currentMonth - 1] + ' ' + currentYear;

    timelineStartDay = null;
    currentDailyDay  = null;
    render();
}

function render() {
    if (currentTab === 'timeline') renderTimeline();
    else if (currentTab === 'monthly') renderMonthly();
    else renderDaily();
}

function shiftTimeline(offset) {
    if (timelineStartDay === null) return;
    timelineStartDay += offset;
    if (timelineStartDay < 1) timelineStartDay = 1;
    if (timelineStartDay > data.days) {
        timelineStartDay = data.days - 6;
        if (timelineStartDay < 1) timelineStartDay = 1;
    }
    renderTimeline();
}

function shiftDaily(offset) {
    if (currentDailyDay === null) return;
    currentDailyDay += offset;
    if (currentDailyDay < 1) currentDailyDay = 1;
    if (currentDailyDay > data.days) currentDailyDay = data.days;
    renderDaily();
}

function calcDayOccupancy(dateStr) {
    const cur = new Date(dateStr + 'T00:00:00');
    const result = {};

    Object.keys(PRODUCTS).forEach(code => {
        result[code] = {
            name:         PRODUCTS[code].name,
            capacity:     PRODUCTS[code].capacity,
            occupied:     0,
            free:         PRODUCTS[code].capacity,
            reservations: [],
            platforms:    {} // per il breakdown
        };
    });

    data.reservations.forEach(r => {
        const start = new Date(r.starts_at + 'T00:00:00');
        const end   = new Date(r.ends_at   + 'T00:00:00');
        if (cur >= start && cur <= end) {
            const pCode = r.product_code || 'unknown';
            
            // Failsafe per prodotti eliminati/inattivi ma ancora nelle query storiche
            if (!result[pCode]) {
                result[pCode] = {
                    name: r.product_name || pCode,
                    capacity: 0,
                    occupied: 0,
                    free: 0,
                    reservations: [],
                    platforms: {}
                };
            }

            result[pCode].occupied += r.spots;
            result[pCode].reservations.push(r);
            
            // Incrementa stats per breakdown
            const platSlug = r.platform_slug || 'unknown';
            const platName = r.platform || @js(__('Sconosciuto'));
            if (!result[pCode].platforms[platSlug]) {
                result[pCode].platforms[platSlug] = { name: platName, count: 0 };
            }
            result[pCode].platforms[platSlug].count += r.spots;
        }
    });

    Object.keys(result).forEach(code => {
        result[code].free = Math.max(0, result[code].capacity - result[code].occupied);
    });

    return result;
}

function renderDaily() {
    const container = document.getElementById('daily-container');
    const year      = currentYear;
    const month     = currentMonth;
    
    if (currentDailyDay === null) {
        if (year === new Date().getFullYear() && month === new Date().getMonth() + 1) {
            currentDailyDay = new Date().getDate();
        } else {
            currentDailyDay = 1;
        }
    }
    
    const dateStr = `${year}-${String(month).padStart(2,'0')}-${String(currentDailyDay).padStart(2,'0')}`;
    const occ = calcDayOccupancy(dateStr);
    const dow = new Date(dateStr).getDay();
    const dowStr = DAYS[dow === 0 ? 6 : dow - 1];
    
    let html = `
        <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid var(--pm-border); padding-bottom:16px; margin-bottom:24px;">
            <div style="display:flex; align-items:baseline; gap:12px;">
                <div style="font-size:24px; font-weight:600; color:var(--pm-text); font-family:var(--pm-mono); line-height:1">${String(currentDailyDay).padStart(2,'0')} / ${String(month).padStart(2,'0')}</div>
                <div style="font-size:14px; font-weight:500; color:var(--pm-text-muted); text-transform:uppercase;">${dowStr}</div>
            </div>
            <div style="display:flex; gap:8px;">
                <button onclick="shiftDaily(-1)" class="pm-btn pm-btn-secondary pm-btn-sm">◄ ${LABEL.previousShort}</button>
                <button onclick="shiftDaily(1)" class="pm-btn pm-btn-secondary pm-btn-sm">${LABEL.nextShort} ►</button>
            </div>
        </div>
    `;

    Object.keys(occ).forEach(code => {
        const p = occ[code];
        // Mostriamo solo categorie con prenotazioni per non intasare la view giornaliera, 
        // a meno che non ci siano pochissime categorie
        if (p.occupied === 0 && Object.keys(occ).length > 3) return;

        const color = getColor(code);
        
        html += `
            <div style="margin-bottom: 32px;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; background:rgba(255,255,255,0.02); padding:12px 16px; border-radius:var(--pm-radius-sm); border-left:4px solid ${color};">
                    <div style="display:flex; align-items:center; gap:8px;">
                        <div style="font-size:18px; font-weight:600; color:var(--pm-text);">${p.name}</div>
                    </div>
                    <div style="font-size:14px; font-family:var(--pm-mono); color:var(--pm-text-muted);">
                        ${LABEL.occupied}: <span style="color:${occupancyColor(p.occupied, p.capacity)}; font-weight:600">${p.occupied} / ${p.capacity}</span>
                    </div>
                </div>
        `;
        
        if (p.reservations && p.reservations.length > 0) {
            p.reservations.sort((a, b) => {
                const timeA = new Date(a.starts_at + 'T' + a.starts_at_time + ':00').getTime();
                const timeB = new Date(b.starts_at + 'T' + b.starts_at_time + ':00').getTime();
                return timeA - timeB;
            });
            html += `<div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 16px;">`;
            p.reservations.forEach(r => {
                const plateHtml = r.license_plate 
                    ? `<div style="font-family:var(--pm-mono); font-size:13px; font-weight:600; color:var(--pm-accent); background:rgba(59,130,246,0.1); padding:4px 8px; border-radius:4px; flex-shrink:0;">${r.license_plate}</div>`
                    : '';
                const flightHtml = r.flight_reference 
                    ? `<span style="font-family:var(--pm-mono); font-size:13px; font-weight:600; color:var(--pm-accent); background:rgba(59,130,246,0.1); padding:4px 8px; border-radius:4px; flex-shrink:0; margin-left: 4px;">${r.flight_reference}</span>`
                    : '';

                html += `
                    <div style="background:var(--pm-bg); border:1px solid var(--pm-border); padding:16px; border-radius:var(--pm-radius);">
                        <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:12px;">
                            <div style="font-weight:600; font-size:15px; color:var(--pm-text); line-height:1.2;">${r.customer_name}</div>
                            <div style="display:flex;">
                                ${plateHtml}
                                ${flightHtml}
                            </div>
                        </div>
                        <div style="display:flex; align-items:center; gap:6px; margin-bottom: 12px; font-size:11px; color:var(--pm-text-muted);">
                            ${LABEL.channel}: <span style="color:var(--pm-text);">${r.platform}</span>
                        </div>
                        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:8px;">
                            <div>
                                <div style="font-size:10px; color:var(--pm-text-dim); text-transform:uppercase;">${LABEL.entry}</div>
                                <div style="font-size:13px; font-family:var(--pm-mono); color:var(--pm-text);">${r.starts_at_time} <span style="font-size:11px; opacity:0.5; margin-left:4px">${formatDateIT(r.starts_at)}</span></div>
                            </div>
                            <div>
                                <div style="font-size:10px; color:var(--pm-text-dim); text-transform:uppercase;">${LABEL.exit}</div>
                                <div style="font-size:13px; font-family:var(--pm-mono); color:var(--pm-text);">${r.ends_at_time} <span style="font-size:11px; opacity:0.5; margin-left:4px">${formatDateIT(r.ends_at)}</span></div>
                            </div>
                        </div>
                    </div>
                `;
            });
            html += `</div>`;
        } else {
            html += `<div style="padding: 16px; text-align:center; font-size:13px; color:var(--pm-text-dim); border: 1px dashed var(--pm-border); border-radius:var(--pm-radius);">${LABEL.noStay.replace(':product', p.name)}</div>`;
        }
        
        html += `</div>`;
    });
    
    container.innerHTML = html;
}


function renderTimeline() {
    const container = document.getElementById('timeline-container');
    const days      = data.days;
    const year      = currentYear;
    const month     = currentMonth;
    const today     = new Date().toISOString().slice(0, 10);

    const productKeys = Object.keys(PRODUCTS);

    if (productKeys.length === 0) {
        container.innerHTML = `<div style="padding:32px 0;text-align:center;color:var(--pm-text-muted);font-size:13px">${LABEL.empty}</div>`;
        return;
    }

    let html = `<div style="display:flex;flex-direction:column;min-width:100%;">`;

    // HEADER ROW (Products) - STICKY
    html += `<div style="
        display:flex;
        border-bottom:1px solid var(--pm-border);
        padding-top:8px;
        padding-bottom:12px;
        margin-bottom:0;
        position:sticky;
        top:0;
        background:var(--pm-bg-card, #0f172a);
        z-index:10;
        align-items:center;
    ">`;
    // Left column: Navigatore timeline
    html += `<div style="width:100px;flex-shrink:0;padding-left:14px;display:flex;flex-direction:column;justify-content:center;gap:6px;">
        <div style="font-size:10px;font-family:var(--pm-mono);color:var(--pm-text-muted);text-transform:uppercase;">${LABEL.sevenDayView}</div>
        <div style="display:flex;gap:4px;">
            <button onclick="shiftTimeline(-7)" class="pm-btn pm-btn-secondary pm-btn-sm" style="padding:4px 8px;font-size:10px;line-height:1" title="${LABEL.previous}">◄</button>
            <button onclick="shiftTimeline(7)" class="pm-btn pm-btn-secondary pm-btn-sm" style="padding:4px 8px;font-size:10px;line-height:1" title="${LABEL.next}">►</button>
        </div>
    </div>`;
    
    productKeys.forEach(code => {
        const prod  = PRODUCTS[code];
        const color = getColor(code);
        html += `<div style="flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;">
             <div style="display:flex;align-items:center;gap:6px;">
                 <div style="width:8px;height:8px;border-radius:50%;background:${color};"></div>
                 <div style="font-size:13px;font-weight:500;color:var(--pm-text);text-align:center;">${prod.name}</div>
             </div>
             <div style="font-size:11px;font-family:var(--pm-mono);color:var(--pm-text-muted);margin-top:4px;">
                 ${LABEL.capacity}: ${prod.capacity}
             </div>
        </div>`;
    });
    html += `</div>`; // End Header

    if (timelineStartDay === null) {
        if (year === new Date().getFullYear() && month === new Date().getMonth() + 1) {
            timelineStartDay = new Date().getDate();
        } else {
            timelineStartDay = 1;
        }
    }
    const endDay = Math.min(days, timelineStartDay + 6);

    // DATA ROWS (Days)
    for (let d = timelineStartDay; d <= endDay; d++) {
        const dateStr = `${year}-${String(month).padStart(2,'0')}-${String(d).padStart(2,'0')}`;
        const occ     = calcDayOccupancy(dateStr);
        const dow     = new Date(dateStr).getDay();
        const isWknd  = dow === 0 || dow === 6;
        const isToday = dateStr === today;
        const dateBorder = isToday ? 'border-left:2px solid var(--pm-accent);' : 'border-left:2px solid transparent;';
        const dowStr  = DAYS[dow === 0 ? 6 : dow - 1]; 

        html += `<div style="display:flex;align-items:stretch;min-height:72px;border-bottom:1px solid var(--pm-border); ${isWknd ? 'background:rgba(255,255,255,0.015);' : ''}">`;

        // Date Column
        html += `<div style="
            width:100px;
            flex-shrink:0;
            display:flex;
            flex-direction:column;
            align-items:flex-start;
            justify-content:center;
            padding-left:14px;
            ${dateBorder}
            border-right:1px solid var(--pm-border);
        ">
             <div style="font-size:18px;font-family:var(--pm-mono);font-weight:${isToday ? '600' : '400'};color:${isToday ? 'var(--pm-accent)' : 'var(--pm-text)'};line-height:1">${String(d).padStart(2,'0')}</div>
             <div style="font-size:11px;color:${isWknd ? 'var(--pm-amber)' : 'var(--pm-text-muted)'};text-transform:uppercase;margin-top:4px;">${dowStr}</div>
        </div>`;

        // Product Columns
        productKeys.forEach((code, idx) => {
            const prod  = PRODUCTS[code];
            const pOcc = occ[code] || { occupied: 0, capacity: prod.capacity, free: prod.capacity };
            const bgColor   = occupancyBg(pOcc.occupied, pOcc.capacity);
            const textColor = occupancyColor(pOcc.occupied, pOcc.capacity);
            const innerBorder = idx < productKeys.length - 1 ? 'border-right:1px solid var(--pm-border);' : '';

            // Per il tooltip serializziamo l'oggetto platforms (il breakdown per canale)
            const breakdownData = encodeURIComponent(JSON.stringify(pOcc.platforms));

            html += `<div
                data-date="${dateStr}"
                data-product="${prod.name}"
                data-occupied="${pOcc.occupied}"
                data-capacity="${pOcc.capacity}"
                data-free="${pOcc.free}"
                data-breakdown="${breakdownData}"
                onmouseenter="showTooltipCell(event,this)"
                onmouseleave="hideTooltip()"
                style="
                    flex:1;
                    display:flex;
                    flex-direction:column;
                    align-items:center;
                    justify-content:center;
                    background:${pOcc.occupied > 0 ? bgColor : 'transparent'};
                    ${innerBorder}
                    cursor:${pOcc.occupied > 0 ? 'pointer' : 'default'};
                    transition:background 0.15s;
                    padding:8px 4px;
                "
            >`;

            if (pOcc.occupied > 0) {
                html += `
                    <div style="font-family:var(--pm-mono);font-size:16px;font-weight:600;color:${textColor};line-height:1">
                        ${pOcc.occupied}
                    </div>
                    <div style="font-family:var(--pm-mono);font-size:12px;color:var(--pm-text-muted);margin-top:6px;line-height:1">
                        / ${pOcc.capacity}
                    </div>
                `;
            } else {
                html += `<div style="font-family:var(--pm-mono);font-size:13px;color:var(--pm-text-dim)">—</div>`;
            }

            html += `</div>`;
        });

        html += `</div>`; // End Date Row
    }

    html += `</div>`;
    container.innerHTML = html;
}

function renderMonthly() {
    const container = document.getElementById('monthly-container');
    const days      = data.days;
    const year      = currentYear;
    const month     = currentMonth;
    const today     = new Date().toISOString().slice(0, 10);

    const firstDow = new Date(`${year}-${String(month).padStart(2,'0')}-01`).getDay();
    const offset   = firstDow === 0 ? 6 : firstDow - 1;

    let html = '';

    html += `<div style="display:grid;grid-template-columns:repeat(7,1fr);gap:4px;margin-bottom:8px">`;
    DAYS.forEach(d => {
        html += `<div style="text-align:center;font-size:11px;font-family:var(--pm-mono);color:var(--pm-text-muted);padding:4px 0">${d}</div>`;
    });
    html += `</div>`;

    html += `<div style="display:grid;grid-template-columns:repeat(7,1fr);gap:4px">`;

    for (let i = 0; i < offset; i++) {
        html += `<div></div>`;
    }

    for (let d = 1; d <= days; d++) {
        const dateStr = `${year}-${String(month).padStart(2,'0')}-${String(d).padStart(2,'0')}`;
        const isToday = dateStr === today;
        const dow     = new Date(dateStr).getDay();
        const isWknd  = dow === 0 || dow === 6;
        const occ     = calcDayOccupancy(dateStr);

        let totalOccupied  = 0;
        let hasAlert       = false;

        Object.values(occ).forEach(p => {
            totalOccupied  += p.occupied;
            // Valutazione alert per singolo prodotto se supera 94%
            if (p.capacity > 0 && (p.occupied / p.capacity) >= 0.94) {
                hasAlert = true;
            }
        });
        
        const totalFree = Math.max(0, physicalCapacity - totalOccupied);

        // Calcolo dello sfondo e dei bordi del giorno
        let bgStyle = 'rgba(255,255,255,0.02)';
        let borderStyle = 'var(--pm-border)';

        if (hasAlert) {
            bgStyle = 'rgba(239, 68, 68, 0.15)'; // rosso tenue
            borderStyle = 'rgba(239, 68, 68, 0.5)';
        } else if (isToday) {
            bgStyle = 'rgba(59,130,246,0.08)';
            borderStyle = 'rgba(59,130,246,0.3)';
        } else if (isWknd) {
            bgStyle = 'rgba(245,158,11,0.03)';
        }

        html += `<div
            data-date="${dateStr}"
            data-occupied="${totalOccupied}"
            data-capacity="${physicalCapacity}"
            data-free="${totalFree}"
            onmouseenter="showTooltipDay(event,this,${JSON.stringify(occ).replace(/"/g, '&quot;')})"
            onmouseleave="hideTooltip()"
            style="
                min-height:90px;
                background:${bgStyle};
                border:1px solid ${borderStyle};
                border-radius:var(--pm-radius-sm);
                padding:8px;
                cursor:pointer;
            ">
            <div style="
                font-size:12px;
                font-family:var(--pm-mono);
                color:${isToday ? 'var(--pm-accent)' : 'var(--pm-text-muted)'};
                font-weight:${isToday ? '500' : '400'};
                margin-bottom:6px;
            ">${d}</div>`;

        if (totalOccupied > 0) {
            const totalColor = occupancyColor(totalOccupied, physicalCapacity);
            html += `
                <div style="font-family:var(--pm-mono);font-size:14px;font-weight:500;color:${totalColor};line-height:1;margin-bottom:3px">
                    ${totalOccupied}/${physicalCapacity}
                </div>
                <div style="font-family:var(--pm-mono);font-size:10px;color:var(--pm-text-muted);margin-bottom:6px">
                    ${LABEL.free_short} ${totalFree}
                </div>`;

            Object.entries(occ).forEach(([code, p]) => {
                if (p.occupied === 0) return;
                const color = getColor(code);
                html += `<div style="display:flex;align-items:center;gap:5px;margin-bottom:3px">
                    <div style="width:6px;height:6px;border-radius:50%;background:${color};flex-shrink:0"></div>
                    <div style="font-size:10px;font-family:var(--pm-mono);color:var(--pm-text-muted);text-overflow:ellipsis;overflow:hidden;white-space:nowrap;">
                        ${p.name.split(' ')[0]}: ${p.occupied}
                    </div>
                </div>`;
            });
        } else {
            html += `<div style="font-size:11px;color:var(--pm-text-dim);font-family:var(--pm-mono)">${LABEL.free_day}</div>`;
        }

        html += `</div>`;
    }

    html += `</div>`;
    container.innerHTML = html;
}

function formatDateIT(dateStr) {
    const parts = dateStr.split('-');
    if (parts.length === 3) return `${parts[2]}-${parts[1]}-${parts[0]}`;
    return dateStr;
}

function showTooltipCell(e, el) {
    const t = document.getElementById('tooltip');
    
    let breakdownHtml = '';
    const breakdown = JSON.parse(decodeURIComponent(el.dataset.breakdown));
    
    if (Object.keys(breakdown).length > 0) {
        breakdownHtml += `<div style="border-top:1px solid var(--pm-border);padding-top:8px;margin-top:10px;">`;
        breakdownHtml += `<div style="font-size:10px;color:var(--pm-text-dim);margin-bottom:6px;text-transform:uppercase;">${LABEL.channels}</div>`;
        Object.values(breakdown).forEach(plat => {
            breakdownHtml += `
                <div style="display:flex;justify-content:space-between;margin-bottom:4px;">
                    <div style="font-size:12px;color:var(--pm-text-muted)">${plat.name}</div>
                    <div style="font-family:var(--pm-mono);font-size:12px;color:var(--pm-text);font-weight:500">${plat.count}</div>
                </div>
            `;
        });
        breakdownHtml += `</div>`;
    }
    
    t.innerHTML = `
        <div style="font-weight:500;color:var(--pm-text);margin-bottom:8px">
            ${el.dataset.product} <span style="color:var(--pm-text-muted);font-weight:normal;font-size:11px;margin-left:4px">${formatDateIT(el.dataset.date)}</span>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;text-align:center;">
            <div>
                <div style="font-family:var(--pm-mono);font-size:18px;font-weight:500;color:var(--pm-red)">${el.dataset.occupied}</div>
                <div style="font-size:11px;color:var(--pm-text-muted)">${LABEL.occupied}</div>
            </div>
            <div>
                <div style="font-family:var(--pm-mono);font-size:18px;font-weight:500;color:var(--pm-green)">${el.dataset.free}</div>
                <div style="font-size:11px;color:var(--pm-text-muted)">${LABEL.free}</div>
            </div>
        </div>
        ${breakdownHtml}
    `;
    t.style.display = 'block';
    positionTooltip(e);
}

function showTooltipDay(e, el, occ) {
    const t = document.getElementById('tooltip');
    let rows = '';
    Object.entries(occ).forEach(([code, p]) => {
        const color = getColor(code);
        // Costruzione mini-breakdown per il tooltip mensile
        let breakdownStr = '';
        if (p.occupied > 0 && Object.keys(p.platforms).length > 0) {
            const arr = Object.values(p.platforms).map(pl => `${pl.name}: ${pl.count}`);
            breakdownStr = `<div style="font-size:10px;color:var(--pm-text-dim);margin-left:13px;margin-top:2px;">${arr.join(', ')}</div>`;
        }

        rows += `
            <div style="margin-bottom:8px">
                <div style="display:flex;align-items:center;justify-content:space-between;gap:16px;">
                    <div style="display:flex;align-items:center;gap:6px">
                        <div style="width:7px;height:7px;border-radius:50%;background:${color};flex-shrink:0"></div>
                        <div style="font-size:12px;color:var(--pm-text-muted)">${p.name}</div>
                    </div>
                    <div style="font-family:var(--pm-mono);font-size:12px;color:${occupancyColor(p.occupied, p.capacity)}">
                        ${p.occupied} / ${p.capacity}
                    </div>
                </div>
                ${breakdownStr}
            </div>`;
    });

    t.innerHTML = `
        <div style="font-weight:500;color:var(--pm-text);margin-bottom:10px">${formatDateIT(el.dataset.date)}</div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;text-align:center;margin-bottom:10px">
            <div>
                <div style="font-family:var(--pm-mono);font-size:18px;font-weight:500;color:var(--pm-red)">${el.dataset.occupied}</div>
                <div style="font-size:11px;color:var(--pm-text-muted)">${LABEL.occupied}</div>
            </div>
            <div>
                <div style="font-family:var(--pm-mono);font-size:18px;font-weight:500;color:var(--pm-green)">${el.dataset.free}</div>
                <div style="font-size:11px;color:var(--pm-text-muted)">${LABEL.free}</div>
            </div>
            
        </div>
        <div style="border-top:1px solid var(--pm-border);padding-top:10px">${rows}</div>
    `;
    t.style.display = 'block';
    positionTooltip(e);
}

function positionTooltip(e) {
    const t = document.getElementById('tooltip');
    const x = e.clientX + 14;
    const y = e.clientY + 14;
    const w = t.offsetWidth;
    const h = t.offsetHeight;
    t.style.left = (x + w > window.innerWidth  ? x - w - 28 : x) + 'px';
    t.style.top  = (y + h > window.innerHeight ? y - h - 28 : y) + 'px';
}

function hideTooltip() {
    document.getElementById('tooltip').style.display = 'none';
}

document.addEventListener('mousemove', function(e) {
    if (document.getElementById('tooltip').style.display === 'block') {
        positionTooltip(e);
    }
});

loadData();
</script>

</x-app-layout>
