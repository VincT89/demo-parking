# Doppia gestione nella demo

Integrazione sul branch main_vincenzo della logica di main_doppio, adattata alla demo Sodano Consulting.

## Accessi e parcheggio selezionato

- Ogni gestione ha i propri utenti e ruoli. Può comprendere più parcheggi.
- Il cliente abilitato ad A e B può cambiare parcheggio dal selettore in alto.
- Il socio assegnato soltanto alla gestione B non può aprire i dati di A, neppure tramite un collegamento diretto.
- In Parcheggi, la scelta “Crea una gestione separata” rende il creatore il primo amministratore. Gli altri accessi si assegnano da Utenti.
- Senza questa scelta, il nuovo parcheggio appartiene alla gestione corrente e ne condivide gli utenti.
- Un account esistente può essere riutilizzato solo da un amministratore che ne gestisce già l'accesso. Le password vuote mantengono le credenziali esistenti.
- La revoca riguarda solo la gestione selezionata. Non è possibile rimuovere l'ultimo amministratore.
- Una scheda rimasta aperta sul vecchio parcheggio non può salvare dopo un cambio di parcheggio: deve essere ricaricata.

## Dati e funzioni

Prenotazioni, disponibilità, calendario, esportazioni, incassi, fatture, stampa, garage e navette seguono il parcheggio selezionato. Le anagrafiche clienti appartengono alla gestione; lo storico mostra le operazioni del parcheggio corrente. Clienti con la stessa email in gestioni diverse rimangono distinti.

Il registro attività mostra chi ha eseguito le operazioni, senza memorizzare password o credenziali nei dettagli. La pagina pubblica FO utilizza il collegamento del parcheggio selezionato.

Le nuove schermate, i ruoli, i messaggi di errore e le conferme sono disponibili nei cataloghi JSON italiano, inglese e olandese. Nomi di persone, gestioni e parcheggi sono dati inseriti dall'utente: non vengono tradotti automaticamente.

La modalità demo conserva le simulazioni delle piattaforme, dei pagamenti e della fatturazione. Non è necessario inserire credenziali reali.

## Attivazione sull'installazione esistente

Il database operativo non è stato modificato durante l'integrazione. Le prove usano database separati. Non sono stati eseguiti commit o push.

Prima dell'attivazione occorre fare un backup del database e applicare le nuove migrazioni dall'installazione demo corretta:

    php artisan migrate --force
    npm run build

Se l'installazione usa cache di configurazione o rotte, ricostruirle nel normale processo di rilascio. Non usare migrate:fresh, demo:reset o il seeder generale sull'installazione esistente: non servono per l'aggiornamento.

Le migrazioni mantengono gli ID e i dati esistenti, associandoli alla gestione iniziale e conservando i ruoli degli account. Non dividono automaticamente vecchi parcheggi già presenti: per avere isolamento tra A e B occorrono due gestioni distinte.

Per aggiungere lo scenario sintetico B, solo con DEMO_MODE=true e con l'account demo principale già presente, è disponibile il seeder aggiuntivo:

    php artisan db:seed --class=DemoManagementSeeder --force

Questo scenario abilita demo@sodanoconsulting.it ad A e B e crea socio@sodanoconsulting.it solo per B. La password del nuovo socio dimostrativo è password. Non cambia la password del cliente demo esistente. Aggiunge dati sintetici di B senza svuotare A; ripeterlo nella stessa giornata non duplica gli stessi record.

Il login mostra entrambi gli accessi, A+B e solo B, esclusivamente quando DEMO_MODE è attivo, in italiano, inglese e olandese.

Se il socio era già stato creato con il precedente indirizzo dimostrativo, aggiornare i file non ne cambia automaticamente l’email. Rilanciando il solo DemoManagementSeeder viene creato il nuovo account socio@sodanoconsulting.it per B; il precedente account resta invariato e non viene eliminato. Nessuna password di account già presenti viene reimpostata.

Queste credenziali e i dati sintetici sono destinati esclusivamente alla demo, non ad account di produzione.

## Verifiche ripetibili

La suite PHP forza un database SQLite in memoria e cache/sessioni di test:

    php vendor/bin/pest

Il controllo browser è in tests/Browser/audit-demo-management.cjs. Richiede Playwright e Chrome; PLAYWRIGHT_MODULE e BROWSER_EXECUTABLE permettono di indicarli senza installare altri pacchetti. Usa un profilo Chrome invisibile separato e un database SQLite dedicato sotto storage/framework/testing. Non controlla finestre aperte dall'utente e blocca richieste verso servizi esterni.

Il controllo attraversa 21 schermate in tre lingue a 360, 768 e 1440 pixel, prova i ruoli, il cambio parcheggio, i moduli utente, il menu mobile, la visibilità delle password e il recupero dagli errori. I risultati e le immagini restano nella cartella di test, esclusa da Git. AUDIT_PAGES consente di ripetere soltanto le pagine indicate.

La prova di aggiornamento verifica anche la conservazione di ID, veicoli collegati, prenotazioni e pagamenti già presenti. Su SQLite le nuove migrazioni proteggono le relazioni durante la ricostruzione delle tabelle e rifiutano esecuzioni dentro una transazione già aperta.

I test non sostituiscono un backup né una prova di rilascio sullo specifico database del server. Nessuna integrazione reale di pagamento o fatturazione è stata contattata per questa verifica.
