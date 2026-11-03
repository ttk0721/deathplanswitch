<?php
/** Italian wording for the core classes: engine warnings and checklist, web errors, setup checks, contact labels. */
declare(strict_types=1);

return [
    // Contact labels
    'next of kin' => 'persona più vicina',
    'legal contact' => 'contatto legale',
    'contact' => 'contatto',
    'Next of kin' => 'Persona più vicina',
    'Legal contact' => 'Contatto legale',
    'Other contact' => 'Altro contatto',
    'Contact' => 'Contatto',
    '(none listed)' => '(nessuno in elenco)',

    // Web
    'The data folder needs attention' => 'La cartella dei dati richiede attenzione',
    'Nothing has been changed. See "If a data file is damaged" in the README.' => 'Non è stato modificato nulla. Vedi «If a data file is damaged» nel README.',
    'Too many wrong passwords. Try again {when}.' => 'Troppe password errate. Riprova {when}.',
    'That password is not right.' => 'Questa password non è corretta.',
    'That form had expired. Go back, reload the page and try again.' => 'Questo modulo era scaduto. Torna indietro, ricarica la pagina e riprova.',

    // Setup
    'PHP {version} or newer' => 'PHP {version} o successivo',
    'This server runs PHP {version}. Ask your host to switch to a current version.' => 'Questo server usa PHP {version}. Chiedi al tuo hosting di passare a una versione aggiornata.',
    'The OpenSSL extension, for encrypted mail' => 'L’estensione OpenSSL, per la posta cifrata',
    'Ask your host to enable the PHP openssl extension.' => 'Chiedi al tuo hosting di attivare l’estensione openssl di PHP.',
    'The mbstring extension' => 'L’estensione mbstring',
    'Ask your host to enable the PHP mbstring extension.' => 'Chiedi al tuo hosting di attivare l’estensione mbstring di PHP.',
    'The data folder can be written to' => 'La cartella dei dati è scrivibile',
    'PHP cannot write to {dir}. Give that folder to the user the web pages run as' => 'PHP non può scrivere in {dir}. Assegna quella cartella all’utente con cui girano le pagine web',
    'Choose a password of at least {min} characters.' => 'Scegli una password di almeno {min} caratteri.',
    'That password is too long.' => 'Questa password è troppo lunga.',
    'Enter the mail server host, for example smtp.example.com.' => 'Inserisci il server di posta, per esempio smtp.example.com.',
    'Enter the mail server port. 587 is the usual one.' => 'Inserisci la porta del server di posta. Di solito è la 587.',
    'Enter the address the switch sends from. Your mail service must allow it.' => 'Inserisci l’indirizzo da cui invia l’interruttore. Il tuo servizio di posta deve consentirlo.',
    'There is no code waiting. Send a new one.' => 'Non c’è nessun codice in attesa. Inviane uno nuovo.',
    'That code has expired. Send a new one.' => 'Questo codice è scaduto. Inviane uno nuovo.',
    'That code is not right. Check the email and try again.' => 'Questo codice non è corretto. Controlla l’email e riprova.',

    // Engine: actions
    'Open the vault' => 'Apri la cassaforte',
    'You disarmed it from the dashboard.' => 'L’hai disinserito dal pannello.',
    'Their previous address is still in use.' => 'Resta in uso il loro indirizzo precedente.',
    '{name} declined to be your {role}, so a required contact is missing.' => '{name} ha rifiutato il ruolo di {role}, quindi manca un contatto necessario.',
    'Your switch has been disarmed, because it needs this role filled.' => 'Il tuo interruttore è stato disinserito, perché questo ruolo deve essere coperto.',

    // Engine: checklist
    'The scheduler is running' => 'Il pianificatore è in funzione',
    'No run has been recorded yet. Add the crontab line shown in Settings.' => 'Non è stata ancora registrata nessuna esecuzione. Aggiungi la riga del crontab mostrata nelle Impostazioni.',
    'The last run was {when}. Check the crontab line shown in Settings.' => 'L’ultima esecuzione è stata {when}. Controlla la riga del crontab mostrata nelle Impostazioni.',
    'The data folder cannot be read from the web' => 'La cartella dei dati non è leggibile dal web',
    'Files in the data folder can be opened in a browser. See "Protecting the data folder" in the README.' => 'I file nella cartella dei dati possono essere aperti da un browser. Vedi «Protecting the data folder» nel README.',
    'The server could not test this by itself. Run the check from Settings.' => 'Il server non è riuscito a verificarlo da solo. Esegui la verifica dalle Impostazioni.',
    'Your next of kin has confirmed' => 'La tua persona più vicina ha confermato',
    'Add your next of kin.' => 'Aggiungi la tua persona più vicina.',
    'Every message says where things are' => 'Ogni messaggio dice dove si trovano le cose',
    'Still holding starter text in square brackets: {names}.' => 'Contengono ancora il testo di esempio tra parentesi quadre: {names}.',
    'Checked once your contacts have confirmed.' => 'Verrà controllato quando i tuoi contatti avranno confermato.',
    'Each contact has been given what they need' => 'Ogni contatto ha ricevuto ciò che gli serve',
    'Not ticked yet: {names}.' => 'Non ancora spuntato: {names}.',
    'A second email address for reminders' => 'Un secondo indirizzo email per i promemoria',
    'Recommended: an address with a different provider, in case your main one fails. Add it in Settings.' => 'Consigliato: un indirizzo presso un altro provider, nel caso quello principale non funzioni. Aggiungilo nelle Impostazioni.',
    '{name} declined. Choose someone else.' => '{name} ha rifiutato. Scegli un’altra persona.',
    'Send {name} their confirmation email.' => 'Invia a {name} l’email di conferma.',
    'Waiting for {name} to confirm. The email went {when}.' => 'In attesa che {name} confermi. L’email è stata inviata {when}.',

    // Engine: warnings
    '{name}.json was damaged and the last good copy is in use. Check what changed recently, then save any setting to rewrite the file.' => '{name}.json era danneggiato ed è in uso l’ultima copia valida. Controlla cosa è cambiato di recente, poi salva una qualsiasi impostazione per riscrivere il file.',
    'The scheduler has never run. Nothing will be sent until it does.' => 'Il pianificatore non è mai stato eseguito. Non verrà inviato nulla finché non lo sarà.',
    'The scheduler last ran {when}. While it is stopped, the switch cannot send anything.' => 'Il pianificatore è stato eseguito l’ultima volta {when}. Finché è fermo, l’interruttore non può inviare nulla.',
    'This node has not heard from any other node for {duration}. Serious steps are delayed while it is cut off.' => 'Questo nodo non riceve notizie da nessun altro nodo da {duration}. I passaggi importanti sono rinviati finché resta isolato.',
    'This node has not heard from any other node yet. Serious steps are delayed while it is cut off.' => 'Questo nodo non ha ancora ricevuto notizie da nessun altro nodo. I passaggi importanti sono rinviati finché resta isolato.',
    'The last email could not be sent ({when}): {error}' => 'Non è stato possibile inviare l’ultima email ({when}): {error}',
    'notification' => 'notifica',
    'unknown error' => 'errore sconosciuto',
    'The {label} channel failed {when}: {error}' => 'Il canale «{label}» ha avuto un errore {when}: {error}',
    'Files in the data folder can be opened in a browser.' => 'I file nella cartella dei dati possono essere aperti da un browser.',
    'The server could not check whether the data folder is blocked from the web.' => 'Il server non è riuscito a verificare se la cartella dei dati è bloccata per il web.',
    'A required contact is missing or unconfirmed.' => 'Manca un contatto necessario oppure non ha confermato.',
    '{name} declined and will not be contacted.' => '{name} ha rifiutato e non sarà contattato.',
    '{name} has not confirmed, so will not be contacted.' => '{name} non ha confermato, quindi non sarà contattato.',
    '{name} has not confirmed their new address. The old one is still in use.' => '{name} non ha confermato il nuovo indirizzo. Resta in uso quello vecchio.',
    '{name} has not answered their yearly reconfirmation. Check their address still works.' => '{name} non ha risposto alla riconferma annuale. Verifica che il suo indirizzo funzioni ancora.',
    'The message for {name} still holds starter text in square brackets.' => 'Il messaggio per {name} contiene ancora il testo di esempio tra parentesi quadre.',
    'Your yearly review is due: confirm each contact still has what they need.' => 'È il momento della revisione annuale: conferma che ogni contatto ha ancora ciò che gli serve.',
    'This install is not using HTTPS. Links in its emails can be read in transit.' => 'Questa installazione non usa HTTPS. I link nelle sue email possono essere letti durante il percorso.',

    // Engine: message previews
    'When you miss a check-in and its reminders' => 'Quando non confermi la presenza né rispondi ai promemoria',
    'When the switch fires' => 'Quando l’interruttore scatta',

    // Technical helper
    'technical helper' => 'referente tecnico',
    'Technical helper' => 'Referente tecnico',
    'Technical helper (optional)' => 'Referente tecnico (facoltativo)',
    'Someone who can keep your servers, accounts or projects running.' => 'Qualcuno che possa mantenere in funzione i tuoi server, account o progetti.',

    // not told in advance, group channels
    '{name} is set not to be told in advance, and trusted people have to know. Turn that option off first.' => '{name} ha l’opzione «Senza preavviso», e le persone di fiducia devono sapere. Disattiva prima quell’opzione.',
    'Your next of kin is asked first whether you are all right, so they have to know about the switch in advance.' => 'Alla tua persona più vicina viene chiesto per prima se stai bene, quindi deve conoscere l’interruttore in anticipo.',
    'Trusted people are asked whether you are all right, so they have to know about the switch in advance. Remove them from trusted people first.' => 'Alle persone di fiducia viene chiesto se stai bene, quindi devono conoscere l’interruttore in anticipo. Rimuovi prima questa persona dalle persone di fiducia.',
    'They hold vault packages, and opening those needs a code given to them in advance. Remove them from the vault first.' => 'Questa persona ha pacchetti nella cassaforte e per aprirli serve un codice consegnato in anticipo. Rimuovila prima dalla cassaforte.',
    '{name} is set not to be told in advance, so they cannot hold vault packages. Turn that option off on their contact page first.' => '{name} ha l’opzione «Senza preavviso», quindi non può avere pacchetti nella cassaforte. Disattiva prima quell’opzione nella pagina di questo contatto.',
    'Enter a name for the group channel.' => 'Inserisci un nome per il canale di gruppo.',
    'The name can be at most {max} characters.' => 'Il nome può contenere al massimo {max} caratteri.',
    'Write the message the group channel is sent.' => 'Scrivi il messaggio che riceverà il canale di gruppo.',
    'The message can be at most {max} characters.' => 'Il messaggio può contenere al massimo {max} caratteri.',
    'A test was sent less than a minute ago. Try again in a minute.' => 'Una prova è stata inviata meno di un minuto fa. Riprova tra un minuto.',
    'This is a test message. Nothing has happened and nothing needs to be done.' => 'Questo è un messaggio di prova. Non è successo nulla e non c’è nulla da fare.',
    'You can have at most {max} group channels.' => 'Puoi avere al massimo {max} canali di gruppo.',
    'Please disregard the earlier message from {owner}: it was sent by mistake, and {owner} is fine.' => 'Si prega di ignorare il messaggio precedente di {owner}: è stato inviato per errore e {owner} sta bene.',
    'A group channel message cannot use {refused}: everyone in the channel would see it. Only these fill themselves in here: {allowed}.' => 'Un messaggio per un canale di gruppo non può usare {refused}: lo vedrebbe tutto il canale. Qui si compilano da sole solo queste parole: {allowed}.',
];
