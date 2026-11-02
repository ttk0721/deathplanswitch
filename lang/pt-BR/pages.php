<?php
declare(strict_types=1);

// Check-in, confirm, respond, history, public page and the vault pages.
// Keys are the English source strings. Pages for family avoid gendered forms.
return [
    // Check-in (owner)
    'Check in: Death Plan Switch' => 'Confirmar presença: Death Plan Switch',
    'Checked in' => 'Presença confirmada',
    'Your switch had fired. Everyone who was contacted has now been told it was a false alarm: {names}.' => 'Seu interruptor havia sido acionado. Todas as pessoas contatadas já foram avisadas de que era um alarme falso: {names}.',
    'Your switch had fired. Everyone who was contacted has now been told it was a false alarm.' => 'Seu interruptor havia sido acionado. Todas as pessoas contatadas já foram avisadas de que era um alarme falso.',
    'Your contacts have read their messages, so any pointer in them has been seen. {link} to review what needs changing.' => 'Seus contatos leram as mensagens deles, então qualquer indicação nelas já foi vista. {link} para revisar o que precisa ser alterado.',
    'Sign in' => 'Entrar',
    '{names} had been asked whether you are all right, and has now been told it was a false alarm. Nobody else was contacted.' => 'Havíamos perguntado a {names} se você estava bem, e agora essa pessoa foi avisada de que era um alarme falso. Ninguém mais foi contatado.',
    '{names} had been asked whether you are all right, and have now been told it was a false alarm. Nobody else was contacted.' => 'Havíamos perguntado a {names} se você estava bem, e agora essas pessoas foram avisadas de que era um alarme falso. Ninguém mais foi contatado.',
    'Thank you. Nobody was contacted.' => 'Agradecemos. Ninguém foi contatado.',
    'Your switch is paused until {date}.' => 'Seu interruptor está pausado até {date}.',
    'Your next check-in email arrives on {date} at {time}.' => 'Seu próximo e-mail de confirmação chegará em {date}, às {time}.',
    'Are you there, {name}?' => 'Você está aí, {name}?',
    'owner' => 'titular',
    'Press the button to check in. Opening this page is not enough.' => 'Clique no botão para confirmar sua presença. Apenas abrir esta página não basta.',
    'I am here: check in' => 'Estou aqui: confirmar',
    'This link is no longer active' => 'Este link não está mais ativo',
    'Each check-in link works once, and for 14 days. If you have a newer email from Death Plan Switch, use the link in that one.' => 'Cada link de confirmação funciona uma única vez e por 14 dias. Se você recebeu um e-mail mais recente do Death Plan Switch, use o link dele.',
    'You can also {link} and check in from the dashboard.' => 'Você também pode {link} e confirmar sua presença pelo painel.',
    'sign in' => 'entrar',

    // Confirm (contact)
    'Death Plan Switch' => 'Death Plan Switch',
    'Confirmed' => 'Confirmado',
    'Thank you, {name}. {owner} has been told. There is nothing else to do.' => 'Agradecemos, {name}. {owner} recebeu o aviso. Não há mais nada a fazer.',
    'If {owner} ever stops checking in, you will hear from the same email address that sent you this request. Add it to your contacts so it is not mistaken for spam.' => 'Se {owner} deixar de confirmar a presença, você receberá uma mensagem do mesmo endereço de e-mail que enviou este pedido. Adicione-o aos seus contatos para que não seja confundido com spam.',
    'Done' => 'Pronto',
    "You will not be contacted by {owner}'s Death Plan Switch. {owner} has been told." => 'O Death Plan Switch de {owner} não entrará em contato com você. {owner} recebeu o aviso.',
    "Still happy to be {owner}'s {role}?" => 'Você continua de acordo com o papel de {role} para {owner}?',
    '{owner} has listed you as their {role}' => '{owner} indicou você no papel de {role}',
    '{owner} uses Death Plan Switch, a small app on their own server. Every week it asks {owner} to check in. If they stop, it emails the people they have chosen.' => '{owner} usa o Death Plan Switch, um pequeno aplicativo no próprio servidor. Toda semana ele pede a {owner} que confirme a presença. Se isso deixar de acontecer, ele envia e-mails às pessoas escolhidas.',
    'As next of kin you would be asked first. If {owner} misses a check-in and three days of reminders, you would get one email asking whether everything is all right, with two buttons to answer.' => 'Como pessoa mais próxima, a primeira consulta seria feita a você. Se {owner} deixar de confirmar a presença e passarem três dias de lembretes, você receberia um e-mail perguntando se está tudo bem, com dois botões para responder.',
    'If {owner} stops checking in and their next of kin cannot confirm they are well, you would get one email telling you what {owner} needs you to do.' => 'Se {owner} deixar de confirmar a presença e a pessoa mais próxima não puder confirmar que está tudo bem, você receberia um e-mail com o que {owner} precisa que você faça.',
    'Confirming tells {owner} that {address} reaches you, and that you are willing.' => 'Ao confirmar, {owner} saberá que {address} chega até você e que você está de acordo.',
    'Yes, I confirm' => 'Sim, confirmo',
    'No, leave me out' => 'Não, prefiro ficar de fora',
    'It has either been used already or has expired. If you meant to confirm, ask the person who listed you to send a new request.' => 'Ele já foi usado ou expirou. Se você queria confirmar, peça à pessoa que indicou você que envie um novo pedido.',

    // Respond (next of kin)
    'All fine' => 'Está tudo bem',
    'You have seen or spoken to {owner} and they are all right. Nobody else is contacted, and {owner} is told you answered.' => 'Você viu ou falou com {owner} e está tudo bem. Ninguém mais será contatado, e {owner} receberá o aviso de que você respondeu.',
    "Something's wrong" => 'Algo não está bem',
    '{owner} cannot run their business at the moment.' => '{owner} não consegue cuidar dos próprios assuntos no momento.',
    'These people are sent their instructions straight away: {names}.' => 'Estas pessoas receberão as instruções delas imediatamente: {names}.',
    'The people they chose are sent their instructions straight away.' => 'As pessoas escolhidas receberão as instruções delas imediatamente.',
    'You are sent yours too.' => 'Você também receberá as suas.',
    'Thank you' => 'Agradecemos',
    'Your answer has been recorded. Nobody else has been contacted, and {owner} has been told that you answered.' => 'Sua resposta foi registrada. Ninguém mais foi contatado, e {owner} recebeu o aviso de que você respondeu.',
    'Your answer has been recorded.' => 'Sua resposta foi registrada.',
    'These people have been sent their instructions: {names}.' => 'Estas pessoas receberam as instruções delas: {names}.',
    'The people {owner} chose have been sent their instructions.' => 'As pessoas escolhidas por {owner} receberam as instruções delas.',
    'You have been emailed a message from {owner} as well, with what to do next.' => 'Também enviamos a você uma mensagem de {owner}, com o que fazer em seguida.',
    'Is {owner} all right?' => '{owner} está bem?',
    '{owner} has not answered their weekly check-in or its reminders. You are their next of kin, so you are asked first. Nothing has been sent to anyone else.' => '{owner} não respondeu à confirmação semanal nem aos lembretes. Você é a pessoa mais próxima, por isso perguntamos a você primeiro. Nada foi enviado a mais ninguém.',
    'Not sure yet? You can come back to this page. If neither you nor {owner} answers by the time given in the email, the others are sent their instructions.' => 'Ainda não sabe? Você pode voltar a esta página. Se nem você nem {owner} responderem até o prazo indicado no e-mail, as demais pessoas receberão as instruções delas.',
    'It has either been used already, or the check it belonged to has been settled. You do not need to do anything.' => 'Ele já foi usado, ou a verificação a que pertencia já foi resolvida. Você não precisa fazer nada.',

    // History
    'History: Death Plan Switch' => 'Histórico: Death Plan Switch',
    'History' => 'Histórico',
    'Everything the switch has done, newest first. Scheduler runs that found nothing to do are not listed.' => 'Tudo o que o interruptor fez, do mais recente ao mais antigo. As execuções do agendador que não tinham nada a fazer não aparecem.',
    'Nothing has happened yet.' => 'Ainda não aconteceu nada.',

    // Public page
    'This switch is active.' => 'Este interruptor está ativo.',
    'This switch is not active.' => 'Este interruptor não está ativo.',
    'This switch is armed.' => 'O interruptor está de vigia.',
    'Triggered' => 'Acionado',
    'This switch has been triggered.' => 'Este interruptor foi acionado.',
    'Open the vault' => 'Abrir o cofre',
    'Armed' => 'Armado',
    'Not active' => 'Inativo',
    'Owner sign-in' => 'Acesso do titular',

    // Vault (owner and recipient)
    'That recipient has no contact with an email address, so no invitation was sent.' => 'Esse destinatário não tem um contato com endereço de e-mail, por isso nenhum convite foi enviado.',
    'Invitation sent to {name}.' => 'Convite enviado para {name}.',
    'The invitation could not be sent. Check the mail settings.' => 'Não foi possível enviar o convite. Verifique as configurações de e-mail.',
    'Vault: Death Plan Switch' => 'Cofre: Death Plan Switch',
    'Vault' => 'Cofre',
    "What you leave for the people you choose, locked in your browser. Only a recipient's code or password opens it, and only after the switch has fired." => 'O que você deixa para as pessoas que escolhe, trancado no seu navegador. Só o código ou a senha de um destinatário o abre, e somente depois que o interruptor for acionado.',
    'The vault needs JavaScript. Turn it on for this page.' => 'O cofre precisa de JavaScript. Ative-o nesta página.',
    'This page needs JavaScript, because everything is decrypted in your browser.' => 'Esta página precisa de JavaScript, porque tudo é descriptografado no seu navegador.',

    // Public landing page and background images
    'This switch is being set up.' => 'Este interruptor está sendo configurado.',
    'All is well.' => 'Está tudo bem.',
    'Set up' => 'Configurar',
    'Dawn' => 'Amanhecer',
    'Sea' => 'Mar',
    'Forest' => 'Floresta',
    'Paper' => 'Papel',
    'Plain' => 'Liso',
    'That file is empty.' => 'Esse arquivo está vazio.',
    'The image can be up to 3 MB.' => 'A imagem pode ter até 3 MB.',
    'Use a JPEG, PNG or WebP image.' => 'Use uma imagem JPEG, PNG ou WebP.',
    'That file is not a valid image.' => 'Esse arquivo não é uma imagem válida.',
    'The image is too large in pixels. Use one up to {n} pixels on each side.' => 'A imagem tem pixels demais. Use uma de até {n} pixels de cada lado.',

    // Respond (trusted people)
    'Nobody is sent anything early. If {owner} does not check in and nobody answers "All fine" by the time given in the email, the people {owner} chose are sent their instructions.' => 'Nada é enviado antes da hora. Se {owner} não confirmar a presença e ninguém responder “Está tudo bem” até o prazo indicado no e-mail, as pessoas escolhidas por {owner} receberão as instruções delas.',
    'Your answer has been recorded. Nobody else will be asked. The others who were asked are told it was a false alarm, and {owner} has been told that you answered.' => 'Sua resposta foi registrada. Ninguém mais será consultado. As outras pessoas que já foram consultadas são avisadas de que foi um alarme falso, e {owner} recebeu o aviso de que você respondeu.',
    'The other trusted people are still asked in turn, and nobody is sent anything early.' => 'As outras pessoas de confiança continuam sendo consultadas uma após a outra, e nada é enviado antes da hora.',
    '{owner} has not answered their weekly check-in or its reminders. You are one of the people they trust, so you are asked whether everything is all right. Nothing has been sent to anyone else.' => '{owner} não respondeu à confirmação semanal nem aos lembretes. Você é uma das pessoas em quem confia, por isso perguntamos a você se está tudo bem. Nada foi enviado a mais ninguém.',
];
