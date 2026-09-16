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

## Anagrafica clienti e storico

La sezione **Clienti** raccoglie privati e aziende, recapiti, dati di fatturazione facoltativi, note e più targhe per cliente. Le schede sono ricercabili per nome, email, telefono o targa e filtrabili per tipo e stato.

Nei moduli di prenotazione, abbonamento e ingresso giornaliero puoi selezionare un cliente: i recapiti vengono proposti nel modulo e l'operazione entra nello storico della scheda. Gli ingressi di un abbonato ereditano il cliente dell'abbonamento. Lo storico include prenotazioni, abbonamenti, soste, pagamenti e fatture, con filtri per tipo e periodo.

Le operazioni precedenti all'introduzione dell'anagrafica possono essere recuperate con il seeder dedicato descritto sotto. Per i casi da verificare usa **Collega operazioni esistenti** dalla scheda cliente. Collegando un abbonamento si collegano anche le sue soste. Le modifiche al profilo non riscrivono i dati registrati nelle operazioni e nelle fatture.

L'archiviazione, riservata agli amministratori, conserva lo storico ma esclude il cliente dai nuovi inserimenti; un amministratore può riattivarlo.

Per installare questo aggiornamento su un database già esistente, eseguire la nuova migrazione nel normale deploy:

```sh
php artisan migrate --force
```

La migrazione aggiunge le tabelle dell'anagrafica e collegamenti facoltativi: non cancella i dati esistenti. **Non usare `demo:reset` o `migrate:fresh` per questo aggiornamento.** Il database di produzione non viene modificato dai test locali.

Per riempire l'anagrafica con i clienti delle prenotazioni, degli abbonamenti e delle soste già presenti, dopo la migrazione eseguire:

```sh
php artisan db:seed --class=CustomerSeeder --force
```

Specificare sempre `--class=CustomerSeeder`: il seeder generale del progetto svuota e ricrea altre tabelle e non va usato per questa operazione.

Il seeder clienti è ripetibile: elabora solo operazioni non ancora collegate, non cancella dati, non modifica recapiti o note delle schede esistenti e conserva dati originali e date delle operazioni. I pagamenti e le fatture diventano visibili tramite i collegamenti, senza essere ricreati. Le targhe vengono aggiunte senza sostituire quelle già associate, fino al limite di 30 per scheda.

Il riconoscimento richiede il nome insieme a email, telefono o targa normalizzati. I nominativi uguali da soli non vengono accorpati; senza identificativi ogni operazione conserva una scheda separata. Corrispondenze multiple o contrastanti, clienti archiviati, nomi mancanti e recapiti non validi sono segnalati per verifica manuale. Le soste degli abbonati ereditano il cliente già collegato al contratto, senza scambiare il conducente con il titolare. Gli abbonamenti con collegamenti manuali incoerenti non vengono modificati.

La preparazione di una nuova demo richiama ora questo seeder anche dopo l'importazione delle prenotazioni simulate. Non occorre rigenerare una demo esistente per popolare l'anagrafica.

## Verifiche

```powershell
php artisan test
npm run build
```
