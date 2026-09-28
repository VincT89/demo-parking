export async function toggleMovement(reservationId, type, checkbox, autoPrint = false) {
    const previous = !checkbox.checked;
    const labels = document.documentElement.dataset;
    const errorBox = document.getElementById('pm-request-error');
    if (errorBox) errorBox.hidden = true;
    let printWindow = null;
    if (type === 'exited' && checkbox.checked && autoPrint) printWindow = window.open('about:blank', '_blank');
    checkbox.disabled = true;
    try {
        const response = await fetch('/reservations/' + reservationId + '/toggle-movement', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
            body: JSON.stringify({type, value: checkbox.checked}),
        });
        if (!response.ok) throw new Error(response.status === 409 ? labels.contextChanged : labels.saveError);
        const data = await response.json();
        if (data.print_url) {
            if (printWindow) printWindow.location.href = data.print_url;
            else if (!window.open(data.print_url, '_blank')) {
                if (errorBox) { errorBox.textContent = labels.printBlocked; errorBox.hidden = false; }
            }
        } else if (printWindow) printWindow.close();
    } catch (error) {
        checkbox.checked = previous;
        if (printWindow) printWindow.close();
        if (errorBox) {
            errorBox.textContent = error instanceof TypeError ? labels.networkError : error.message;
            errorBox.hidden = false;
            errorBox.scrollIntoView({block: 'nearest'});
        }
    } finally { checkbox.disabled = false; }
}
