<?php
/** Turkish wording for the core classes: engine warnings and checklist, web errors, setup checks, contact labels. */
declare(strict_types=1);

return [
    // Contact labels
    'next of kin' => 'en yakın kişi',
    'technical 2IC' => 'teknik yardımcı',
    'legal contact' => 'hukuki irtibat kişisi',
    'contact' => 'kişi',
    'Next of kin' => 'En yakın kişi',
    'Technical 2IC' => 'Teknik yardımcı',
    'Legal contact' => 'Hukuki irtibat kişisi',
    'Other contact' => 'Diğer kişi',
    'Contact' => 'Kişi',
    '(none listed)' => '(kimse eklenmemiş)',

    // Web
    'The data folder needs attention' => 'Veri klasörü ilgi gerektiriyor',
    'Nothing has been changed. See "If a data file is damaged" in the README.' => 'Hiçbir şey değiştirilmedi. README içindeki “If a data file is damaged” bölümüne bak.',
    'Too many wrong passwords. Try again {when}.' => 'Çok fazla yanlış parola girildi. {when} yeniden dene.',
    'That password is not right.' => 'Bu parola doğru değil.',
    'That form had expired. Go back, reload the page and try again.' => 'Bu formun süresi dolmuş. Geri dön, sayfayı yenile ve yeniden dene.',

    // Setup
    'PHP {version} or newer' => 'PHP {version} veya daha yenisi',
    'This server runs PHP {version}. Ask your host to switch to a current version.' => 'Bu sunucuda PHP {version} çalışıyor. Barındırma hizmetinden güncel bir sürüme geçmesini iste.',
    'The OpenSSL extension, for encrypted mail' => 'OpenSSL eklentisi, şifreli posta için',
    'Ask your host to enable the PHP openssl extension.' => 'Barındırma hizmetinden PHP openssl eklentisini etkinleştirmesini iste.',
    'The mbstring extension' => 'mbstring eklentisi',
    'Ask your host to enable the PHP mbstring extension.' => 'Barındırma hizmetinden PHP mbstring eklentisini etkinleştirmesini iste.',
    'The data folder can be written to' => 'Veri klasörüne yazılabiliyor',
    'PHP cannot write to {dir}. Give that folder to the user the web pages run as' => 'PHP, {dir} klasörüne yazamıyor. Bu klasörü web sayfalarının çalıştığı kullanıcıya ver',
    'Choose a password of at least {min} characters.' => 'En az {min} karakterden oluşan bir parola seç.',
    'That password is too long.' => 'Bu parola çok uzun.',
    'Enter the mail server host, for example smtp.example.com.' => 'Posta sunucusu adresini gir, örneğin smtp.example.com.',
    'Enter the mail server port. 587 is the usual one.' => 'Posta sunucusu portunu gir. Genellikle 587’dir.',
    'Enter the address the switch sends from. Your mail service must allow it.' => 'Anahtarın e-posta göndereceği adresi gir. Posta hizmetin buna izin vermeli.',
    'There is no code waiting. Send a new one.' => 'Girilmeyi bekleyen bir kod yok. Yeni bir kod gönder.',
    'That code has expired. Send a new one.' => 'Bu kodun süresi dolmuş. Yeni bir kod gönder.',
    'That code is not right. Check the email and try again.' => 'Bu kod doğru değil. E-postayı kontrol et ve yeniden dene.',

    // Engine: actions
    'Open the vault' => 'Kasayı aç',
    'You disarmed it from the dashboard.' => 'Anahtarı kontrol panelinden devre dışı bıraktın.',
    'Their previous address is still in use.' => 'Önceki adresi kullanılmaya devam ediyor.',
    '{name} declined to be your {role}, so a required contact is missing.' => '{name} şu rolü reddetti: {role}. Bu yüzden gerekli bir kişi eksik.',
    'Your switch has been disarmed, because it needs this role filled.' => 'Anahtarın devre dışı bırakıldı; çünkü bu rolün dolu olması gerekiyor.',

    // Engine: checklist
    'The scheduler is running' => 'Zamanlayıcı çalışıyor',
    'No run has been recorded yet. Add the crontab line shown in Settings.' => 'Henüz kayıtlı bir çalıştırma yok. Ayarlar’da gösterilen crontab satırını ekle.',
    'The last run was {when}. Check the crontab line shown in Settings.' => 'Son çalıştırma şu zamandaydı: {when}. Ayarlar’da gösterilen crontab satırını kontrol et.',
    'The data folder cannot be read from the web' => 'Veri klasörü internetten okunamıyor',
    'Files in the data folder can be opened in a browser. See "Protecting the data folder" in the README.' => 'Veri klasöründeki dosyalar tarayıcıda açılabiliyor. README içindeki “Protecting the data folder” bölümüne bak.',
    'The server could not test this by itself. Run the check from Settings.' => 'Sunucu bunu kendi başına sınayamadı. Kontrolü Ayarlar’dan çalıştır.',
    'Your next of kin has confirmed' => 'En yakın kişin onay verdi',
    'Add your next of kin.' => 'En yakın kişini ekle.',
    'Your technical 2IC has confirmed' => 'Teknik yardımcın onay verdi',
    'Add the person who would look after your clients.' => 'Müşterilerinle ilgilenecek kişiyi ekle.',
    'Every message says where things are' => 'Her mesaj şeylerin nerede olduğunu söylüyor',
    'Still holding starter text in square brackets: {names}.' => 'Hâlâ köşeli parantez içinde örnek metin var: {names}.',
    'Checked once your contacts have confirmed.' => 'Kişilerin onay verdikten sonra kontrol edilir.',
    'Each contact has been given what they need' => 'Her kişiye gereken şeyler verildi',
    'Not ticked yet: {names}.' => 'Henüz işaretlenmedi: {names}.',
    'A second email address for reminders' => 'Hatırlatmalar için ikinci bir e-posta adresi',
    'Recommended: an address with a different provider, in case your main one fails. Add it in Settings.' => 'Önerilir: ana adresin arızalanırsa diye farklı bir sağlayıcıdan bir adres. Ayarlar’dan ekleyebilirsin.',
    '{name} declined. Choose someone else.' => '{name} reddetti. Başka birini seç.',
    'Send {name} their confirmation email.' => '{name} kişisine onay e-postasını gönder.',
    'Waiting for {name} to confirm. The email went {when}.' => 'Onay bekleniyor: {name}. E-posta şu zamanda gönderildi: {when}.',

    // Engine: warnings
    '{name}.json was damaged and the last good copy is in use. Check what changed recently, then save any setting to rewrite the file.' => '{name}.json hasarlıydı ve son sağlam kopya kullanılıyor. Yakın zamanda neyin değiştiğine bak, sonra dosyayı yeniden yazmak için herhangi bir ayarı kaydet.',
    'The scheduler has never run. Nothing will be sent until it does.' => 'Zamanlayıcı hiç çalışmadı. Çalışana kadar hiçbir şey gönderilmeyecek.',
    'The scheduler last ran {when}. While it is stopped, the switch cannot send anything.' => 'Zamanlayıcı en son şu zamanda çalıştı: {when}. Durduğu sürece anahtar hiçbir şey gönderemez.',
    'This node has not heard from any other node for {duration}. Serious steps are delayed while it is cut off.' => 'Bu düğüm {duration} süredir başka hiçbir düğümden haber alamadı. Kopuk olduğu sürece önemli adımlar geciktirilir.',
    'This node has not heard from any other node yet. Serious steps are delayed while it is cut off.' => 'Bu düğüm henüz başka hiçbir düğümden haber almadı. Kopuk olduğu sürece önemli adımlar geciktirilir.',
    'The last email could not be sent ({when}): {error}' => 'Son e-posta gönderilemedi ({when}): {error}',
    'notification' => 'bildirim',
    'unknown error' => 'bilinmeyen hata',
    'The {label} channel failed {when}: {error}' => '“{label}” kanalı başarısız oldu ({when}): {error}',
    'Files in the data folder can be opened in a browser.' => 'Veri klasöründeki dosyalar tarayıcıda açılabiliyor.',
    'The server could not check whether the data folder is blocked from the web.' => 'Sunucu, veri klasörünün internete kapalı olup olmadığını kontrol edemedi.',
    'A required contact is missing or unconfirmed.' => 'Gerekli bir kişi eksik ya da onaylanmamış.',
    '{name} declined and will not be contacted.' => '{name} reddetti ve kendisine ulaşılmayacak.',
    '{name} has not confirmed, so will not be contacted.' => '{name} onay vermedi, bu yüzden kendisine ulaşılmayacak.',
    '{name} has not confirmed their new address. The old one is still in use.' => '{name} için yeni adres onaylanmadı. Eski adres kullanılmaya devam ediyor.',
    '{name} has not answered their yearly reconfirmation. Check their address still works.' => '{name} yıllık yeniden onay isteğine yanıt vermedi. Adresin hâlâ çalışıp çalışmadığını kontrol et.',
    'The message for {name} still holds starter text in square brackets.' => '{name} için yazılan mesajda hâlâ köşeli parantez içinde örnek metin var.',
    'Your yearly review is due: confirm each contact still has what they need.' => 'Yıllık gözden geçirme zamanı geldi: her kişinin ihtiyaç duyduğu şeylere hâlâ sahip olduğunu doğrula.',
    'This install is not using HTTPS. Links in its emails can be read in transit.' => 'Bu kurulum HTTPS kullanmıyor. E-postalarındaki bağlantılar yolda okunabilir.',

    // Engine: message previews
    'When you miss a check-in and its reminders' => 'Bir yoklamayı ve hatırlatmalarını kaçırdığında',
    'When the switch fires' => 'Anahtar devreye girdiğinde',
];
