<?php
/** Indonesian wording for the core classes: engine warnings and checklist, web errors, setup checks, contact labels. */
declare(strict_types=1);

return [
    // Contact labels
    'next of kin' => 'kerabat terdekat',
    'legal contact' => 'kontak hukum',
    'contact' => 'kontak',
    'Next of kin' => 'Kerabat terdekat',
    'Legal contact' => 'Kontak hukum',
    'Other contact' => 'Kontak lain',
    'Contact' => 'Kontak',
    '(none listed)' => '(tidak ada yang tercatat)',

    // Web
    'The data folder needs attention' => 'Folder data memerlukan perhatian',
    'Nothing has been changed. See "If a data file is damaged" in the README.' => 'Tidak ada yang diubah. Lihat "If a data file is damaged" di README.',
    'Too many wrong passwords. Try again {when}.' => 'Terlalu banyak kata sandi yang salah. Coba lagi {when}.',
    'That password is not right.' => 'Kata sandi itu tidak benar.',
    'That form had expired. Go back, reload the page and try again.' => 'Formulir itu sudah kedaluwarsa. Kembali, muat ulang halaman, lalu coba lagi.',

    // Setup
    'PHP {version} or newer' => 'PHP {version} atau yang lebih baru',
    'This server runs PHP {version}. Ask your host to switch to a current version.' => 'Server ini menjalankan PHP {version}. Minta penyedia hosting Anda beralih ke versi yang lebih baru.',
    'The OpenSSL extension, for encrypted mail' => 'Ekstensi OpenSSL, untuk email terenkripsi',
    'Ask your host to enable the PHP openssl extension.' => 'Minta penyedia hosting Anda mengaktifkan ekstensi PHP openssl.',
    'The mbstring extension' => 'Ekstensi mbstring',
    'Ask your host to enable the PHP mbstring extension.' => 'Minta penyedia hosting Anda mengaktifkan ekstensi PHP mbstring.',
    'The data folder can be written to' => 'Folder data dapat ditulisi',
    'PHP cannot write to {dir}. Give that folder to the user the web pages run as' => 'PHP tidak dapat menulis ke {dir}. Berikan folder itu kepada pengguna yang menjalankan halaman web',
    'Choose a password of at least {min} characters.' => 'Pilih kata sandi minimal {min} karakter.',
    'That password is too long.' => 'Kata sandi itu terlalu panjang.',
    'Enter the mail server host, for example smtp.example.com.' => 'Masukkan host server email, misalnya smtp.example.com.',
    'Enter the mail server port. 587 is the usual one.' => 'Masukkan port server email. Yang umum adalah 587.',
    'Enter the address the switch sends from. Your mail service must allow it.' => 'Masukkan alamat pengirim sakelar. Layanan email Anda harus mengizinkannya.',
    'There is no code waiting. Send a new one.' => 'Tidak ada kode yang menunggu. Kirim kode baru.',
    'That code has expired. Send a new one.' => 'Kode itu sudah kedaluwarsa. Kirim kode baru.',
    'That code is not right. Check the email and try again.' => 'Kode itu tidak benar. Periksa email dan coba lagi.',

    // Engine: actions
    'Open the vault' => 'Buka brankas',
    'You disarmed it from the dashboard.' => 'Anda menonaktifkan alarm dari dasbor.',
    'Their previous address is still in use.' => 'Alamat mereka yang sebelumnya masih dipakai.',
    '{name} declined to be your {role}, so a required contact is missing.' => '{name} menolak menjadi {role} Anda, sehingga ada kontak wajib yang belum ada.',
    'Your switch has been disarmed, because it needs this role filled.' => 'Alarm Anda dinonaktifkan, karena peran ini harus terisi.',

    // Engine: checklist
    'The scheduler is running' => 'Penjadwal berjalan',
    'No run has been recorded yet. Add the crontab line shown in Settings.' => 'Belum ada proses yang tercatat. Tambahkan baris crontab yang ditampilkan di Pengaturan.',
    'The last run was {when}. Check the crontab line shown in Settings.' => 'Proses terakhir terjadi {when}. Periksa baris crontab yang ditampilkan di Pengaturan.',
    'The data folder cannot be read from the web' => 'Folder data tidak dapat dibaca dari web',
    'Files in the data folder can be opened in a browser. See "Protecting the data folder" in the README.' => 'File di folder data dapat dibuka di peramban. Lihat "Protecting the data folder" di README.',
    'The server could not test this by itself. Run the check from Settings.' => 'Server tidak dapat menguji hal ini sendiri. Jalankan pemeriksaan dari Pengaturan.',
    'Your next of kin has confirmed' => 'Kerabat terdekat Anda sudah mengonfirmasi',
    'Add your next of kin.' => 'Tambahkan kerabat terdekat Anda.',
    'Every message says where things are' => 'Setiap pesan menyebutkan di mana barang-barang berada',
    'Still holding starter text in square brackets: {names}.' => 'Masih berisi teks contoh dalam tanda kurung siku: {names}.',
    'Checked once your contacts have confirmed.' => 'Diperiksa setelah kontak Anda mengonfirmasi.',
    'Each contact has been given what they need' => 'Setiap kontak sudah menerima apa yang mereka perlukan',
    'Not ticked yet: {names}.' => 'Belum dicentang: {names}.',
    'A second email address for reminders' => 'Alamat email kedua untuk pengingat',
    'Recommended: an address with a different provider, in case your main one fails. Add it in Settings.' => 'Disarankan: alamat dari penyedia yang berbeda, kalau-kalau alamat utama Anda bermasalah. Tambahkan di Pengaturan.',
    '{name} declined. Choose someone else.' => '{name} menolak. Pilih orang lain.',
    'Send {name} their confirmation email.' => 'Kirim email konfirmasi kepada {name}.',
    'Waiting for {name} to confirm. The email went {when}.' => 'Menunggu {name} mengonfirmasi. Email dikirim {when}.',

    // Engine: warnings
    '{name}.json was damaged and the last good copy is in use. Check what changed recently, then save any setting to rewrite the file.' => '{name}.json rusak dan salinan terakhir yang baik sedang dipakai. Periksa apa yang berubah belakangan ini, lalu simpan pengaturan apa pun untuk menulis ulang file tersebut.',
    'The scheduler has never run. Nothing will be sent until it does.' => 'Penjadwal belum pernah berjalan. Tidak ada yang akan dikirim sampai penjadwal berjalan.',
    'The scheduler last ran {when}. While it is stopped, the switch cannot send anything.' => 'Penjadwal terakhir berjalan {when}. Selama berhenti, sakelar tidak dapat mengirim apa pun.',
    'This node has not heard from any other node for {duration}. Serious steps are delayed while it is cut off.' => 'Node ini tidak menerima kabar dari node lain selama {duration}. Langkah-langkah penting ditunda selama node ini terputus.',
    'This node has not heard from any other node yet. Serious steps are delayed while it is cut off.' => 'Node ini belum menerima kabar dari node lain. Langkah-langkah penting ditunda selama node ini terputus.',
    'The last email could not be sent ({when}): {error}' => 'Email terakhir tidak dapat dikirim ({when}): {error}',
    'notification' => 'notifikasi',
    'unknown error' => 'kesalahan tidak diketahui',
    'The {label} channel failed {when}: {error}' => 'Kanal {label} gagal {when}: {error}',
    'Files in the data folder can be opened in a browser.' => 'File di folder data dapat dibuka di peramban.',
    'The server could not check whether the data folder is blocked from the web.' => 'Server tidak dapat memeriksa apakah folder data diblokir dari web.',
    'A required contact is missing or unconfirmed.' => 'Ada kontak wajib yang belum ada atau belum mengonfirmasi.',
    '{name} declined and will not be contacted.' => '{name} menolak dan tidak akan dihubungi.',
    '{name} has not confirmed, so will not be contacted.' => '{name} belum mengonfirmasi, sehingga tidak akan dihubungi.',
    '{name} has not confirmed their new address. The old one is still in use.' => '{name} belum mengonfirmasi alamat barunya. Alamat lama masih dipakai.',
    '{name} has not answered their yearly reconfirmation. Check their address still works.' => '{name} belum menjawab konfirmasi ulang tahunannya. Periksa apakah alamatnya masih berfungsi.',
    'The message for {name} still holds starter text in square brackets.' => 'Pesan untuk {name} masih berisi teks contoh dalam tanda kurung siku.',
    'Your yearly review is due: confirm each contact still has what they need.' => 'Saatnya peninjauan tahunan: pastikan setiap kontak masih memiliki apa yang mereka perlukan.',
    'This install is not using HTTPS. Links in its emails can be read in transit.' => 'Instalasi ini tidak memakai HTTPS. Tautan dalam emailnya dapat terbaca saat dikirim.',

    // Engine: message previews
    'When you miss a check-in and its reminders' => 'Saat Anda melewatkan check-in beserta pengingatnya',
    'When the switch fires' => 'Saat sakelar dijalankan',
    'technical helper' => 'asisten teknis',
    'Technical helper' => 'Asisten teknis',
    'Technical helper (optional)' => 'Asisten teknis (opsional)',
    'Someone who can keep your servers, accounts or projects running.' => 'Orang yang dapat menjaga server, akun, atau proyek Anda tetap berjalan.',
];
