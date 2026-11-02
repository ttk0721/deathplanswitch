<?php
declare(strict_types=1);

// Check-in, confirm, respond, history, public page and the vault pages.
// Keys are the English source strings. Pages for family avoid gendered forms.
return [
    // Check-in (owner)
    'Check in: Death Plan Switch' => 'Conferma la presenza: Death Plan Switch',
    'Checked in' => 'Presenza confermata',
    'Your switch had fired. Everyone who was contacted has now been told it was a false alarm: {names}.' => 'Il tuo interruttore era scattato. Tutte le persone contattate sono state ora informate che era un falso allarme: {names}.',
    'Your switch had fired. Everyone who was contacted has now been told it was a false alarm.' => 'Il tuo interruttore era scattato. Tutte le persone contattate sono state ora informate che era un falso allarme.',
    'Your contacts have read their messages, so any pointer in them has been seen. {link} to review what needs changing.' => 'I tuoi contatti hanno letto i loro messaggi, quindi ogni indicazione contenuta è stata vista. {link} per rivedere cosa va cambiato.',
    'Sign in' => 'Accedi',
    '{names} had been asked whether you are all right, and has now been told it was a false alarm. Nobody else was contacted.' => 'Avevamo chiesto a {names} se stai bene, e ora è stato informato che era un falso allarme. Nessun altro è stato contattato.',
    '{names} had been asked whether you are all right, and have now been told it was a false alarm. Nobody else was contacted.' => 'Avevamo chiesto a {names} se stai bene, e ora sono stati informati che era un falso allarme. Nessun altro è stato contattato.',
    'Thank you. Nobody was contacted.' => 'Grazie. Nessuno è stato contattato.',
    'Your switch is paused until {date}.' => 'Il tuo interruttore è in pausa fino a {date}.',
    'Your next check-in email arrives on {date} at {time}.' => 'La tua prossima email di conferma arriverà {date} alle {time}.',
    'Are you there, {name}?' => 'Ci sei, {name}?',
    'owner' => 'titolare',
    'Press the button to check in. Opening this page is not enough.' => 'Premi il pulsante per confermare la tua presenza. Aprire questa pagina non basta.',
    'I am here: check in' => 'Ci sono: conferma',
    'This link is no longer active' => 'Questo link non è più attivo',
    'Each check-in link works once, and for 14 days. If you have a newer email from Death Plan Switch, use the link in that one.' => 'Ogni link di conferma funziona una sola volta e per 14 giorni. Se hai un’email più recente da Death Plan Switch, usa il link di quella.',
    'You can also {link} and check in from the dashboard.' => 'Puoi anche {link} e confermare la presenza dal pannello.',
    'sign in' => 'accedere',

    // Confirm (contact)
    'Death Plan Switch' => 'Death Plan Switch',
    'Confirmed' => 'Confermato',
    'Thank you, {name}. {owner} has been told. There is nothing else to do.' => 'Grazie, {name}. {owner} è stato informato. Non c’è nient’altro da fare.',
    'If {owner} ever stops checking in, you will hear from the same email address that sent you this request. Add it to your contacts so it is not mistaken for spam.' => 'Se {owner} smettesse di confermare la presenza, riceverai un messaggio dallo stesso indirizzo email che ti ha inviato questa richiesta. Aggiungilo ai tuoi contatti perché non venga scambiato per spam.',
    'Done' => 'Fatto',
    "You will not be contacted by {owner}'s Death Plan Switch. {owner} has been told." => 'Il Death Plan Switch di {owner} non ti contatterà. {owner} è stato informato.',
    "Still happy to be {owner}'s {role}?" => 'Conferma la sua disponibilità nel ruolo di {role} per {owner}?',
    '{owner} has listed you as their {role}' => '{owner} ha scelto lei nel ruolo di {role}',
    '{owner} uses Death Plan Switch, a small app on their own server. Every week it asks {owner} to check in. If they stop, it emails the people they have chosen.' => '{owner} usa Death Plan Switch, una piccola applicazione sul proprio server. Ogni settimana chiede a {owner} di confermare la presenza. Se smette di farlo, scrive alle persone che ha scelto.',
    'As next of kin you would be asked first. If {owner} misses a check-in and three days of reminders, you would get one email asking whether everything is all right, with two buttons to answer.' => 'Come persona più vicina verresti interpellato per primo. Se {owner} non conferma la presenza e passano tre giorni di promemoria, riceveresti un’email che chiede se va tutto bene, con due pulsanti per rispondere.',
    'If {owner} stops checking in and their next of kin cannot confirm they are well, you would get one email telling you what {owner} needs you to do.' => 'Se {owner} smette di confermare la presenza e la sua persona più vicina non può confermare che sta bene, riceveresti un’email con ciò che {owner} ti chiede di fare.',
    'Confirming tells {owner} that {address} reaches you, and that you are willing.' => 'Confermando, {owner} saprà che {address} ti raggiunge e che sei d’accordo.',
    'Yes, I confirm' => 'Sì, confermo',
    'No, leave me out' => 'No, preferisco restarne fuori',
    'It has either been used already or has expired. If you meant to confirm, ask the person who listed you to send a new request.' => 'È già stato usato oppure è scaduto. Se volevi confermare, chiedi alla persona che ti ha indicato di inviare una nuova richiesta.',

    // Respond (next of kin)
    'All fine' => 'Va tutto bene',
    'You have seen or spoken to {owner} and they are all right. Nobody else is contacted, and {owner} is told you answered.' => 'Hai visto o sentito {owner} e sta bene. Nessun altro verrà contattato e {owner} sarà informato che hai risposto.',
    "Something's wrong" => 'Qualcosa non va',
    '{owner} cannot run their business at the moment.' => '{owner} al momento non può occuparsi delle proprie faccende.',
    'These people are sent their instructions straight away: {names}.' => 'Queste persone riceveranno subito le loro istruzioni: {names}.',
    'The people they chose are sent their instructions straight away.' => 'Le persone che ha scelto riceveranno subito le loro istruzioni.',
    'You are sent yours too.' => 'Riceverai anche le tue.',
    'Thank you' => 'Grazie',
    'Your answer has been recorded. Nobody else has been contacted, and {owner} has been told that you answered.' => 'La tua risposta è stata registrata. Nessun altro è stato contattato e {owner} è stato informato che hai risposto.',
    'Your answer has been recorded.' => 'La tua risposta è stata registrata.',
    'These people have been sent their instructions: {names}.' => 'Queste persone hanno ricevuto le loro istruzioni: {names}.',
    'The people {owner} chose have been sent their instructions.' => 'Le persone scelte da {owner} hanno ricevuto le loro istruzioni.',
    'You have been emailed a message from {owner} as well, with what to do next.' => 'Ti abbiamo inviato anche un messaggio da parte di {owner}, con ciò che c’è da fare ora.',
    'Is {owner} all right?' => '{owner} sta bene?',
    '{owner} has not answered their weekly check-in or its reminders. You are their next of kin, so you are asked first. Nothing has been sent to anyone else.' => '{owner} non ha risposto alla conferma settimanale né ai promemoria. Sei la sua persona più vicina, quindi lo chiediamo prima a te. Non è stato inviato nulla a nessun altro.',
    'Not sure yet? You can come back to this page. If neither you nor {owner} answers by the time given in the email, the others are sent their instructions.' => 'Non sei ancora sicuro? Puoi tornare su questa pagina. Se né tu né {owner} rispondete entro il termine indicato nell’email, le altre persone riceveranno le loro istruzioni.',
    'It has either been used already, or the check it belonged to has been settled. You do not need to do anything.' => 'È già stato usato, oppure la verifica a cui apparteneva è stata chiusa. Non devi fare nulla.',

    // History
    'History: Death Plan Switch' => 'Cronologia: Death Plan Switch',
    'History' => 'Cronologia',
    'Everything the switch has done, newest first. Scheduler runs that found nothing to do are not listed.' => 'Tutto ciò che ha fatto l’interruttore, dal più recente. Le esecuzioni del pianificatore che non avevano nulla da fare non sono elencate.',
    'Nothing has happened yet.' => 'Non è ancora successo nulla.',

    // Public page
    'This switch is active.' => 'Questo interruttore è attivo.',
    'This switch is not active.' => 'Questo interruttore non è attivo.',
    'This switch is armed.' => 'L’interruttore veglia.',
    'Triggered' => 'Scattato',
    'This switch has been triggered.' => 'Questo interruttore è scattato.',
    'Open the vault' => 'Apri la cassaforte',
    'Armed' => 'Inserito',
    'Not active' => 'Non attivo',
    'Owner sign-in' => 'Accesso del titolare',

    // Vault (owner and recipient)
    'That recipient has no contact with an email address, so no invitation was sent.' => 'Questo destinatario non ha un contatto con un indirizzo email, quindi non è stato inviato alcun invito.',
    'Invitation sent to {name}.' => 'Invito inviato a {name}.',
    'The invitation could not be sent. Check the mail settings.' => 'Non è stato possibile inviare l’invito. Controlla le impostazioni di posta.',
    'Vault: Death Plan Switch' => 'Cassaforte: Death Plan Switch',
    'Vault' => 'Cassaforte',
    "What you leave for the people you choose, locked in your browser. Only a recipient's code or password opens it, and only after the switch has fired." => 'Ciò che lasci alle persone che scegli, chiuso nel tuo browser. Lo apre solo il codice o la password di un destinatario, e solo dopo che l’interruttore è scattato.',
    'The vault needs JavaScript. Turn it on for this page.' => 'La cassaforte richiede JavaScript. Attivalo per questa pagina.',
    'This page needs JavaScript, because everything is decrypted in your browser.' => 'Questa pagina richiede JavaScript, perché tutto viene decifrato nel tuo browser.',

    // Public landing page and background images
    'This switch is being set up.' => 'Questo interruttore è in fase di configurazione.',
    'All is well.' => 'Va tutto bene.',
    'Set up' => 'Configura',
    'Dawn' => 'Alba',
    'Sea' => 'Mare',
    'Forest' => 'Bosco',
    'Paper' => 'Carta',
    'Plain' => 'Tinta unita',
    'That file is empty.' => 'Questo file è vuoto.',
    'The image can be up to 3 MB.' => 'L’immagine può pesare fino a 3 MB.',
    'Use a JPEG, PNG or WebP image.' => 'Usa un’immagine JPEG, PNG o WebP.',
    'That file is not a valid image.' => 'Questo file non è un’immagine valida.',
    'The image is too large in pixels. Use one up to {n} pixels on each side.' => 'L’immagine ha troppi pixel. Usane una fino a {n} pixel per lato.',

    // Respond (trusted people)
    'Nobody is sent anything early. If {owner} does not check in and nobody answers "All fine" by the time given in the email, the people {owner} chose are sent their instructions.' => 'Non viene inviato nulla in anticipo. Se {owner} non conferma la presenza e nessuno risponde «Va tutto bene» entro il termine indicato nell’email, le persone scelte da {owner} riceveranno le loro istruzioni.',
    'Your answer has been recorded. Nobody else will be asked. The others who were asked are told it was a false alarm, and {owner} has been told that you answered.' => 'La sua risposta è stata registrata. Non verrà chiesto a nessun altro. Alle altre persone già interpellate viene comunicato che era un falso allarme e {owner} è stato informato che ha risposto.',
    'The other trusted people are still asked in turn, and nobody is sent anything early.' => 'Le altre persone di fiducia continuano a essere interpellate una dopo l’altra e non viene inviato nulla in anticipo.',
    '{owner} has not answered their weekly check-in or its reminders. You are one of the people they trust, so you are asked whether everything is all right. Nothing has been sent to anyone else.' => '{owner} non ha risposto alla conferma settimanale né ai promemoria. Lei è una delle persone di cui si fida, quindi chiediamo a lei se va tutto bene. Non è stato inviato nulla a nessun altro.',
];
