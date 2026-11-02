<?php
declare(strict_types=1);

// Check-in, confirm, respond, history, public page and the vault pages.
// Keys are the English source strings. Pages for family avoid gendered forms.
return [
    // Check-in (owner)
    'Check in: Death Plan Switch' => 'Yoklama: Death Plan Switch',
    'Checked in' => 'Yoklama verildi',
    'Your switch had fired. Everyone who was contacted has now been told it was a false alarm: {names}.' => 'Anahtarın devreye girmişti. İletişime geçilen herkese bunun yanlış alarm olduğu bildirildi: {names}.',
    'Your switch had fired. Everyone who was contacted has now been told it was a false alarm.' => 'Anahtarın devreye girmişti. İletişime geçilen herkese bunun yanlış alarm olduğu bildirildi.',
    'Your contacts have read their messages, so any pointer in them has been seen. {link} to review what needs changing.' => 'Kişilerin mesajlarını okudu; bu yüzden içlerindeki yönlendirmeler görülmüş olabilir. Nelerin değişmesi gerektiğine bakmak için {link}.',
    'Sign in' => 'Giriş yap',
    '{names} had been asked whether you are all right, and has now been told it was a false alarm. Nobody else was contacted.' => 'İyi olup olmadığın şu kişiye sorulmuştu: {names}. Yanlış alarm olduğu kendisine bildirildi. Başka kimseyle iletişime geçilmedi.',
    '{names} had been asked whether you are all right, and have now been told it was a false alarm. Nobody else was contacted.' => 'İyi olup olmadığın şu kişilere sorulmuştu: {names}. Yanlış alarm olduğu kendilerine bildirildi. Başka kimseyle iletişime geçilmedi.',
    'Thank you. Nobody was contacted.' => 'Teşekkürler. Kimseyle iletişime geçilmedi.',
    'Your switch is paused until {date}.' => 'Anahtarın şu tarihe kadar duraklatıldı: {date}.',
    'Your next check-in email arrives on {date} at {time}.' => 'Bir sonraki yoklama e-postası şu zamanda gelecek: {date}, saat {time}.',
    'Are you there, {name}?' => '{name}, orada mısın?',
    'owner' => 'sahip',
    'Press the button to check in. Opening this page is not enough.' => 'Yoklama vermek için düğmeye bas. Bu sayfayı açmak yetmez.',
    'I am here: check in' => 'Buradayım: yoklama ver',
    'This link is no longer active' => 'Bu bağlantı artık geçerli değil',
    'Each check-in link works once, and for 14 days. If you have a newer email from Death Plan Switch, use the link in that one.' => 'Her yoklama bağlantısı yalnızca bir kez ve 14 gün boyunca çalışır. Death Plan Switch’ten gelen daha yeni bir e-postan varsa onun içindeki bağlantıyı kullan.',
    'You can also {link} and check in from the dashboard.' => 'Ayrıca {link} ve yoklamayı kontrol panelinden verebilirsin.',
    'sign in' => 'giriş yap',

    // Confirm (contact)
    'Death Plan Switch' => 'Death Plan Switch',
    'Confirmed' => 'Onaylandı',
    'Thank you, {name}. {owner} has been told. There is nothing else to do.' => 'Teşekkür ederiz, {name}. Sizi belirten kişiye ({owner}) haber verildi. Yapmanız gereken başka bir şey yok.',
    'If {owner} ever stops checking in, you will hear from the same email address that sent you this request. Add it to your contacts so it is not mistaken for spam.' => 'Sizi belirten kişi ({owner}) yoklama vermeyi bırakırsa bu isteği gönderen e-posta adresinden size haber verilir. Spam sanılmaması için bu adresi kişilerinize ekleyin.',
    'Done' => 'Tamam',
    "You will not be contacted by {owner}'s Death Plan Switch. {owner} has been told." => 'Bu kişinin ({owner}) Death Plan Switch’i sizinle iletişime geçmeyecek. Kendisine bunu bildiriyoruz.',
    "Still happy to be {owner}'s {role}?" => 'Şu kişi ({owner}) için hâlâ şu rolü üstlenmeyi kabul ediyor musunuz: {role}?',
    '{owner} has listed you as their {role}' => '{owner} sizi şu rolde belirtti: {role}',
    '{owner} uses Death Plan Switch, a small app on their own server. Every week it asks {owner} to check in. If they stop, it emails the people they have chosen.' => '{owner} adlı kişi, kendi sunucusunda çalışan küçük bir uygulama olan Death Plan Switch’i kullanıyor. Uygulama her hafta iyi olup olmadığının onaylanmasını ister. Onay gelmezse seçilen kişilere e-posta yazar.',
    'As next of kin you would be asked first. If {owner} misses a check-in and three days of reminders, you would get one email asking whether everything is all right, with two buttons to answer.' => 'En yakın kişi olarak size ilk siz sorulur. {owner} yoklama vermez ve üç günlük hatırlatmalar da yanıtsız kalırsa size, her şeyin yolunda olup olmadığını soran ve iki yanıt düğmesi içeren bir e-posta gelir.',
    'If {owner} stops checking in and their next of kin cannot confirm they are well, you would get one email telling you what {owner} needs you to do.' => '{owner} yoklama vermeyi bırakır ve en yakın kişisi de her şeyin yolunda olduğunu onaylayamazsa size, {owner} kişisinin sizden ne istediğini anlatan bir e-posta gelir.',
    'Confirming tells {owner} that {address} reaches you, and that you are willing.' => 'Onayladığınızda {owner} kişisine, {address} adresinin size ulaştığı ve kabul ettiğiniz bildirilir.',
    'Yes, I confirm' => 'Evet, onaylıyorum',
    'No, leave me out' => 'Hayır, beni dışarıda tutun',
    'It has either been used already or has expired. If you meant to confirm, ask the person who listed you to send a new request.' => 'Bağlantı daha önce kullanılmış ya da süresi dolmuş. Onaylamak istediyseniz sizi belirten kişiden yeni bir istek göndermesini isteyin.',

    // Respond (next of kin)
    'All fine' => 'Her şey yolunda',
    'You have seen or spoken to {owner} and they are all right. Nobody else is contacted, and {owner} is told you answered.' => 'Şu kişiyi ({owner}) gördünüz ya da onunla konuştunuz ve iyi. Başka kimseyle iletişime geçilmez; kendisine yanıt verdiğiniz bildirilir.',
    "Something's wrong" => 'Bir sorun var',
    '{owner} cannot run their business at the moment.' => 'Bu kişi ({owner}) şu anda işleriyle ilgilenemiyor.',
    'These people are sent their instructions straight away: {names}.' => 'Bu kişilere yönergeleri hemen gönderilir: {names}.',
    'The people they chose are sent their instructions straight away.' => 'Seçilen kişilere yönergeleri hemen gönderilir.',
    'You are sent yours too.' => 'Size de sizinki gönderilir.',
    'Thank you' => 'Teşekkür ederiz',
    'Your answer has been recorded. Nobody else has been contacted, and {owner} has been told that you answered.' => 'Yanıtınız kaydedildi. Başka kimseyle iletişime geçilmedi; {owner} kişisine yanıt verdiğiniz bildirilecek.',
    'Your answer has been recorded.' => 'Yanıtınız kaydedildi.',
    'These people have been sent their instructions: {names}.' => 'Bu kişilere yönergeleri gönderildi: {names}.',
    'The people {owner} chose have been sent their instructions.' => 'Şu kişinin ({owner}) seçtiği kişilere yönergeleri gönderildi.',
    'You have been emailed a message from {owner} as well, with what to do next.' => 'Size ayrıca şu kişiden ({owner}) bir mesaj gönderdik; bundan sonra ne yapılacağını anlatıyor.',
    'Is {owner} all right?' => 'Şu kişi ({owner}) iyi mi?',
    '{owner} has not answered their weekly check-in or its reminders. You are their next of kin, so you are asked first. Nothing has been sent to anyone else.' => 'Bu kişi ({owner}) haftalık yoklamaya ve hatırlatmalara yanıt vermedi. Siz en yakın kişisiniz, bu yüzden ilk olarak size soruyoruz. Başka kimseye hiçbir şey gönderilmedi.',
    'Not sure yet? You can come back to this page. If neither you nor {owner} answers by the time given in the email, the others are sent their instructions.' => 'Henüz emin değil misiniz? Bu sayfaya geri dönebilirsiniz. E-postada belirtilen zamana kadar ne siz ne de {owner} yanıt verirse diğer kişilere yönergeleri gönderilir.',
    'It has either been used already, or the check it belonged to has been settled. You do not need to do anything.' => 'Bağlantı daha önce kullanılmış ya da ait olduğu konu sonuçlandırılmış. Yapmanız gereken bir şey yok.',

    // History
    'History: Death Plan Switch' => 'Geçmiş: Death Plan Switch',
    'History' => 'Geçmiş',
    'Everything the switch has done, newest first. Scheduler runs that found nothing to do are not listed.' => 'Anahtarın yaptığı her şey, en yeniden başlayarak. Yapacak iş bulamayan zamanlayıcı çalıştırmaları listelenmez.',
    'Nothing has happened yet.' => 'Henüz hiçbir şey olmadı.',

    // Public page
    'This switch is active.' => 'Bu anahtar etkin.',
    'This switch is not active.' => 'Bu anahtar etkin değil.',
    'This switch is armed.' => 'Bu anahtar etkinleştirilmiş.',
    'Triggered' => 'Devreye girdi',
    'This switch has been triggered.' => 'Bu anahtar devreye girdi.',
    'Open the vault' => 'Kasayı aç',
    'Armed' => 'Etkin',
    'Not active' => 'Etkin değil',
    'Owner sign-in' => 'Sahip girişi',

    // Vault (owner and recipient)
    'That recipient has no contact with an email address, so no invitation was sent.' => 'Bu alıcının e-posta adresi olan bir kişisi yok, bu yüzden davet gönderilmedi.',
    'Invitation sent to {name}.' => 'Davet gönderildi: {name}.',
    'The invitation could not be sent. Check the mail settings.' => 'Davet gönderilemedi. Posta ayarlarını kontrol et.',
    'Vault: Death Plan Switch' => 'Kasa: Death Plan Switch',
    'Vault' => 'Kasa',
    "What you leave for the people you choose, locked in your browser. Only a recipient's code or password opens it, and only after the switch has fired." => 'Seçtiğin kişiler için bıraktıkların, tarayıcında kilitlenir. Yalnızca alıcının kodu ya da parolası bunu açar; o da ancak anahtar devreye girdikten sonra.',
    'The vault needs JavaScript. Turn it on for this page.' => 'Kasa için JavaScript gerekir. Bu sayfa için aç.',
    'This page needs JavaScript, because everything is decrypted in your browser.' => 'Bu sayfa için JavaScript gerekir; çünkü her şeyin şifresi tarayıcında çözülür.',
];
