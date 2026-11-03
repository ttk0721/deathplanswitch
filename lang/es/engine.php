<?php
/** Spanish wording for the core classes: engine warnings and checklist, web errors, setup checks, contact labels. */
declare(strict_types=1);

return [
    // Contact labels
    'next of kin' => 'persona más cercana',
    'legal contact' => 'contacto legal',
    'contact' => 'contacto',
    'Next of kin' => 'Persona más cercana',
    'Legal contact' => 'Contacto legal',
    'Other contact' => 'Otro contacto',
    'Contact' => 'Contacto',
    '(none listed)' => '(ninguno en la lista)',

    // Web
    'The data folder needs attention' => 'La carpeta de datos necesita atención',
    'Nothing has been changed. See "If a data file is damaged" in the README.' => 'No se ha cambiado nada. Consulta «If a data file is damaged» en el README.',
    'Too many wrong passwords. Try again {when}.' => 'Demasiadas contraseñas incorrectas. Inténtalo de nuevo {when}.',
    'That password is not right.' => 'Esa contraseña no es correcta.',
    'That form had expired. Go back, reload the page and try again.' => 'Ese formulario había caducado. Vuelve atrás, recarga la página e inténtalo de nuevo.',

    // Setup
    'PHP {version} or newer' => 'PHP {version} o posterior',
    'This server runs PHP {version}. Ask your host to switch to a current version.' => 'Este servidor usa PHP {version}. Pide a tu proveedor que cambie a una versión actual.',
    'The OpenSSL extension, for encrypted mail' => 'La extensión OpenSSL, para el correo cifrado',
    'Ask your host to enable the PHP openssl extension.' => 'Pide a tu proveedor que active la extensión openssl de PHP.',
    'The mbstring extension' => 'La extensión mbstring',
    'Ask your host to enable the PHP mbstring extension.' => 'Pide a tu proveedor que active la extensión mbstring de PHP.',
    'The data folder can be written to' => 'Se puede escribir en la carpeta de datos',
    'PHP cannot write to {dir}. Give that folder to the user the web pages run as' => 'PHP no puede escribir en {dir}. Asigna esa carpeta al usuario con el que se ejecutan las páginas web',
    'Choose a password of at least {min} characters.' => 'Elige una contraseña de al menos {min} caracteres.',
    'That password is too long.' => 'Esa contraseña es demasiado larga.',
    'Enter the mail server host, for example smtp.example.com.' => 'Escribe el servidor de correo, por ejemplo smtp.example.com.',
    'Enter the mail server port. 587 is the usual one.' => 'Escribe el puerto del servidor de correo. Lo habitual es el 587.',
    'Enter the address the switch sends from. Your mail service must allow it.' => 'Escribe la dirección desde la que envía el interruptor. Tu servicio de correo debe permitirlo.',
    'There is no code waiting. Send a new one.' => 'No hay ningún código pendiente. Envía uno nuevo.',
    'That code has expired. Send a new one.' => 'Ese código ha caducado. Envía uno nuevo.',
    'That code is not right. Check the email and try again.' => 'Ese código no es correcto. Revisa el correo e inténtalo de nuevo.',

    // Engine: actions
    'Open the vault' => 'Abrir la caja fuerte',
    'You disarmed it from the dashboard.' => 'Lo desarmaste desde el panel.',
    'Their previous address is still in use.' => 'Se sigue usando su dirección anterior.',
    '{name} declined to be your {role}, so a required contact is missing.' => '{name} rechazó el papel de {role}, así que falta un contacto necesario.',
    'Your switch has been disarmed, because it needs this role filled.' => 'Tu interruptor se ha desarmado, porque este papel debe estar cubierto.',

    // Engine: checklist
    'The scheduler is running' => 'El planificador está funcionando',
    'No run has been recorded yet. Add the crontab line shown in Settings.' => 'Todavía no se ha registrado ninguna ejecución. Añade la línea de crontab que aparece en Ajustes.',
    'The last run was {when}. Check the crontab line shown in Settings.' => 'La última ejecución fue {when}. Revisa la línea de crontab que aparece en Ajustes.',
    'The data folder cannot be read from the web' => 'La carpeta de datos no se puede leer desde la web',
    'Files in the data folder can be opened in a browser. See "Protecting the data folder" in the README.' => 'Los archivos de la carpeta de datos se pueden abrir desde un navegador. Consulta «Protecting the data folder» en el README.',
    'The server could not test this by itself. Run the check from Settings.' => 'El servidor no pudo comprobarlo por sí solo. Ejecuta la comprobación desde Ajustes.',
    'Your next of kin has confirmed' => 'Tu persona más cercana ha confirmado',
    'Add your next of kin.' => 'Añade a tu persona más cercana.',
    'Every message says where things are' => 'Cada mensaje dice dónde está cada cosa',
    'Still holding starter text in square brackets: {names}.' => 'Aún contienen texto de ejemplo entre corchetes: {names}.',
    'Checked once your contacts have confirmed.' => 'Se comprobará cuando tus contactos hayan confirmado.',
    'Each contact has been given what they need' => 'Cada contacto tiene lo que necesita',
    'Not ticked yet: {names}.' => 'Aún sin marcar: {names}.',
    'A second email address for reminders' => 'Una segunda dirección de correo para los recordatorios',
    'Recommended: an address with a different provider, in case your main one fails. Add it in Settings.' => 'Recomendado: una dirección de otro proveedor, por si falla la principal. Añádela en Ajustes.',
    '{name} declined. Choose someone else.' => '{name} ha rechazado. Elige a otra persona.',
    'Send {name} their confirmation email.' => 'Envía a {name} su correo de confirmación.',
    'Waiting for {name} to confirm. The email went {when}.' => 'Esperando a que {name} confirme. El correo se envió {when}.',

    // Engine: warnings
    '{name}.json was damaged and the last good copy is in use. Check what changed recently, then save any setting to rewrite the file.' => '{name}.json estaba dañado y se está usando la última copia correcta. Revisa qué ha cambiado hace poco y después guarda cualquier ajuste para reescribir el archivo.',
    'The scheduler has never run. Nothing will be sent until it does.' => 'El planificador no se ha ejecutado nunca. No se enviará nada hasta que lo haga.',
    'The scheduler last ran {when}. While it is stopped, the switch cannot send anything.' => 'El planificador se ejecutó por última vez {when}. Mientras esté detenido, el interruptor no puede enviar nada.',
    'This node has not heard from any other node for {duration}. Serious steps are delayed while it is cut off.' => 'Este nodo no ha tenido noticias de ningún otro nodo desde hace {duration}. Los pasos importantes se retrasan mientras esté aislado.',
    'This node has not heard from any other node yet. Serious steps are delayed while it is cut off.' => 'Este nodo aún no ha tenido noticias de ningún otro nodo. Los pasos importantes se retrasan mientras esté aislado.',
    'The last email could not be sent ({when}): {error}' => 'No se pudo enviar el último correo ({when}): {error}',
    'notification' => 'notificación',
    'unknown error' => 'error desconocido',
    'The {label} channel failed {when}: {error}' => 'El canal «{label}» falló {when}: {error}',
    'Files in the data folder can be opened in a browser.' => 'Los archivos de la carpeta de datos se pueden abrir desde un navegador.',
    'The server could not check whether the data folder is blocked from the web.' => 'El servidor no pudo comprobar si la carpeta de datos está bloqueada para la web.',
    'A required contact is missing or unconfirmed.' => 'Falta un contacto necesario o no ha confirmado.',
    '{name} declined and will not be contacted.' => '{name} ha rechazado y no recibirá mensajes.',
    '{name} has not confirmed, so will not be contacted.' => '{name} no ha confirmado, así que no recibirá mensajes.',
    '{name} has not confirmed their new address. The old one is still in use.' => '{name} no ha confirmado su nueva dirección. Se sigue usando la anterior.',
    '{name} has not answered their yearly reconfirmation. Check their address still works.' => '{name} no ha respondido a su reconfirmación anual. Comprueba que su dirección sigue funcionando.',
    'The message for {name} still holds starter text in square brackets.' => 'El mensaje para {name} aún contiene texto de ejemplo entre corchetes.',
    'Your yearly review is due: confirm each contact still has what they need.' => 'Toca hacer la revisión anual: confirma que cada contacto sigue teniendo lo que necesita.',
    'This install is not using HTTPS. Links in its emails can be read in transit.' => 'Esta instalación no usa HTTPS. Los enlaces de sus correos pueden leerse por el camino.',

    // Engine: message previews
    'When you miss a check-in and its reminders' => 'Cuando no confirmas tu presencia ni respondes a los recordatorios',
    'When the switch fires' => 'Cuando el interruptor se acciona',

    // Technical helper
    'technical helper' => 'ayudante técnico',
    'Technical helper' => 'Ayudante técnico',
    'Technical helper (optional)' => 'Ayudante técnico (opcional)',
    'Someone who can keep your servers, accounts or projects running.' => 'Alguien que pueda mantener en marcha tus servidores, cuentas o proyectos.',

    // not told in advance, group channels
    '{name} is set not to be told in advance, and trusted people have to know. Turn that option off first.' => '{name} tiene activada la opción «Sin aviso previo», y las personas de confianza tienen que saberlo. Desactiva primero esa opción.',
    'Your next of kin is asked first whether you are all right, so they have to know about the switch in advance.' => 'A tu persona más cercana se le pregunta primero si estás bien, así que tiene que conocer el interruptor con antelación.',
    'Trusted people are asked whether you are all right, so they have to know about the switch in advance. Remove them from trusted people first.' => 'A las personas de confianza se les pregunta si estás bien, así que tienen que conocer el interruptor con antelación. Quita primero a esta persona de las personas de confianza.',
    'They hold vault packages, and opening those needs a code given to them in advance. Remove them from the vault first.' => 'Esta persona tiene paquetes en la caja fuerte, y para abrirlos necesita un código entregado con antelación. Quítala primero de la caja fuerte.',
    '{name} is set not to be told in advance, so they cannot hold vault packages. Turn that option off on their contact page first.' => '{name} tiene activada la opción «Sin aviso previo», así que no puede tener paquetes en la caja fuerte. Desactiva primero esa opción en la página de este contacto.',
    'Enter a name for the group channel.' => 'Escribe un nombre para el canal de grupo.',
    'The name can be at most {max} characters.' => 'El nombre puede tener como máximo {max} caracteres.',
    'Write the message the group channel is sent.' => 'Escribe el mensaje que recibirá el canal de grupo.',
    'The message can be at most {max} characters.' => 'El mensaje puede tener como máximo {max} caracteres.',
    'A test was sent less than a minute ago. Try again in a minute.' => 'Se envió una prueba hace menos de un minuto. Inténtalo de nuevo dentro de un minuto.',
    'This is a test message. Nothing has happened and nothing needs to be done.' => 'Este es un mensaje de prueba. No ha ocurrido nada y no hay que hacer nada.',
    'You can have at most {max} group channels.' => 'Puedes tener como máximo {max} canales de grupo.',
    'Please disregard the earlier message from {owner}: it was sent by mistake, and {owner} is fine.' => 'Rogamos ignorar el mensaje anterior de {owner}: se envió por error y {owner} se encuentra bien.',
    'A group channel message cannot use {refused}: everyone in the channel would see it. Only these fill themselves in here: {allowed}.' => 'Un mensaje de canal de grupo no puede usar {refused}: lo vería todo el canal. Aquí solo se rellenan solas estas palabras: {allowed}.',
];
