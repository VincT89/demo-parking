@component('mail::message')
# Richiesta blocco disponibilità

Gentile Partner,

vi informiamo che per la giornata del **{{ $day->format('d/m/Y') }}** il parcheggio **{{ $parking->name }}** risulta completo.

Al momento risultano occupati **{{ $occupied }} posti** su una capienza disponibile di **{{ $capacity }} posti**.

Vi chiediamo cortesemente di bloccare temporaneamente la vendita e la disponibilità per la data indicata, al fine di evitare ulteriori prenotazioni e possibili disservizi operativi.

Grazie per la collaborazione.

Cordiali saluti,  
{{ config('app.name') }}
@endcomponent
