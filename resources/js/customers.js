document.querySelectorAll('[data-customer-picker]').forEach((picker) => {
    const form = picker.closest('form');
    const search = picker.querySelector('[data-customer-search]');
    if (!search || !form) return;
    const customerId = picker.querySelector('[data-customer-id]');
    const results = picker.querySelector('[data-customer-results]');
    const feedback = picker.querySelector('[data-customer-feedback]');
    const clear = picker.querySelector('[data-customer-clear]');
    const vehicle = picker.querySelector('[data-customer-vehicle]');
    const vehicleField = picker.querySelector('[data-customer-vehicle-field]');
    let timer;
    let request;
    let generation = 0;
    const field = (name) => form.querySelector('[name="' + name + '"]');
    const fill = (name, value) => {
        const input = field(name);
        if (input) {
            input.value = value ?? '';
            input.dispatchEvent(new Event('input', { bubbles: true }));
        }
    };
    const closeResults = () => { results.hidden = true; results.replaceChildren(); };
    const choose = (customer, autofill = true) => {
        generation += 1;
        request?.abort();
        clearTimeout(timer);
        customerId.value = customer.id;
        search.value = customer.name;
        clear.hidden = false;
        feedback.textContent = '';
        closeResults();
        if (autofill) {
            fill('customer_name', customer.name);
            fill('customer_email', customer.email);
            fill('customer_phone', customer.phone);
        }
        vehicle.length = 1;
        (customer.plates ?? []).forEach((plate) => vehicle.add(new Option(plate, plate)));
        vehicleField.hidden = !customer.plates?.length;
        if (autofill && !field('license_plate')?.value && customer.plates?.length === 1) {
            fill('license_plate', customer.plates[0]);
            vehicle.value = customer.plates[0];
        }
    };
    search.addEventListener('input', () => {
        const query = search.value.trim();
        customerId.value = '';
        clear.hidden = true;
        vehicleField.hidden = true;
        closeResults();
        request?.abort();
        clearTimeout(timer);
        const current = ++generation;
        feedback.textContent = query.length < 2 ? picker.dataset.minLabel : '';
        if (query.length < 2) return;
        timer = setTimeout(async () => {
            request = new AbortController();
            const url = new URL(picker.dataset.url, window.location.origin);
            url.searchParams.set('search', query);
            try {
                const response = await fetch(url, { signal: request.signal, headers: { Accept: 'application/json' }, credentials: 'same-origin' });
                if (!response.ok) throw new Error('Lookup failed');
                const customers = await response.json();
                if (current !== generation) return;
                closeResults();
                feedback.textContent = customers.length ? '' : picker.dataset.emptyLabel;
                customers.forEach((customer) => {
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'pm-customer-result';
                    const name = document.createElement('strong');
                    name.textContent = customer.name;
                    const contact = document.createElement('span');
                    contact.textContent = [customer.email, customer.phone].filter(Boolean).join(' · ');
                    button.append(name, contact);
                    button.addEventListener('click', () => choose(customer));
                    results.append(button);
                });
                results.hidden = customers.length === 0;
            } catch (error) {
                if (error.name !== 'AbortError' && current === generation) feedback.textContent = picker.dataset.errorLabel;
            }
        }, 200);
    });
    clear.addEventListener('click', () => {
        generation += 1;
        request?.abort();
        clearTimeout(timer);
        customerId.value = '';
        search.value = '';
        vehicleField.hidden = true;
        clear.hidden = true;
        feedback.textContent = '';
        closeResults();
        search.focus();
    });
    vehicle.addEventListener('change', () => { if (vehicle.value) fill('license_plate', vehicle.value); });
    picker.addEventListener('keydown', (event) => { if (event.key === 'Escape') closeResults(); });
    document.addEventListener('click', (event) => { if (!picker.contains(event.target)) closeResults(); });
    const initial = picker.querySelector('[data-customer-initial]');
    if (initial) choose(JSON.parse(initial.textContent), picker.dataset.autofill === 'true');
});
