# Sodano Parking Demo

Demo isolata del gestionale parcheggi, preparata per presentazioni commerciali. Il progetto non contiene una cartella `.git` e non è collegato a un repository remoto.

## Cosa mostra

- dashboard operativa, calendario, prenotazioni, disponibilità, report e avvisi;
- un mese mobile di prenotazioni sintetiche: 7 giorni precedenti e 23 successivi alla data corrente;
- tre canali dimostrativi che simulano creazioni, modifiche e cancellazioni ricevute via API;
- interfaccia in italiano, inglese britannico e olandese tramite file JSON;
- marchio Sodano Consulting in blu;
- modulo pubblico per creare prenotazioni esclusivamente dimostrative.

Tutti i nomi dei clienti, gli indirizzi e-mail, le targhe, i riferimenti di volo e gli identificativi sono sintetici. Gli indirizzi e-mail usano il dominio riservato `example.test`.

## Sicurezza della demo

Con `DEMO_MODE=true` gli adapter reali vengono sostituiti da generatori locali deterministici. Le richieste HTTP non previste vengono rifiutate e gli endpoint Stripe e PayPal sono bloccati prima di invocare i relativi servizi. La conferma "paga in struttura" registra soltanto un evento dimostrativo nel database SQLite locale.

La demo contiene credenziali note e non va pubblicata su Internet così com'è. Per una demo online servono almeno credenziali private, protezione dell'accesso, HTTPS, limiti di frequenza e un ripristino automatico dei dati.

## Avvio locale

Requisiti: PHP 8.2 o successivo, Composer e Node.js.

Preparazione iniziale:

```powershell
Copy-Item .env.example .env
php artisan key:generate
New-Item -ItemType File database/demo.sqlite
```

Nel file `.env`, impostare `DB_DATABASE` con il percorso assoluto di `database/demo.sqlite`, quindi:

```powershell
composer install
npm install
npm run build
php artisan demo:reset
php artisan serve
```

Accesso amministratore:

```text
Email: demo@sodanoconsulting.it
Password: password
```

Accesso staff:

```text
Email: staff-demo@sodanoconsulting.it
Password: password
```

## Ripristino dello scenario

Il comando seguente ricrea il database SQLite e rigenera il mese di prenotazioni sintetiche:

```powershell
php artisan demo:reset
```

La seconda risposta simulata modifica parte delle prenotazioni, ne annulla alcune e ne aggiunge di nuove. Per importare soltanto la prima risposta:

```powershell
php artisan demo:reset --single-sync
```

## Lingue

Le traduzioni sono in:

- `lang/it.json`
- `lang/en_GB.json`
- `lang/nl.json`

La lingua scelta viene salvata nella sessione. L'inglese usa la variante britannica `en-GB`.

## Verifiche

```powershell
php artisan test
npm run build
```
