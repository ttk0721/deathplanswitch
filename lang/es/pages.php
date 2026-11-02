<?php
declare(strict_types=1);

// Check-in, confirm, respond, history, public page and the vault pages.
// Keys are the English source strings. Pages for family avoid gendered forms.
return [
    // Check-in (owner)
    'Check in: Death Plan Switch' => 'Confirmar presencia: Death Plan Switch',
    'Checked in' => 'Presencia confirmada',
    'Your switch had fired. Everyone who was contacted has now been told it was a false alarm: {names}.' => 'Tu interruptor se había accionado. Todas las personas con las que se contactó ya han sido avisadas de que era una falsa alarma: {names}.',
    'Your switch had fired. Everyone who was contacted has now been told it was a false alarm.' => 'Tu interruptor se había accionado. Todas las personas con las que se contactó ya han sido avisadas de que era una falsa alarma.',
    'Your contacts have read their messages, so any pointer in them has been seen. {link} to review what needs changing.' => 'Tus contactos han leído sus mensajes, así que cualquier indicación que contengan ya se ha visto. {link} para revisar qué conviene cambiar.',
    'Sign in' => 'Iniciar sesión',
    '{names} had been asked whether you are all right, and has now been told it was a false alarm. Nobody else was contacted.' => 'Se había preguntado a {names} si estás bien, y ya se le ha avisado de que era una falsa alarma. No se contactó con nadie más.',
    '{names} had been asked whether you are all right, and have now been told it was a false alarm. Nobody else was contacted.' => 'Se había preguntado a {names} si estás bien, y ya se les ha avisado de que era una falsa alarma. No se contactó con nadie más.',
    'Thank you. Nobody was contacted.' => 'Gracias. No se contactó con nadie.',
    'Your switch is paused until {date}.' => 'Tu interruptor está en pausa hasta el {date}.',
    'Your next check-in email arrives on {date} at {time}.' => 'Tu próximo correo de confirmación llegará el {date} a las {time}.',
    'Are you there, {name}?' => '¿Estás ahí, {name}?',
    'owner' => 'titular',
    'Press the button to check in. Opening this page is not enough.' => 'Pulsa el botón para confirmar tu presencia. No basta con abrir esta página.',
    'I am here: check in' => 'Estoy aquí: confirmar',
    'This link is no longer active' => 'Este enlace ya no está activo',
    'Each check-in link works once, and for 14 days. If you have a newer email from Death Plan Switch, use the link in that one.' => 'Cada enlace de confirmación funciona una sola vez y durante 14 días. Si tienes un correo más reciente de Death Plan Switch, usa el enlace de ese.',
    'You can also {link} and check in from the dashboard.' => 'También puedes {link} y confirmar tu presencia desde el panel.',
    'sign in' => 'iniciar sesión',

    // Confirm (contact)
    'Death Plan Switch' => 'Death Plan Switch',
    'Confirmed' => 'Confirmado',
    'Thank you, {name}. {owner} has been told. There is nothing else to do.' => 'Gracias, {name}. Se ha avisado a {owner}. No hay nada más que hacer.',
    'If {owner} ever stops checking in, you will hear from the same email address that sent you this request. Add it to your contacts so it is not mistaken for spam.' => 'Si {owner} dejara de confirmar su presencia, recibirá un mensaje desde la misma dirección de correo que le envió esta solicitud. Añádala a sus contactos para que no se confunda con spam.',
    'Done' => 'Hecho',
    "You will not be contacted by {owner}'s Death Plan Switch. {owner} has been told." => 'El Death Plan Switch de {owner} no se pondrá en contacto con usted. Se ha avisado a {owner}.',
    "Still happy to be {owner}'s {role}?" => '¿Sigue de acuerdo con ser {role} de {owner}?',
    '{owner} has listed you as their {role}' => '{owner} le ha indicado como {role}',
    '{owner} uses Death Plan Switch, a small app on their own server. Every week it asks {owner} to check in. If they stop, it emails the people they have chosen.' => '{owner} usa Death Plan Switch, una pequeña aplicación en su propio servidor. Cada semana le pide a {owner} que confirme su presencia. Si deja de hacerlo, escribe a las personas que ha elegido.',
    'As next of kin you would be asked first. If {owner} misses a check-in and three days of reminders, you would get one email asking whether everything is all right, with two buttons to answer.' => 'Como persona más cercana, se le preguntaría primero. Si {owner} no confirma su presencia y pasan tres días de recordatorios, recibiría un correo preguntando si todo va bien, con dos botones para responder.',
    'If {owner} stops checking in and their next of kin cannot confirm they are well, you would get one email telling you what {owner} needs you to do.' => 'Si {owner} deja de confirmar su presencia y su persona más cercana no puede confirmar que está bien, recibiría un correo con lo que {owner} necesita que haga.',
    'Confirming tells {owner} that {address} reaches you, and that you are willing.' => 'Al confirmar, {owner} sabrá que {address} le llega y que está de acuerdo.',
    'Yes, I confirm' => 'Sí, confirmo',
    'No, leave me out' => 'No, prefiero quedar al margen',
    'It has either been used already or has expired. If you meant to confirm, ask the person who listed you to send a new request.' => 'Ya se ha usado o ha caducado. Si quería confirmar, pida a la persona que le indicó que envíe una nueva solicitud.',

    // Respond (next of kin)
    'All fine' => 'Todo va bien',
    'You have seen or spoken to {owner} and they are all right. Nobody else is contacted, and {owner} is told you answered.' => 'Ha visto o hablado con {owner} y está bien. No se contactará con nadie más y se avisará a {owner} de que ha respondido.',
    "Something's wrong" => 'Algo no va bien',
    '{owner} cannot run their business at the moment.' => '{owner} no puede ocuparse de sus asuntos en este momento.',
    'These people are sent their instructions straight away: {names}.' => 'Estas personas recibirán sus instrucciones de inmediato: {names}.',
    'The people they chose are sent their instructions straight away.' => 'Las personas que ha elegido recibirán sus instrucciones de inmediato.',
    'You are sent yours too.' => 'Usted también recibirá las suyas.',
    'Thank you' => 'Gracias',
    'Your answer has been recorded. Nobody else has been contacted, and {owner} has been told that you answered.' => 'Su respuesta se ha registrado. No se ha contactado con nadie más y se ha avisado a {owner} de que ha respondido.',
    'Your answer has been recorded.' => 'Su respuesta se ha registrado.',
    'These people have been sent their instructions: {names}.' => 'Estas personas han recibido sus instrucciones: {names}.',
    'The people {owner} chose have been sent their instructions.' => 'Las personas que eligió {owner} han recibido sus instrucciones.',
    'You have been emailed a message from {owner} as well, with what to do next.' => 'También le hemos enviado un mensaje de {owner} con lo que conviene hacer a continuación.',
    'Is {owner} all right?' => '¿Está bien {owner}?',
    '{owner} has not answered their weekly check-in or its reminders. You are their next of kin, so you are asked first. Nothing has been sent to anyone else.' => '{owner} no ha respondido a su confirmación semanal ni a los recordatorios. Usted es su persona más cercana, así que le preguntamos primero a usted. No se ha enviado nada a nadie más.',
    'Not sure yet? You can come back to this page. If neither you nor {owner} answers by the time given in the email, the others are sent their instructions.' => '¿Aún no lo sabe? Puede volver a esta página. Si ni usted ni {owner} responden antes de la fecha indicada en el correo, las demás personas recibirán sus instrucciones.',
    'It has either been used already, or the check it belonged to has been settled. You do not need to do anything.' => 'Ya se ha usado, o la comprobación a la que pertenecía ya está resuelta. No necesita hacer nada.',

    // History
    'History: Death Plan Switch' => 'Historial: Death Plan Switch',
    'History' => 'Historial',
    'Everything the switch has done, newest first. Scheduler runs that found nothing to do are not listed.' => 'Todo lo que ha hecho el interruptor, de lo más reciente a lo más antiguo. Las ejecuciones del planificador que no tenían nada que hacer no aparecen.',
    'Nothing has happened yet.' => 'Todavía no ha ocurrido nada.',

    // Public page
    'This switch is active.' => 'Este interruptor está activo.',
    'This switch is not active.' => 'Este interruptor no está activo.',
    'This switch is armed.' => 'Este interruptor está activo.',
    'Triggered' => 'Accionado',
    'This switch has been triggered.' => 'Este interruptor se ha accionado.',
    'Open the vault' => 'Abrir la caja fuerte',
    'Armed' => 'Activo',
    'Not active' => 'No activo',
    'Owner sign-in' => 'Acceso del titular',

    // Vault (owner and recipient)
    'That recipient has no contact with an email address, so no invitation was sent.' => 'Ese destinatario no tiene un contacto con dirección de correo, así que no se envió ninguna invitación.',
    'Invitation sent to {name}.' => 'Invitación enviada a {name}.',
    'The invitation could not be sent. Check the mail settings.' => 'No se pudo enviar la invitación. Revisa los ajustes de correo.',
    'Vault: Death Plan Switch' => 'Caja fuerte: Death Plan Switch',
    'Vault' => 'Caja fuerte',
    "What you leave for the people you choose, locked in your browser. Only a recipient's code or password opens it, and only after the switch has fired." => 'Lo que dejas para las personas que eliges, cerrado en tu navegador. Solo lo abre el código o la contraseña de un destinatario, y únicamente después de que el interruptor se haya accionado.',
    'The vault needs JavaScript. Turn it on for this page.' => 'La caja fuerte necesita JavaScript. Actívalo para esta página.',
    'This page needs JavaScript, because everything is decrypted in your browser.' => 'Esta página necesita JavaScript, porque todo se descifra en tu navegador.',
];
