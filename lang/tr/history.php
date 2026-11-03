<?php
/** Turkish wording for the history log: entries written from now on, and the words that fill messages in. */
declare(strict_types=1);

return [
    // Check-in methods (also stored as the way the last check-in came in)
    'the dashboard' => 'kontrol paneli',
    'the emailed link' => 'e-postadaki bağlantı',
    'another node' => 'başka bir düğüm',
    'arming the switch' => 'anahtarın kurulması',
    'pausing the switch' => 'anahtarın duraklatılması',
    'ending the pause' => 'duraklatmanın bitirilmesi',

    // Cycle
    'Weekly check-in email sent.' => 'Haftalık durum bildirimi e-postası gönderildi.',
    'Reminder {number} sent to the owner.' => 'Sahibine {number}. hatırlatma gönderildi.',
    'No check-in after 72 hours. The next of kin has been asked.' => '72 saat içinde durum bildirimi gelmedi. En yakın kişiye soruldu.',
    'No check-in after 72 hours. The next of kin could not be emailed; the 72-hour wait has started anyway.' => '72 saat içinde durum bildirimi gelmedi. En yakın kişiye e-posta gönderilemedi; 72 saatlik bekleme yine de başladı.',
    'The next of kin was reminded.' => 'En yakın kişiye hatırlatma gönderildi.',
    'The next of kin answered "Something\'s wrong". The switch has fired.' => 'En yakın kişi “Bir sorun var” yanıtını verdi. Anahtar devreye girdi.',
    'Neither the owner nor the next of kin confirmed all is well. The switch has fired.' => 'Ne sahibi ne de en yakın kişi her şeyin yolunda olduğunu onayladı. Anahtar devreye girdi.',
    '{role} ({name}) was sent their message.' => 'Mesaj gönderildi: {role} ({name}).',
    '{name} was told it was a false alarm.' => 'Yanlış alarm bilgisi gönderildi: {name}.',
    'The next of kin answered "All fine". Nobody else was contacted.' => 'En yakın kişi “Her şey yolunda” yanıtını verdi. Başka kimseye ulaşılmadı.',
    'The next of kin answered "All fine" ({via}). Nobody else was contacted.' => 'En yakın kişi “Her şey yolunda” yanıtını verdi ({via}). Başka kimseye ulaşılmadı.',
    'The weekly check-in email was sent by another node.' => 'Haftalık durum bildirimi e-postasını başka bir düğüm gönderdi.',
    'Another node fired the switch.' => 'Anahtarı başka bir düğüm devreye aldı.',
    'Checked in by {via}.' => 'Durum bildirildi (yöntem: {via}).',
    'Checked in by another node ({via}).' => 'Durum bildirimi başka bir düğümde verildi ({via}).',
    'False-alarm notices sent to {names}.' => 'Yanlış alarm bilgisi şu kişilere gönderildi: {names}.',

    // Switch
    'The pause ended. The switch is active again.' => 'Duraklatma sona erdi. Anahtar yeniden çalışıyor.',
    'The switch was armed.' => 'Anahtar kuruldu.',
    'The switch was disarmed. {reason}' => 'Anahtar devre dışı bırakıldı. {reason}',
    'The switch was paused for {duration}.' => 'Anahtar {duration} süreyle duraklatıldı.',
    'The pause was ended early.' => 'Duraklatma erken bitirildi.',
    'Yearly review completed.' => 'Yıllık gözden geçirme tamamlandı.',
    'The data folder can be read from the web.' => 'Veri klasörü internetten okunabiliyor.',

    // Contacts
    '{name} confirmed as {role}.' => '{name}: rol onaylandı – {role}.',
    'The new address for {name} was declined. The old one is still in use.' => '{name} için yeni adres reddedildi. Eski adres kullanılmaya devam ediyor.',
    '{name} declined to be {role}.' => '{name}: rol reddedildi – {role}.',
    '{name} ({role}) was deleted.' => 'Kişi silindi: {name} ({role}).',
    '{name} was added as {role}.' => 'Kişi eklendi: {name} ({role}).',

    // Sending
    'Notified {where}' => 'Bildirim gönderildi: {where}',
    'Could not notify {where}.' => 'Bildirim gönderilemedi: {where}.',
    'Sent "{subject}" to {to}' => '“{subject}” gönderildi: {to}',
    'Could not send "{subject}" to {to}' => '“{subject}” gönderilemedi: {to}',

    // Settings and sign-in
    'Backup email address confirmed: {address}.' => 'Yedek e-posta adresi onaylandı: {address}.',
    'Main email address confirmed: {address}.' => 'Ana e-posta adresi onaylandı: {address}.',
    'A wrong password was entered at sign-in.' => 'Girişte yanlış parola girildi.',

    // Message placeholders (contact page)
    'The name of the person receiving the message' => 'Mesajı alan kişinin adı',
    'Whole days since your last check-in' => 'Son durum bildiriminden bu yana geçen tam gün sayısı',
    'The date of your last check-in' => 'Son durum bildiriminin tarihi',
    'Next of kin: name, email, phone' => 'En yakın kişi: ad, e-posta, telefon',
    'Technical contact: name, email, phone' => 'Teknik kişi: ad, e-posta, telefon',
    'Everyone else on your list, one per line' => 'Listendeki diğer herkes, her satırda bir kişi',

    // Settings, cluster, install, storage
    'The weekly check-in time was changed to {day} at {time} ({tz}).' => 'Haftalık durum bildirimi zamanı şu şekilde değiştirildi: {day}, {time} ({tz}).',
    'Mail settings were changed.' => 'Posta ayarları değiştirildi.',
    'The owner password was changed.' => 'Sahip parolası değiştirildi.',
    'A node was added to the cluster.' => 'Kümeye bir düğüm eklendi.',
    'A node public address was changed.' => 'Bir düğümün herkese açık adresi değiştirildi.',
    'A node was removed from the cluster.' => 'Kümeden bir düğüm çıkarıldı.',
    'The cluster settings were changed.' => 'Küme ayarları değiştirildi.',
    'Death Plan Switch {version} was installed.' => 'Death Plan Switch {version} kuruldu.',
    'Vault API error: {detail}' => 'Kasa API hatası: {detail}',
    '{name}.json was unreadable. The last good copy was used instead.' => '{name}.json dosyası okunamadı. Bunun yerine son sağlam kopya kullanıldı.',

    'another node ({via})' => 'başka bir düğüm ({via})',
    '{name} was asked whether {owner} is all right.' => 'Şu kişiye, {owner} kişisinin iyi olup olmadığı soruldu: {name}.',
    '{name} could not be emailed.' => 'E-posta gönderilemedi: {name}.',
    '{name} answered "Something\'s wrong". Nothing is sent early; the others are still asked in turn.' => '{name}: “Bir sorun var” yanıtı verildi. Erkenden bir şey gönderilmez; diğerlerine sırayla sorulmaya devam edilir.',
    '{name} answered "All fine". Nobody else will be asked.' => '{name}: “Her şey yolunda” yanıtı verildi. Başka kimseye sorulmayacak.',
    'The settings for the trusted people were changed.' => 'Güvenilir kişilerin ayarları değiştirildi.',

    'Could not send the message to the group channel {name}.' => '{name} grup kanalına mesaj gönderilemedi.',
    'Could not send part {part} of {parts} to the group channel {name}.' => '{name} grup kanalına {part}. bölüm ({parts} bölümden) gönderilemedi.',
    'No more attempts will be made.' => 'Başka deneme yapılmayacak.',
    'It will be tried again on the next run.' => 'Bir sonraki çalıştırmada yeniden denenecek.',
    'The message to the group channel {name} was sent by another node.' => '{name} grup kanalına mesajı başka bir düğüm gönderdi.',
    'The message was sent to the group channel {name} in {n} part.' => ['Mesaj, {name} grup kanalına {n} parça hâlinde gönderildi.', 'Mesaj, {name} grup kanalına {n} parça hâlinde gönderildi.'],
    'The group channel {name} was added.' => '{name} grup kanalı eklendi.',
    'The group channel {name} was deleted.' => '{name} grup kanalı silindi.',
    'Another node stopped sending the message to the group channel {name}, so this node goes on with it.' => 'Başka bir düğüm {name} grup kanalına mesaj göndermeyi bıraktı; bu yüzden gönderime bu düğüm devam ediyor.',
    'The group channel {name} was sent the correction.' => '{name} grup kanalına düzeltme gönderildi.',
    'Could not send the correction to the group channel {name}.' => '{name} grup kanalına düzeltme gönderilemedi.',
];
