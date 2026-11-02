<?php
/** Indonesian wording for the history log: entries written from now on, and the words that fill messages in. */
declare(strict_types=1);

return [
    // Check-in methods (also stored as the way the last check-in came in)
    'the dashboard' => 'dasbor',
    'the emailed link' => 'tautan dari email',
    'another node' => 'node lain',
    'arming the switch' => 'pengaktifan alarm',
    'pausing the switch' => 'penjedaan sakelar',
    'ending the pause' => 'berakhirnya jeda',

    // Cycle
    'Weekly check-in email sent.' => 'Email check-in mingguan dikirim.',
    'Reminder {number} sent to the owner.' => 'Pengingat {number} dikirim kepada pemilik.',
    'No check-in after 72 hours. The next of kin has been asked.' => 'Tidak ada check-in setelah 72 jam. Kerabat terdekat telah ditanya.',
    'No check-in after 72 hours. The next of kin could not be emailed; the 72-hour wait has started anyway.' => 'Tidak ada check-in setelah 72 jam. Email kepada kerabat terdekat tidak dapat dikirim; masa tunggu 72 jam tetap dimulai.',
    'The next of kin was reminded.' => 'Kerabat terdekat telah diingatkan.',
    'The next of kin answered "Something\'s wrong". The switch has fired.' => 'Kerabat terdekat menjawab "Ada yang tidak beres". Sakelar telah dijalankan.',
    'Neither the owner nor the next of kin confirmed all is well. The switch has fired.' => 'Baik pemilik maupun kerabat terdekat tidak memastikan bahwa semuanya baik-baik saja. Sakelar telah dijalankan.',
    '{role} ({name}) was sent their message.' => 'Pesan dikirim kepada {role} ({name}).',
    '{name} was told it was a false alarm.' => '{name} diberi tahu bahwa itu alarm palsu.',
    'The next of kin answered "All fine". Nobody else was contacted.' => 'Kerabat terdekat menjawab "Semua baik-baik saja". Tidak ada orang lain yang dihubungi.',
    'The next of kin answered "All fine" ({via}). Nobody else was contacted.' => 'Kerabat terdekat menjawab "Semua baik-baik saja" ({via}). Tidak ada orang lain yang dihubungi.',
    'The weekly check-in email was sent by another node.' => 'Email check-in mingguan dikirim oleh node lain.',
    'Another node fired the switch.' => 'Node lain menjalankan sakelar.',
    'Checked in by {via}.' => 'Check-in melalui {via}.',
    'Checked in by another node ({via}).' => 'Check-in melalui node lain ({via}).',
    'False-alarm notices sent to {names}.' => 'Pemberitahuan alarm palsu dikirim kepada {names}.',

    // Switch
    'The pause ended. The switch is active again.' => 'Jeda berakhir. Sakelar aktif kembali.',
    'The switch was armed.' => 'Alarm diaktifkan.',
    'The switch was disarmed. {reason}' => 'Alarm dinonaktifkan. {reason}',
    'The switch was paused for {duration}.' => 'Sakelar dijeda selama {duration}.',
    'The pause was ended early.' => 'Jeda diakhiri lebih awal.',
    'Yearly review completed.' => 'Peninjauan tahunan selesai.',
    'The data folder can be read from the web.' => 'Folder data dapat dibaca dari web.',

    // Contacts
    '{name} confirmed as {role}.' => '{name} mengonfirmasi sebagai {role}.',
    'The new address for {name} was declined. The old one is still in use.' => 'Alamat baru untuk {name} ditolak. Alamat lama masih dipakai.',
    '{name} declined to be {role}.' => '{name} menolak menjadi {role}.',
    '{name} ({role}) was deleted.' => '{name} ({role}) dihapus.',
    '{name} was added as {role}.' => '{name} ditambahkan sebagai {role}.',

    // Sending
    'Notified {where}' => 'Notifikasi dikirim ke {where}',
    'Could not notify {where}.' => 'Tidak dapat mengirim notifikasi ke {where}.',
    'Sent "{subject}" to {to}' => '"{subject}" dikirim ke {to}',
    'Could not send "{subject}" to {to}' => '"{subject}" tidak dapat dikirim ke {to}',

    // Settings and sign-in
    'Backup email address confirmed: {address}.' => 'Alamat email cadangan dikonfirmasi: {address}.',
    'Main email address confirmed: {address}.' => 'Alamat email utama dikonfirmasi: {address}.',
    'A wrong password was entered at sign-in.' => 'Kata sandi yang salah dimasukkan saat masuk.',

    // Message placeholders (contact page)
    'The name of the person receiving the message' => 'Nama orang yang menerima pesan',
    'Whole days since your last check-in' => 'Jumlah hari penuh sejak check-in terakhir Anda',
    'The date of your last check-in' => 'Tanggal check-in terakhir Anda',
    'Next of kin: name, email, phone' => 'Kerabat terdekat: nama, email, telepon',
    'Technical contact: name, email, phone' => 'Kontak teknis: nama, email, telepon',
    'Everyone else on your list, one per line' => 'Semua orang lain dalam daftar Anda, satu per baris',

    // Settings, cluster, install, storage
    'The weekly check-in time was changed to {day} at {time} ({tz}).' => 'Waktu check-in mingguan diubah menjadi {day} pukul {time} ({tz}).',
    'Mail settings were changed.' => 'Pengaturan email diubah.',
    'The owner password was changed.' => 'Kata sandi pemilik diubah.',
    'A node was added to the cluster.' => 'Sebuah node ditambahkan ke klaster.',
    'A node public address was changed.' => 'Alamat publik sebuah node diubah.',
    'A node was removed from the cluster.' => 'Sebuah node dihapus dari klaster.',
    'The cluster settings were changed.' => 'Pengaturan klaster diubah.',
    'Death Plan Switch {version} was installed.' => 'Death Plan Switch {version} telah diinstal.',
    'Vault API error: {detail}' => 'Kesalahan API brankas: {detail}',
    '{name}.json was unreadable. The last good copy was used instead.' => '{name}.json tidak dapat dibaca. Salinan terakhir yang baik dipakai sebagai gantinya.',
    'another node ({via})' => 'node lain ({via})',
    '{name} was asked whether {owner} is all right.' => '{name} ditanya apakah {owner} baik-baik saja.',
    '{name} could not be emailed.' => '{name} tidak dapat dikirimi email.',
    '{name} answered "Something\'s wrong". Nothing is sent early; the others are still asked in turn.' => '{name} menjawab "Ada yang tidak beres". Tidak ada yang dikirim lebih awal; yang lain tetap ditanya secara bergiliran.',
    '{name} answered "All fine". Nobody else will be asked.' => '{name} menjawab "Semua baik-baik saja". Tidak ada orang lain yang akan ditanya.',
    'The settings for the trusted people were changed.' => 'Pengaturan orang tepercaya diubah.',
];
