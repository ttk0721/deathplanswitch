<?php
/** Brazilian Portuguese wording for the core classes: engine warnings and checklist, web errors, setup checks, contact labels. */
declare(strict_types=1);

return [
    // Contact labels
    'next of kin' => 'pessoa mais próxima',
    'legal contact' => 'contato jurídico',
    'contact' => 'contato',
    'Next of kin' => 'Pessoa mais próxima',
    'Legal contact' => 'Contato jurídico',
    'Other contact' => 'Outro contato',
    'Contact' => 'Contato',
    '(none listed)' => '(nenhum na lista)',

    // Web
    'The data folder needs attention' => 'A pasta de dados precisa de atenção',
    'Nothing has been changed. See "If a data file is damaged" in the README.' => 'Nada foi alterado. Veja “If a data file is damaged” no README.',
    'Too many wrong passwords. Try again {when}.' => 'Muitas senhas incorretas. Tente novamente {when}.',
    'That password is not right.' => 'Essa senha não está correta.',
    'That form had expired. Go back, reload the page and try again.' => 'Esse formulário expirou. Volte, recarregue a página e tente novamente.',

    // Setup
    'PHP {version} or newer' => 'PHP {version} ou mais recente',
    'This server runs PHP {version}. Ask your host to switch to a current version.' => 'Este servidor usa PHP {version}. Peça à sua hospedagem que mude para uma versão atual.',
    'The OpenSSL extension, for encrypted mail' => 'A extensão OpenSSL, para e-mail criptografado',
    'Ask your host to enable the PHP openssl extension.' => 'Peça à sua hospedagem que ative a extensão openssl do PHP.',
    'The mbstring extension' => 'A extensão mbstring',
    'Ask your host to enable the PHP mbstring extension.' => 'Peça à sua hospedagem que ative a extensão mbstring do PHP.',
    'The data folder can be written to' => 'É possível gravar na pasta de dados',
    'PHP cannot write to {dir}. Give that folder to the user the web pages run as' => 'O PHP não consegue gravar em {dir}. Dê essa pasta ao usuário com o qual as páginas web rodam',
    'Choose a password of at least {min} characters.' => 'Escolha uma senha com pelo menos {min} caracteres.',
    'That password is too long.' => 'Essa senha é longa demais.',
    'Enter the mail server host, for example smtp.example.com.' => 'Digite o servidor de e-mail, por exemplo smtp.example.com.',
    'Enter the mail server port. 587 is the usual one.' => 'Digite a porta do servidor de e-mail. O mais comum é a 587.',
    'Enter the address the switch sends from. Your mail service must allow it.' => 'Digite o endereço a partir do qual o interruptor envia. Seu serviço de e-mail precisa permitir.',
    'There is no code waiting. Send a new one.' => 'Não há nenhum código pendente. Envie um novo.',
    'That code has expired. Send a new one.' => 'Esse código expirou. Envie um novo.',
    'That code is not right. Check the email and try again.' => 'Esse código não está correto. Confira o e-mail e tente novamente.',

    // Engine: actions
    'Open the vault' => 'Abrir o cofre',
    'You disarmed it from the dashboard.' => 'Você o desarmou pelo painel.',
    'Their previous address is still in use.' => 'O endereço anterior continua em uso.',
    '{name} declined to be your {role}, so a required contact is missing.' => '{name} recusou o papel de {role}, então falta um contato necessário.',
    'Your switch has been disarmed, because it needs this role filled.' => 'Seu interruptor foi desarmado, porque este papel precisa estar preenchido.',

    // Engine: checklist
    'The scheduler is running' => 'O agendador está funcionando',
    'No run has been recorded yet. Add the crontab line shown in Settings.' => 'Nenhuma execução foi registrada ainda. Adicione a linha do crontab mostrada em Configurações.',
    'The last run was {when}. Check the crontab line shown in Settings.' => 'A última execução foi {when}. Confira a linha do crontab mostrada em Configurações.',
    'The data folder cannot be read from the web' => 'A pasta de dados não pode ser lida pela web',
    'Files in the data folder can be opened in a browser. See "Protecting the data folder" in the README.' => 'Os arquivos da pasta de dados podem ser abertos em um navegador. Veja “Protecting the data folder” no README.',
    'The server could not test this by itself. Run the check from Settings.' => 'O servidor não conseguiu testar isso sozinho. Execute a verificação em Configurações.',
    'Your next of kin has confirmed' => 'Sua pessoa mais próxima confirmou',
    'Add your next of kin.' => 'Adicione sua pessoa mais próxima.',
    'Every message says where things are' => 'Cada mensagem diz onde estão as coisas',
    'Still holding starter text in square brackets: {names}.' => 'Ainda contêm texto de exemplo entre colchetes: {names}.',
    'Checked once your contacts have confirmed.' => 'Será verificado quando seus contatos confirmarem.',
    'Each contact has been given what they need' => 'Cada contato recebeu o que precisa',
    'Not ticked yet: {names}.' => 'Ainda sem marcação: {names}.',
    'A second email address for reminders' => 'Um segundo endereço de e-mail para os lembretes',
    'Recommended: an address with a different provider, in case your main one fails. Add it in Settings.' => 'Recomendado: um endereço de outro provedor, caso o principal falhe. Adicione-o em Configurações.',
    '{name} declined. Choose someone else.' => '{name} recusou. Escolha outra pessoa.',
    'Send {name} their confirmation email.' => 'Envie a {name} o e-mail de confirmação.',
    'Waiting for {name} to confirm. The email went {when}.' => 'Aguardando a confirmação de {name}. O e-mail foi enviado {when}.',

    // Engine: warnings
    '{name}.json was damaged and the last good copy is in use. Check what changed recently, then save any setting to rewrite the file.' => '{name}.json estava danificado e a última cópia válida está em uso. Confira o que mudou recentemente e depois salve qualquer configuração para reescrever o arquivo.',
    'The scheduler has never run. Nothing will be sent until it does.' => 'O agendador nunca foi executado. Nada será enviado até que seja.',
    'The scheduler last ran {when}. While it is stopped, the switch cannot send anything.' => 'O agendador foi executado pela última vez {when}. Enquanto estiver parado, o interruptor não consegue enviar nada.',
    'This node has not heard from any other node for {duration}. Serious steps are delayed while it is cut off.' => 'Este nó não recebe notícias de nenhum outro nó há {duration}. As etapas importantes ficam adiadas enquanto ele estiver isolado.',
    'This node has not heard from any other node yet. Serious steps are delayed while it is cut off.' => 'Este nó ainda não recebeu notícias de nenhum outro nó. As etapas importantes ficam adiadas enquanto ele estiver isolado.',
    'The last email could not be sent ({when}): {error}' => 'Não foi possível enviar o último e-mail ({when}): {error}',
    'notification' => 'notificação',
    'unknown error' => 'erro desconhecido',
    'The {label} channel failed {when}: {error}' => 'O canal “{label}” falhou {when}: {error}',
    'Files in the data folder can be opened in a browser.' => 'Os arquivos da pasta de dados podem ser abertos em um navegador.',
    'The server could not check whether the data folder is blocked from the web.' => 'O servidor não conseguiu verificar se a pasta de dados está bloqueada para a web.',
    'A required contact is missing or unconfirmed.' => 'Falta um contato necessário ou ele ainda não confirmou.',
    '{name} declined and will not be contacted.' => '{name} recusou e não receberá mensagens.',
    '{name} has not confirmed, so will not be contacted.' => '{name} não confirmou, por isso não receberá mensagens.',
    '{name} has not confirmed their new address. The old one is still in use.' => '{name} não confirmou o novo endereço. O antigo continua em uso.',
    '{name} has not answered their yearly reconfirmation. Check their address still works.' => '{name} não respondeu à reconfirmação anual. Verifique se o endereço ainda funciona.',
    'The message for {name} still holds starter text in square brackets.' => 'A mensagem para {name} ainda contém texto de exemplo entre colchetes.',
    'Your yearly review is due: confirm each contact still has what they need.' => 'Está na hora da revisão anual: confirme que cada contato ainda tem o que precisa.',
    'This install is not using HTTPS. Links in its emails can be read in transit.' => 'Esta instalação não usa HTTPS. Os links dos e-mails dela podem ser lidos no caminho.',

    // Engine: message previews
    'When you miss a check-in and its reminders' => 'Quando você deixa de confirmar a presença e de responder aos lembretes',
    'When the switch fires' => 'Quando o interruptor é acionado',

    // Technical helper
    'technical helper' => 'auxiliar técnico',
    'Technical helper' => 'Auxiliar técnico',
    'Technical helper (optional)' => 'Auxiliar técnico (opcional)',
    'Someone who can keep your servers, accounts or projects running.' => 'Alguém que possa manter em funcionamento seus servidores, contas ou projetos.',
];
