<?php
/** Spanish wording for the history log: entries written from now on, and the words that fill messages in. */
declare(strict_types=1);

return [
    // Check-in methods (also stored as the way the last check-in came in)
    'the dashboard' => 'el panel',
    'the emailed link' => 'el enlace del correo',
    'another node' => 'otro nodo',
    'arming the switch' => 'armar el interruptor',
    'pausing the switch' => 'pausar el interruptor',
    'ending the pause' => 'terminar la pausa',

    // Cycle
    'Weekly check-in email sent.' => 'Correo semanal de confirmación de presencia enviado.',
    'Reminder {number} sent to the owner.' => 'Recordatorio n.º {number} enviado al titular.',
    'No check-in after 72 hours. The next of kin has been asked.' => 'Sin confirmación de presencia tras 72 horas. Se ha preguntado a la persona más cercana.',
    'No check-in after 72 hours. The next of kin could not be emailed; the 72-hour wait has started anyway.' => 'Sin confirmación de presencia tras 72 horas. No se pudo enviar el correo a la persona más cercana; la espera de 72 horas ha empezado de todos modos.',
    'The next of kin was reminded.' => 'Se ha enviado un recordatorio a la persona más cercana.',
    'The next of kin answered "Something\'s wrong". The switch has fired.' => 'La persona más cercana respondió «Algo no va bien». El interruptor se ha accionado.',
    'Neither the owner nor the next of kin confirmed all is well. The switch has fired.' => 'Ni el titular ni la persona más cercana confirmaron que todo va bien. El interruptor se ha accionado.',
    '{role} ({name}) was sent their message.' => 'Se envió su mensaje a: {role} ({name}).',
    '{name} was told it was a false alarm.' => 'Se avisó a {name} de que era una falsa alarma.',
    'The next of kin answered "All fine". Nobody else was contacted.' => 'La persona más cercana respondió «Todo va bien». No se contactó con nadie más.',
    'The next of kin answered "All fine" ({via}). Nobody else was contacted.' => 'La persona más cercana respondió «Todo va bien» ({via}). No se contactó con nadie más.',
    'The weekly check-in email was sent by another node.' => 'El correo semanal de confirmación lo envió otro nodo.',
    'Another node fired the switch.' => 'Otro nodo accionó el interruptor.',
    'Checked in by {via}.' => 'Presencia confirmada mediante: {via}.',
    'Checked in by another node ({via}).' => 'Presencia confirmada en otro nodo ({via}).',
    'False-alarm notices sent to {names}.' => 'Avisos de falsa alarma enviados a: {names}.',

    // Switch
    'The pause ended. The switch is active again.' => 'La pausa terminó. El interruptor vuelve a estar activo.',
    'The switch was armed.' => 'El interruptor se armó.',
    'The switch was disarmed. {reason}' => 'El interruptor se desarmó. {reason}',
    'The switch was paused for {duration}.' => 'El interruptor se puso en pausa durante {duration}.',
    'The pause was ended early.' => 'La pausa se terminó antes de tiempo.',
    'Yearly review completed.' => 'Revisión anual terminada.',
    'The data folder can be read from the web.' => 'La carpeta de datos se puede leer desde la web.',

    // Contacts
    '{name} confirmed as {role}.' => '{name} confirmó como {role}.',
    'The new address for {name} was declined. The old one is still in use.' => 'La nueva dirección de {name} fue rechazada. Se sigue usando la anterior.',
    '{name} declined to be {role}.' => '{name} rechazó ser {role}.',
    '{name} ({role}) was deleted.' => 'Se eliminó a {name} ({role}).',
    '{name} was added as {role}.' => 'Se añadió a {name} como {role}.',

    // Sending
    'Notified {where}' => 'Se notificó a: {where}',
    'Could not notify {where}.' => 'No se pudo notificar a: {where}.',
    'Sent "{subject}" to {to}' => 'Se envió «{subject}» a: {to}',
    'Could not send "{subject}" to {to}' => 'No se pudo enviar «{subject}» a: {to}',

    // Settings and sign-in
    'Backup email address confirmed: {address}.' => 'Dirección de correo de respaldo confirmada: {address}.',
    'Main email address confirmed: {address}.' => 'Dirección de correo principal confirmada: {address}.',
    'A wrong password was entered at sign-in.' => 'Se introdujo una contraseña incorrecta al iniciar sesión.',

    // Message placeholders (contact page)
    'The name of the person receiving the message' => 'El nombre de la persona que recibe el mensaje',
    'Whole days since your last check-in' => 'Días completos desde tu última confirmación de presencia',
    'The date of your last check-in' => 'La fecha de tu última confirmación de presencia',
    'Next of kin: name, email, phone' => 'Persona más cercana: nombre, correo, teléfono',
    'Technical contact: name, email, phone' => 'Contacto técnico: nombre, correo, teléfono',
    'Everyone else on your list, one per line' => 'Todos los demás de tu lista, uno por línea',

    // Settings, cluster, install, storage
    'The weekly check-in time was changed to {day} at {time} ({tz}).' => 'La hora de la confirmación semanal se cambió a: {day}, {time} ({tz}).',
    'Mail settings were changed.' => 'Se cambiaron los ajustes de correo.',
    'The owner password was changed.' => 'Se cambió la contraseña del titular.',
    'A node was added to the cluster.' => 'Se añadió un nodo al clúster.',
    'A node public address was changed.' => 'Se cambió la dirección pública de un nodo.',
    'A node was removed from the cluster.' => 'Se quitó un nodo del clúster.',
    'The cluster settings were changed.' => 'Se cambiaron los ajustes del clúster.',
    'Death Plan Switch {version} was installed.' => 'Se instaló Death Plan Switch {version}.',
    'Vault API error: {detail}' => 'Error de la API de la caja fuerte: {detail}',
    '{name}.json was unreadable. The last good copy was used instead.' => 'No se pudo leer {name}.json. Se usó en su lugar la última copia correcta.',

    // Trusted people
    'another node ({via})' => 'otro nodo ({via})',
    '{name} was asked whether {owner} is all right.' => 'Se preguntó a {name} si {owner} está bien.',
    '{name} could not be emailed.' => 'No se pudo enviar el correo a {name}.',
    '{name} answered "Something\'s wrong". Nothing is sent early; the others are still asked in turn.' => '{name} respondió «Algo no va bien». No se envía nada antes de tiempo y se sigue preguntando a las demás personas por turnos.',
    '{name} answered "All fine". Nobody else will be asked.' => '{name} respondió «Todo va bien». No se preguntará a nadie más.',
    'The settings for the trusted people were changed.' => 'Se cambiaron los ajustes de las personas de confianza.',

    // group channels
    'Could not send the message to the group channel {name}.' => 'No se pudo enviar el mensaje al canal de grupo {name}.',
    'Could not send part {part} of {parts} to the group channel {name}.' => 'No se pudo enviar la parte {part} de {parts} al canal de grupo {name}.',
    'No more attempts will be made.' => 'No se harán más intentos.',
    'It will be tried again on the next run.' => 'Se volverá a intentar en la próxima ejecución.',
    'The message to the group channel {name} was sent by another node.' => 'Otro nodo envió el mensaje al canal de grupo {name}.',
    // plural forms: one, other
    'The message was sent to the group channel {name} in {n} part.' => ['Se envió el mensaje al canal de grupo {name} en {n} parte.', 'Se envió el mensaje al canal de grupo {name} en {n} partes.'],
    'The group channel {name} was added.' => 'Se añadió el canal de grupo {name}.',
    'The group channel {name} was deleted.' => 'Se eliminó el canal de grupo {name}.',
    'Another node stopped sending the message to the group channel {name}, so this node goes on with it.' => 'Otro nodo dejó de enviar el mensaje al canal de grupo {name}, así que este nodo continúa con el envío.',
    'The group channel {name} was sent the correction.' => 'Se envió la rectificación al canal de grupo {name}.',
    'Could not send the correction to the group channel {name}.' => 'No se pudo enviar la rectificación al canal de grupo {name}.',
];
