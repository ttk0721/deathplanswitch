<?php
/** Brazilian Portuguese wording for the history log: entries written from now on, and the words that fill messages in. */
declare(strict_types=1);

return [
    // Check-in methods (also stored as the way the last check-in came in)
    'the dashboard' => 'o painel',
    'the emailed link' => 'o link do e-mail',
    'another node' => 'outro nó',
    'arming the switch' => 'ativar o interruptor',
    'pausing the switch' => 'pausar o interruptor',
    'ending the pause' => 'encerrar a pausa',

    // Cycle
    'Weekly check-in email sent.' => 'E-mail semanal de confirmação de presença enviado.',
    'Reminder {number} sent to the owner.' => 'Lembrete n.º {number} enviado ao titular.',
    'No check-in after 72 hours. The next of kin has been asked.' => 'Sem confirmação de presença após 72 horas. A pessoa mais próxima foi consultada.',
    'No check-in after 72 hours. The next of kin could not be emailed; the 72-hour wait has started anyway.' => 'Sem confirmação de presença após 72 horas. Não foi possível enviar o e-mail à pessoa mais próxima; a espera de 72 horas começou mesmo assim.',
    'The next of kin was reminded.' => 'A pessoa mais próxima recebeu um lembrete.',
    'The next of kin answered "Something\'s wrong". The switch has fired.' => 'A pessoa mais próxima respondeu “Algo não está bem”. O interruptor foi acionado.',
    'Neither the owner nor the next of kin confirmed all is well. The switch has fired.' => 'Nem o titular nem a pessoa mais próxima confirmaram que está tudo bem. O interruptor foi acionado.',
    '{role} ({name}) was sent their message.' => 'A mensagem foi enviada para: {role} ({name}).',
    '{name} was told it was a false alarm.' => '{name} foi avisado de que era um alarme falso.',
    'The next of kin answered "All fine". Nobody else was contacted.' => 'A pessoa mais próxima respondeu “Está tudo bem”. Ninguém mais foi contatado.',
    'The next of kin answered "All fine" ({via}). Nobody else was contacted.' => 'A pessoa mais próxima respondeu “Está tudo bem” ({via}). Ninguém mais foi contatado.',
    'The weekly check-in email was sent by another node.' => 'O e-mail semanal de confirmação foi enviado por outro nó.',
    'Another node fired the switch.' => 'Outro nó acionou o interruptor.',
    'Checked in by {via}.' => 'Presença confirmada por: {via}.',
    'Checked in by another node ({via}).' => 'Presença confirmada em outro nó ({via}).',
    'False-alarm notices sent to {names}.' => 'Avisos de alarme falso enviados para: {names}.',

    // Switch
    'The pause ended. The switch is active again.' => 'A pausa terminou. O interruptor está ativo novamente.',
    'The switch was armed.' => 'O interruptor foi ativo.',
    'The switch was disarmed. {reason}' => 'O interruptor foi desativado. {reason}',
    'The switch was paused for {duration}.' => 'O interruptor foi pausado por {duration}.',
    'The pause was ended early.' => 'A pausa foi encerrada antes do prazo.',
    'Yearly review completed.' => 'Revisão anual concluída.',
    'The data folder can be read from the web.' => 'A pasta de dados pode ser lida pela web.',

    // Contacts
    '{name} confirmed as {role}.' => '{name} confirmou como {role}.',
    'The new address for {name} was declined. The old one is still in use.' => 'O novo endereço de {name} foi recusado. O antigo continua em uso.',
    '{name} declined to be {role}.' => '{name} recusou o papel de {role}.',
    '{name} ({role}) was deleted.' => '{name} ({role}) foi excluído(a).',
    '{name} was added as {role}.' => '{name} foi adicionado(a) como {role}.',

    // Sending
    'Notified {where}' => 'Notificado: {where}',
    'Could not notify {where}.' => 'Não foi possível notificar: {where}.',
    'Sent "{subject}" to {to}' => 'Enviado “{subject}” para: {to}',
    'Could not send "{subject}" to {to}' => 'Não foi possível enviar “{subject}” para: {to}',

    // Settings and sign-in
    'Backup email address confirmed: {address}.' => 'Endereço de e-mail de reserva confirmado: {address}.',
    'Main email address confirmed: {address}.' => 'Endereço de e-mail principal confirmado: {address}.',
    'A wrong password was entered at sign-in.' => 'Uma senha incorreta foi digitada ao entrar.',

    // Message placeholders (contact page)
    'The name of the person receiving the message' => 'O nome da pessoa que recebe a mensagem',
    'Whole days since your last check-in' => 'Dias completos desde a sua última confirmação de presença',
    'The date of your last check-in' => 'A data da sua última confirmação de presença',
    'Next of kin: name, email, phone' => 'Pessoa mais próxima: nome, e-mail, telefone',
    'Technical contact: name, email, phone' => 'Contato técnico: nome, e-mail, telefone',
    'Everyone else on your list, one per line' => 'Todos os outros da sua lista, um por linha',

    // Settings, cluster, install, storage
    'The weekly check-in time was changed to {day} at {time} ({tz}).' => 'O horário da confirmação semanal foi alterado para: {day}, {time} ({tz}).',
    'Mail settings were changed.' => 'As configurações de e-mail foram alteradas.',
    'The owner password was changed.' => 'A senha do titular foi alterada.',
    'A node was added to the cluster.' => 'Um nó foi adicionado ao cluster.',
    'A node public address was changed.' => 'O endereço público de um nó foi alterado.',
    'A node was removed from the cluster.' => 'Um nó foi removido do cluster.',
    'The cluster settings were changed.' => 'As configurações do cluster foram alteradas.',
    'Death Plan Switch {version} was installed.' => 'O Death Plan Switch {version} foi instalado.',
    'Vault API error: {detail}' => 'Erro da API do cofre: {detail}',
    '{name}.json was unreadable. The last good copy was used instead.' => 'Não foi possível ler {name}.json. A última cópia válida foi usada no lugar.',
];
