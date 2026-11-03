<?php
/** Italian wording for the history log: entries written from now on, and the words that fill messages in. */
declare(strict_types=1);

return [
    // Check-in methods (also stored as the way the last check-in came in)
    'the dashboard' => 'il pannello',
    'the emailed link' => 'il link nell’email',
    'another node' => 'un altro nodo',
    'arming the switch' => 'l’inserimento dell’interruttore',
    'pausing the switch' => 'la messa in pausa dell’interruttore',
    'ending the pause' => 'la fine della pausa',

    // Cycle
    'Weekly check-in email sent.' => 'Email settimanale di conferma della presenza inviata.',
    'Reminder {number} sent to the owner.' => 'Promemoria n. {number} inviato al titolare.',
    'No check-in after 72 hours. The next of kin has been asked.' => 'Nessuna conferma della presenza dopo 72 ore. È stata interpellata la persona più vicina.',
    'No check-in after 72 hours. The next of kin could not be emailed; the 72-hour wait has started anyway.' => 'Nessuna conferma della presenza dopo 72 ore. Non è stato possibile inviare l’email alla persona più vicina; l’attesa di 72 ore è comunque iniziata.',
    'The next of kin was reminded.' => 'È stato inviato un promemoria alla persona più vicina.',
    'The next of kin answered "Something\'s wrong". The switch has fired.' => 'La persona più vicina ha risposto «Qualcosa non va». L’interruttore è scattato.',
    'Neither the owner nor the next of kin confirmed all is well. The switch has fired.' => 'Né il titolare né la persona più vicina hanno confermato che va tutto bene. L’interruttore è scattato.',
    '{role} ({name}) was sent their message.' => 'Messaggio inviato a: {role} ({name}).',
    '{name} was told it was a false alarm.' => '{name} è stato informato che era un falso allarme.',
    'The next of kin answered "All fine". Nobody else was contacted.' => 'La persona più vicina ha risposto «Va tutto bene». Nessun altro è stato contattato.',
    'The next of kin answered "All fine" ({via}). Nobody else was contacted.' => 'La persona più vicina ha risposto «Va tutto bene» ({via}). Nessun altro è stato contattato.',
    'The weekly check-in email was sent by another node.' => 'L’email settimanale di conferma è stata inviata da un altro nodo.',
    'Another node fired the switch.' => 'Un altro nodo ha fatto scattare l’interruttore.',
    'Checked in by {via}.' => 'Presenza confermata tramite: {via}.',
    'Checked in by another node ({via}).' => 'Presenza confermata su un altro nodo ({via}).',
    'False-alarm notices sent to {names}.' => 'Avvisi di falso allarme inviati a: {names}.',

    // Switch
    'The pause ended. The switch is active again.' => 'La pausa è terminata. L’interruttore è di nuovo attivo.',
    'The switch was armed.' => 'L’interruttore è stato inserito.',
    'The switch was disarmed. {reason}' => 'L’interruttore è stato disinserito. {reason}',
    'The switch was paused for {duration}.' => 'L’interruttore è stato messo in pausa per {duration}.',
    'The pause was ended early.' => 'La pausa è stata terminata in anticipo.',
    'Yearly review completed.' => 'Revisione annuale completata.',
    'The data folder can be read from the web.' => 'La cartella dei dati è leggibile dal web.',

    // Contacts
    '{name} confirmed as {role}.' => '{name} ha confermato il ruolo di {role}.',
    'The new address for {name} was declined. The old one is still in use.' => 'Il nuovo indirizzo di {name} è stato rifiutato. Resta in uso quello vecchio.',
    '{name} declined to be {role}.' => '{name} ha rifiutato il ruolo di {role}.',
    '{name} ({role}) was deleted.' => '{name} ({role}) è stato eliminato.',
    '{name} was added as {role}.' => '{name} è stato aggiunto come {role}.',

    // Sending
    'Notified {where}' => 'Notificato: {where}',
    'Could not notify {where}.' => 'Non è stato possibile notificare: {where}.',
    'Sent "{subject}" to {to}' => 'Inviato «{subject}» a: {to}',
    'Could not send "{subject}" to {to}' => 'Non è stato possibile inviare «{subject}» a: {to}',

    // Settings and sign-in
    'Backup email address confirmed: {address}.' => 'Indirizzo email di riserva confermato: {address}.',
    'Main email address confirmed: {address}.' => 'Indirizzo email principale confermato: {address}.',
    'A wrong password was entered at sign-in.' => 'È stata inserita una password errata durante l’accesso.',

    // Message placeholders (contact page)
    'The name of the person receiving the message' => 'Il nome della persona che riceve il messaggio',
    'Whole days since your last check-in' => 'Giorni interi dalla tua ultima conferma della presenza',
    'The date of your last check-in' => 'La data della tua ultima conferma della presenza',
    'Next of kin: name, email, phone' => 'Persona più vicina: nome, email, telefono',
    'Technical contact: name, email, phone' => 'Contatto tecnico: nome, email, telefono',
    'Everyone else on your list, one per line' => 'Tutti gli altri nel tuo elenco, uno per riga',

    // Settings, cluster, install, storage
    'The weekly check-in time was changed to {day} at {time} ({tz}).' => 'L’orario della conferma settimanale è stato cambiato in: {day}, {time} ({tz}).',
    'Mail settings were changed.' => 'Le impostazioni di posta sono state modificate.',
    'The owner password was changed.' => 'La password del titolare è stata cambiata.',
    'A node was added to the cluster.' => 'Un nodo è stato aggiunto al cluster.',
    'A node public address was changed.' => 'L’indirizzo pubblico di un nodo è stato modificato.',
    'A node was removed from the cluster.' => 'Un nodo è stato rimosso dal cluster.',
    'The cluster settings were changed.' => 'Le impostazioni del cluster sono state modificate.',
    'Death Plan Switch {version} was installed.' => 'Death Plan Switch {version} è stato installato.',
    'Vault API error: {detail}' => 'Errore dell’API della cassaforte: {detail}',
    '{name}.json was unreadable. The last good copy was used instead.' => 'Non è stato possibile leggere {name}.json. È stata usata al suo posto l’ultima copia valida.',

    // Trusted people
    'another node ({via})' => 'un altro nodo ({via})',
    '{name} was asked whether {owner} is all right.' => 'È stato chiesto a {name} se {owner} sta bene.',
    '{name} could not be emailed.' => 'Non è stato possibile inviare l’email a {name}.',
    '{name} answered "Something\'s wrong". Nothing is sent early; the others are still asked in turn.' => '{name} ha risposto «Qualcosa non va». Non viene inviato nulla in anticipo e le altre persone continuano a essere interpellate una dopo l’altra.',
    '{name} answered "All fine". Nobody else will be asked.' => '{name} ha risposto «Va tutto bene». Non verrà chiesto a nessun altro.',
    'The settings for the trusted people were changed.' => 'Le impostazioni delle persone di fiducia sono state modificate.',

    // group channels
    'Could not send the message to the group channel {name}.' => 'Non è stato possibile inviare il messaggio al canale di gruppo {name}.',
    'Could not send part {part} of {parts} to the group channel {name}.' => 'Non è stato possibile inviare la parte {part} di {parts} al canale di gruppo {name}.',
    'No more attempts will be made.' => 'Non ci saranno altri tentativi.',
    'It will be tried again on the next run.' => 'Verrà riprovato alla prossima esecuzione.',
    'The message to the group channel {name} was sent by another node.' => 'Il messaggio al canale di gruppo {name} è stato inviato da un altro nodo.',
    // plural forms: one, other
    'The message was sent to the group channel {name} in {n} part.' => ['Il messaggio è stato inviato al canale di gruppo {name} in {n} parte.', 'Il messaggio è stato inviato al canale di gruppo {name} in {n} parti.'],
    'The group channel {name} was added.' => 'Il canale di gruppo {name} è stato aggiunto.',
    'The group channel {name} was deleted.' => 'Il canale di gruppo {name} è stato eliminato.',
    'Another node stopped sending the message to the group channel {name}, so this node goes on with it.' => 'Un altro nodo ha smesso di inviare il messaggio al canale di gruppo {name}, quindi questo nodo prosegue l’invio.',
    'The group channel {name} was sent the correction.' => 'La rettifica è stata inviata al canale di gruppo {name}.',
    'Could not send the correction to the group channel {name}.' => 'Non è stato possibile inviare la rettifica al canale di gruppo {name}.',
];
