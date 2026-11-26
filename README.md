# Sodano Parking Demo

Versione dimostrativa del gestionale Sodano Consulting per parcheggi, garage e navette aeroportuali. Il progetto è versionato con Git; il ramo di riferimento per questa integrazione è `main_vincenzo`.

La demo usa un database reale per conservare dati sintetici e operazioni di prova. Le simulazioni delle prenotazioni e dei pagamenti non rendono il database facoltativo.

## Documentazione

- [Guida commerciale in Word](docs/Presentazione_gestionale_parcheggi_e_garage_Sodano_Consulting.docx): presentazione delle funzionalità per sezioni, senza esempi pratici o dettagli tecnici.
- [Doppia gestione nella demo](docs/doppia-gestione-demo.md): isolamento dei dati, attivazione su un'installazione esistente e verifiche dedicate.

## Funzionalità

- **Dashboard**: occupazione, arrivi, partenze, prossime prenotazioni e avvisi del parcheggio selezionato.
- **Clienti**: privati e aziende, contatti, dati di fatturazione, più targhe, ricerca, controllo dei possibili duplicati, archiviazione e storico delle operazioni collegate.
- **Prenotazioni**: inserimento manuale, importazione dai canali, modifica, annullamento, filtri, movimenti dei veicoli, stato dei pagamenti ed esportazione per Excel.
- **Pagina prenotazioni (FO)**: collegamento pubblico dedicato al parcheggio, scelta del servizio, controllo della disponibilità, prezzo, conferma e riepilogo stampabile.
- **Calendario**: viste giornaliera, settimanale e mensile, elenchi di ingressi e uscite ed esportazione della giornata.
- **Garage**: abbonamenti mensili, quote di posti riservati, soste giornaliere, accessi degli abbonati, registrazione dell'uscita, tariffe configurabili, incassi manuali e storni.
- **Pagamenti degli abbonamenti**: collegamento Stripe con simulazione degli esiti nella demo. Le modifiche locali non equivalgono a modifiche dell'abbonamento presso Stripe.
- **Navette**: piano giornaliero ordinato per orario, calcolo dei viaggi dai passeggeri delle prenotazioni, gruppi, posti disponibili, mezzi, corse manuali, stati, filtri e assegnazioni.
- **Parcheggi e disponibilità**: categorie, capacità condivisa o per categoria, tariffe delle prenotazioni, quote riservate e blocchi temporanei.
- **Piattaforme**: Parkos, Vologio e ParkingMyCar, collegamenti ai parcheggi, associazioni delle categorie, sincronizzazione e registri degli esiti.
- **Analisi e avvisi**: risultati delle prenotazioni per periodo e canale, confronti, occupazione e situazioni da verificare.
- **Fatture**: documenti collegati alle prenotazioni, archivio, stati, simulatore locale e modalità Aruba configurabili.
- **Stampa**: biglietti per prenotazioni e soste garage, formati A4, A6, etichetta o personalizzato, orientamento e contenuti configurabili.
- **Gestioni e utenti**: parcheggi della stessa gestione oppure gestioni separate, ruoli per gestione, accessi assegnati esplicitamente e registro attività del parcheggio corrente.
- **Utilizzo**: account personale, visibilità delle password, interfaccia responsive e traduzioni in italiano, inglese britannico e olandese.

Il garage riserva una quantità di posti: non assegna stalli numerati su una planimetria. Il piano navette non comprende tracciamento dei mezzi, turni degli autisti o aggiornamenti dei voli in tempo reale. Il biglietto di ritiro non sostituisce una fattura o un documento fiscale.

## Allineamento delle funzioni operative

Sono disponibili la distinzione fra parcheggio standard e Car valet, i filtri valet e lavaggio, i relativi riepiloghi e le esportazioni. Nel modulo pubblico il lavaggio può essere interno, esterno o completo. I supplementi riprendono quelli del progetto operativo: valet 10 euro, lavaggio interno o esterno 10 euro, completo 20 euro, una sola volta per prenotazione. Al momento sono definiti nel codice.

Le correzioni manuali a nome, email, telefono, targa, volo, numero di persone e note prevalgono sulle sincronizzazioni successive. Le modifiche ai soli dati del cliente non richiedono una nuova disponibilità; modifiche all’occupazione e riattivazioni continuano a verificarla. Il riconoscimento valet comprende Parkos, ParkingMyCar e i servizi Roosh/Vologio esplicitamente associati al parcheggio.

Da **Parcheggi > WhatsApp e recensioni** si configurano gli inviti a recensire su Google: consenso facoltativo, un tentativo per prenotazione e almeno 24 ore dall’uscita effettivamente registrata dopo l’attivazione. In modalità demo gli inviti vengono solo simulati; per gli invii reali servono credenziali e modello Twilio approvato. Il testo del modello va approvato nella lingua desiderata. Impostazioni, consenso, filtri e nuovi riepiloghi sono tradotti nelle tre lingue.

Per aggiornare un’installazione esistente applicare le quattro nuove migrazioni con `php artisan migrate --force`, dopo il backup del database: `2026_10_26_000001_add_is_valet_to_reservations`, `2026_11_07_000001_add_manually_edited_fields_to_reservations`, `2026_11_17_000001_add_whatsapp_review_invitations`, `2026_11_25_000001_add_car_wash_to_reservations`. Non occorre rigenerare lo scenario demo. Lo scheduler include `whatsapp:send-review-invitations`; la riclassificazione facoltativa dello storico usa `reservations:backfill-valet ID_PARCHEGGIO --dry-run`, con `--with-catalog` per consultare anche Roosh.

## Gestione di più parcheggi

Una **gestione** riunisce utenti, ruoli, anagrafiche clienti e canali commerciali. Può comprendere più parcheggi. Prenotazioni, disponibilità, calendario, garage, navette, documenti, analisi e registro attività seguono il **parcheggio selezionato**.

- Creando un parcheggio nella gestione corrente si mantengono gli utenti e le anagrafiche di quella gestione.
- Selezionando **Crea una gestione separata**, il creatore diventa amministratore della nuova gestione e conserva i propri accessi precedenti. Gli altri utenti non ricevono automaticamente accesso.
- Un account abilitato a entrambe le gestioni può cambiare parcheggio dal selettore. Il ruolo può essere diverso nelle due gestioni.
- Un amministratore assegnato soltanto alla gestione B vede e amministra B, non A. Anche i collegamenti diretti e i riferimenti nei moduli rispettano questo isolamento.
- La sezione **Utenti** amministra gli accessi alla gestione del parcheggio selezionato. La revoca riguarda quella gestione e non elimina gli accessi alle altre. Non è possibile rimuovere o declassare l'ultimo amministratore.
- Un account esistente può essere riutilizzato soltanto da un amministratore che ne gestisce già l'accesso. Il riutilizzo non cambia la password esistente.
- Le schede aperte prima di un cambio parcheggio devono essere ricaricate prima di salvare, per evitare operazioni nel contesto sbagliato.

Le anagrafiche clienti sono condivise solo fra parcheggi della stessa gestione; lo storico della scheda mostra le operazioni del parcheggio corrente. Email uguali in gestioni diverse non comportano un accorpamento automatico dei clienti.

Il collegamento **Pagina prenotazioni (FO)** contiene il riferimento del parcheggio selezionato. Non assegna la richiesta a un parcheggio appartenente a una gestione diversa.

Il **Registro attività**, riservato agli amministratori, riporta data, autore, operazione, elemento interessato e campi modificati. Non è una copia completa dei valori precedenti né una funzione di ripristino.

## Scenario e credenziali dimostrative

Lo scenario comprende una gestione iniziale e una **Gestione B Demo**, con un parcheggio aggiuntivo e dati sintetici per mostrare la separazione degli accessi. Le simulazioni dei canali coprono una finestra configurabile, normalmente dai 7 giorni precedenti ai 23 successivi alla data di preparazione; B include prenotazioni dimostrative distribuite su 30 giorni.

| Account | Email | Accesso iniziale | Password iniziale |
| --- | --- | --- | --- |
| Amministratore demo | `demo@sodanoconsulting.it` | Gestioni A e B | `password` |
| Amministratore B Demo | `socio@sodanoconsulting.it` | Solo gestione B | `password` |
| Staff demo | `staff-demo@sodanoconsulting.it` | Personale operativo della gestione iniziale | `password` |

Sono credenziali di una nuova installazione demo, non di account reali. Il seeder aggiuntivo non reimposta password già modificate. Il login mostra gli accessi A+B e solo B esclusivamente con `DEMO_MODE=true`.

Clienti, targhe, voli e riferimenti delle prenotazioni dello scenario sono sintetici; le email dei clienti generati usano `example.test`. Gli indirizzi di accesso sopra indicati sono account dimostrativi e costituiscono un caso distinto.

## Sicurezza e servizi esterni

Con `DEMO_MODE=true`, i tre connettori delle piattaforme usano generatori locali. Il modulo pubblico conferma prenotazioni senza addebiti e il percorso Stripe degli abbonamenti dispone di una simulazione. Stripe e PayPal non vanno presentati come pagamenti reali attivi nella demo.

**La fatturazione Aruba richiede una cautela separata**: i relativi servizi esterni sono consentiti anche in modalità demo. Per presentazioni senza trasmissioni reali, mantenere **Simulatore locale** nelle impostazioni di fatturazione di ogni parcheggio e non inserire credenziali Aruba reali. `DEMO_MODE=true` da solo non garantisce il blocco di tutti gli invii di fatture.

Una nuova gestione non eredita automaticamente i pagamenti o gli accessi privati ai servizi esterni di un'altra. Il riutilizzo di una connessione condivisa richiede l'abilitazione prevista e la verifica del codice attribuito dalla piattaforma al parcheggio.

Per pubblicare una demo usare un ambiente dedicato, solo dati sintetici, HTTPS e un controllo dell'accesso adeguato. Le credenziali note mostrate nel login non sono una protezione per dati reali. Non caricare su Git `.env`, database, log, credenziali o file di runtime; sono esclusi tramite `.gitignore`.

## Nuova installazione locale

Requisiti del progetto: PHP 8.3 o successivo compatibile con le dipendenze, Composer e Node.js compatibile con Vite 8 (`20.19+` nella serie 20 oppure `22.12+`). Per SQLite occorre anche la relativa estensione PHP.

Queste istruzioni sono destinate a una **nuova installazione con database demo vuoto**. Per conservare dati già presenti usare invece la sezione di aggiornamento.

Installare le dipendenze dalla cartella `demo-parking`:

```powershell
composer install
npm ci
```

Preparare l'ambiente soltanto se i file non esistono già:

```powershell
if (-not (Test-Path .env)) { Copy-Item .env.example .env }
if (-not (Test-Path database/demo.sqlite)) { New-Item -ItemType File database/demo.sqlite }
```

Nel file `.env` impostare `DB_CONNECTION=sqlite`, `DB_DATABASE` con il percorso assoluto del file demo, `DEMO_MODE=true` e `APP_URL=http://127.0.0.1:8001`. Conservare `PAYMENTS_ENABLED=false`, `MAIL_MAILER=array` e l'italiano come lingua iniziale, come in `.env.example`.

**Prima del comando seguente verificare il database selezionato. `demo:reset` elimina tutte le tabelle del database configurato e ricrea lo scenario; non è limitato automaticamente a SQLite.** Non usarlo su un'installazione con dati da conservare.

```powershell
php artisan key:generate
php artisan demo:reset
npm run build
php artisan serve --host=127.0.0.1 --port=8001
```

Aprire `http://127.0.0.1:8001/login`. La preparazione completa crea anche lo scenario B e collega i clienti alle operazioni sintetiche.

## Aggiornamento di una demo già esistente

Fare prima un backup del database e verificare l'ambiente di destinazione. Aggiornare le dipendenze e applicare le migrazioni senza ricreare il database:

```powershell
composer install
npm ci
php artisan migrate --force
npm run build
```

Nel rilascio online usare le opzioni Composer appropriate all'ambiente e ricostruire le eventuali cache di configurazione, rotte e viste secondo il processo di deploy. Non generare una nuova `APP_KEY` per aggiornare un'installazione esistente.

Le migrazioni associano i dati e gli accessi esistenti alla gestione iniziale; non dividono automaticamente parcheggi già presenti in gestioni indipendenti. Il codice aggiornato e le migrazioni non popolano da soli il nuovo scenario B.

Per aggiungere B a una demo già inizializzata, con `DEMO_MODE=true` e l'account principale presente:

```powershell
php artisan db:seed --class=DemoManagementSeeder --force
```

Questo seeder crea i dati dimostrativi mancanti di B, abilita l'amministratore demo a entrambe le gestioni e crea `socio@sodanoconsulting.it` soltanto per B. Non svuota A e non reimposta password esistenti. Se l'email è già utilizzata da un account estraneo allo scenario, l'operazione si interrompe invece di assegnare accessi automaticamente.

Se era stato creato il socio con la precedente email dimostrativa, aggiornare i file non lo rinomina: il nuovo account viene creato al successivo avvio del seeder e quello precedente resta invariato.

Non usare `demo:reset`, `migrate:fresh` o il seeder generale per aggiungere queste funzionalità. `DatabaseSeeder` è destinato a una nuova installazione e rifiuta un database con utenti o parcheggi già presenti.

## Anagrafica clienti e storico

La sezione **Clienti** raccoglie privati e aziende, recapiti, dati di fatturazione facoltativi, note e più targhe. Le schede sono ricercabili per nome, email, telefono o targa e filtrabili per tipo e stato.

Prenotazioni, abbonamenti e ingressi giornalieri possono richiamare un cliente esistente. Gli accessi degli abbonati ereditano il cliente del contratto. Lo storico comprende prenotazioni, abbonamenti, soste, pagamenti e fatture collegati, con filtri per tipo e periodo, rispettando la gestione e il parcheggio corrente.

Per popolare l'anagrafica dalle operazioni già presenti, dopo le migrazioni:

```powershell
php artisan db:seed --class=CustomerSeeder --force
```

Specificare sempre la classe dedicata. Il recupero opera nelle rispettive gestioni, elabora solo le operazioni non collegate, non cancella dati e non riscrive recapiti, note o informazioni delle pratiche esistenti. Le targhe vengono aggiunte senza sostituire quelle già associate, fino al limite di 30 per scheda.

Il riconoscimento richiede il nome insieme a email, telefono o targa normalizzati. Nomi uguali da soli non vengono accorpati. Corrispondenze ambigue, schede archiviate, dati mancanti o non validi sono segnalati per verifica. Per i casi residui usare **Collega operazioni esistenti** dalla scheda cliente; collegando un abbonamento si collegano anche le sue soste.

Archiviazione e riattivazione sono riservate agli amministratori. L'archiviazione conserva lo storico ma esclude il cliente dai nuovi inserimenti. Non occorre rigenerare una demo esistente per popolare l'anagrafica.

## Lingue e responsive

I cataloghi sono `lang/it.json`, `lang/en_GB.json` e `lang/nl.json`. L'italiano è la lingua iniziale; l'inglese usa la variante britannica `en-GB`. La scelta dal menu con bandiera e sigla viene conservata nella sessione.

Anche selettore del parcheggio, utenti, ruoli, registro attività e credenziali dimostrative del login seguono la lingua scelta. Nomi, note e altri contenuti inseriti dal gestore non vengono tradotti automaticamente. Menu, moduli e tabelle prevedono adattamenti per desktop, tablet e smartphone.

## Rigenerazione completa dello scenario

Solo quando si vuole deliberatamente eliminare tutte le modifiche della demo e ripartire dallo scenario iniziale:

```powershell
php artisan demo:reset
```

Il comando ricrea **tutto il database configurato**, comprese entrambe le gestioni, gli account e le operazioni. Non è un aggiornamento incrementale e non costituisce un backup.

La seconda risposta simulata modifica alcune prenotazioni, ne annulla altre e ne aggiunge di nuove. Per importare soltanto la prima risposta:

```powershell
php artisan demo:reset --single-sync
```

Le date sono relative alla preparazione dello scenario; i dati non si spostano automaticamente in avanti soltanto aprendo il gestionale.

## Verifiche

```powershell
php vendor/bin/pest
npm run build
```

La configurazione dei test PHP forza un database SQLite in memoria e servizi di test. Le prove comprendono isolamento fra gestioni, migrazioni, utenti, dati demo, lingue e flussi applicativi.

Sono disponibili due controlli browser separati, che richiedono Playwright e Chrome:

- `tests/Browser/audit-demo-management.cjs`: navigazione e flussi della doppia gestione con dati di prova dedicati.
- `tests/Browser/audit-demo-login.cjs`: login con e senza modalità demo, lingue, visibilità password e larghezze da 320 a 1440 pixel. Usa gli asset compilati senza modificare il server di sviluppo.

`PLAYWRIGHT_MODULE` e `BROWSER_EXECUTABLE` permettono di indicare le installazioni disponibili. I risultati restano in `storage/framework/testing`, esclusa da Git. I test non sostituiscono un backup o una verifica del rilascio sul database effettivo del server.
