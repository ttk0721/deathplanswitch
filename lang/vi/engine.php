<?php
/** Vietnamese wording for the core classes: engine warnings and checklist, web errors, setup checks, contact labels. */
declare(strict_types=1);

return [
    // Contact labels
    'next of kin' => 'người thân nhất',
    'technical 2IC' => 'cộng sự kỹ thuật',
    'legal contact' => 'người liên hệ pháp lý',
    'contact' => 'người liên hệ',
    'Next of kin' => 'Người thân nhất',
    'Technical 2IC' => 'Cộng sự kỹ thuật',
    'Legal contact' => 'Người liên hệ pháp lý',
    'Other contact' => 'Người liên hệ khác',
    'Contact' => 'Người liên hệ',
    '(none listed)' => '(chưa có ai trong danh sách)',

    // Web
    'The data folder needs attention' => 'Thư mục dữ liệu cần được chú ý',
    'Nothing has been changed. See "If a data file is damaged" in the README.' => 'Chưa có gì bị thay đổi. Xem mục "If a data file is damaged" trong README.',
    'Too many wrong passwords. Try again {when}.' => 'Nhập sai mật khẩu quá nhiều lần. Hãy thử lại {when}.',
    'That password is not right.' => 'Mật khẩu này không đúng.',
    'That form had expired. Go back, reload the page and try again.' => 'Biểu mẫu này đã hết hạn. Hãy quay lại, tải lại trang rồi thử lại.',

    // Setup
    'PHP {version} or newer' => 'PHP {version} trở lên',
    'This server runs PHP {version}. Ask your host to switch to a current version.' => 'Máy chủ này đang chạy PHP {version}. Hãy nhờ nhà cung cấp hosting chuyển sang phiên bản mới hơn.',
    'The OpenSSL extension, for encrypted mail' => 'Tiện ích mở rộng OpenSSL, dùng cho email mã hóa',
    'Ask your host to enable the PHP openssl extension.' => 'Hãy nhờ nhà cung cấp hosting bật tiện ích mở rộng PHP openssl.',
    'The mbstring extension' => 'Tiện ích mở rộng mbstring',
    'Ask your host to enable the PHP mbstring extension.' => 'Hãy nhờ nhà cung cấp hosting bật tiện ích mở rộng PHP mbstring.',
    'The data folder can be written to' => 'Có thể ghi vào thư mục dữ liệu',
    'PHP cannot write to {dir}. Give that folder to the user the web pages run as' => 'PHP không ghi được vào {dir}. Hãy trao thư mục đó cho người dùng chạy các trang web',
    'Choose a password of at least {min} characters.' => 'Hãy chọn mật khẩu có ít nhất {min} ký tự.',
    'That password is too long.' => 'Mật khẩu này quá dài.',
    'Enter the mail server host, for example smtp.example.com.' => 'Hãy nhập địa chỉ máy chủ email, ví dụ smtp.example.com.',
    'Enter the mail server port. 587 is the usual one.' => 'Hãy nhập cổng của máy chủ email. Thông thường là 587.',
    'Enter the address the switch sends from. Your mail service must allow it.' => 'Hãy nhập địa chỉ mà công tắc dùng để gửi thư. Dịch vụ email của bạn phải cho phép địa chỉ này.',
    'There is no code waiting. Send a new one.' => 'Hiện không có mã nào đang chờ. Hãy gửi mã mới.',
    'That code has expired. Send a new one.' => 'Mã này đã hết hạn. Hãy gửi mã mới.',
    'That code is not right. Check the email and try again.' => 'Mã này không đúng. Hãy kiểm tra email và thử lại.',

    // Engine: actions
    'Open the vault' => 'Mở két an toàn',
    'You disarmed it from the dashboard.' => 'Bạn đã tắt công tắc từ trang tổng quan.',
    'Their previous address is still in use.' => 'Địa chỉ trước đây của họ vẫn đang được dùng.',
    '{name} declined to be your {role}, so a required contact is missing.' => '{name} đã từ chối làm {role} của bạn, nên còn thiếu một người liên hệ bắt buộc.',
    'Your switch has been disarmed, because it needs this role filled.' => 'Công tắc của bạn đã được tắt, vì vai trò này phải có người đảm nhiệm.',

    // Engine: checklist
    'The scheduler is running' => 'Bộ lập lịch đang chạy',
    'No run has been recorded yet. Add the crontab line shown in Settings.' => 'Chưa ghi nhận lần chạy nào. Hãy thêm dòng crontab được hiển thị trong Cài đặt.',
    'The last run was {when}. Check the crontab line shown in Settings.' => 'Lần chạy gần nhất là {when}. Hãy kiểm tra dòng crontab được hiển thị trong Cài đặt.',
    'The data folder cannot be read from the web' => 'Không thể đọc thư mục dữ liệu từ web',
    'Files in the data folder can be opened in a browser. See "Protecting the data folder" in the README.' => 'Các tệp trong thư mục dữ liệu có thể được mở bằng trình duyệt. Xem mục "Protecting the data folder" trong README.',
    'The server could not test this by itself. Run the check from Settings.' => 'Máy chủ không tự kiểm tra được điều này. Hãy chạy kiểm tra từ Cài đặt.',
    'Your next of kin has confirmed' => 'Người thân nhất của bạn đã xác nhận',
    'Add your next of kin.' => 'Hãy thêm người thân nhất của bạn.',
    'Your technical 2IC has confirmed' => 'Cộng sự kỹ thuật của bạn đã xác nhận',
    'Add the person who would look after your clients.' => 'Hãy thêm người sẽ lo cho khách hàng của bạn.',
    'Every message says where things are' => 'Mỗi tin nhắn đều nói rõ mọi thứ ở đâu',
    'Still holding starter text in square brackets: {names}.' => 'Vẫn còn đoạn mẫu trong ngoặc vuông: {names}.',
    'Checked once your contacts have confirmed.' => 'Sẽ được kiểm tra khi những người liên hệ của bạn đã xác nhận.',
    'Each contact has been given what they need' => 'Mỗi người liên hệ đã nhận được những gì họ cần',
    'Not ticked yet: {names}.' => 'Chưa đánh dấu: {names}.',
    'A second email address for reminders' => 'Địa chỉ email thứ hai để nhận nhắc nhở',
    'Recommended: an address with a different provider, in case your main one fails. Add it in Settings.' => 'Khuyến nghị: một địa chỉ của nhà cung cấp khác, phòng khi địa chỉ chính gặp sự cố. Hãy thêm trong Cài đặt.',
    '{name} declined. Choose someone else.' => '{name} đã từ chối. Hãy chọn người khác.',
    'Send {name} their confirmation email.' => 'Hãy gửi email xác nhận cho {name}.',
    'Waiting for {name} to confirm. The email went {when}.' => 'Đang chờ {name} xác nhận. Email đã được gửi {when}.',

    // Engine: warnings
    '{name}.json was damaged and the last good copy is in use. Check what changed recently, then save any setting to rewrite the file.' => '{name}.json đã bị hỏng và bản sao tốt gần nhất đang được dùng. Hãy xem gần đây có gì thay đổi, rồi lưu một cài đặt bất kỳ để ghi lại tệp.',
    'The scheduler has never run. Nothing will be sent until it does.' => 'Bộ lập lịch chưa từng chạy. Sẽ không có gì được gửi cho đến khi nó chạy.',
    'The scheduler last ran {when}. While it is stopped, the switch cannot send anything.' => 'Bộ lập lịch chạy lần cuối {when}. Khi nó dừng, công tắc không thể gửi gì.',
    'This node has not heard from any other node for {duration}. Serious steps are delayed while it is cut off.' => 'Nút này đã {duration} không nhận được tin từ nút nào khác. Các bước quan trọng bị hoãn lại khi nút bị ngắt kết nối.',
    'This node has not heard from any other node yet. Serious steps are delayed while it is cut off.' => 'Nút này chưa nhận được tin từ nút nào khác. Các bước quan trọng bị hoãn lại khi nút bị ngắt kết nối.',
    'The last email could not be sent ({when}): {error}' => 'Email gần nhất không gửi được ({when}): {error}',
    'notification' => 'thông báo',
    'unknown error' => 'lỗi không xác định',
    'The {label} channel failed {when}: {error}' => 'Kênh {label} đã gặp lỗi {when}: {error}',
    'Files in the data folder can be opened in a browser.' => 'Các tệp trong thư mục dữ liệu có thể được mở bằng trình duyệt.',
    'The server could not check whether the data folder is blocked from the web.' => 'Máy chủ không kiểm tra được thư mục dữ liệu có bị chặn truy cập từ web hay không.',
    'A required contact is missing or unconfirmed.' => 'Thiếu một người liên hệ bắt buộc, hoặc người đó chưa xác nhận.',
    '{name} declined and will not be contacted.' => '{name} đã từ chối và sẽ không được liên hệ.',
    '{name} has not confirmed, so will not be contacted.' => '{name} chưa xác nhận, nên sẽ không được liên hệ.',
    '{name} has not confirmed their new address. The old one is still in use.' => '{name} chưa xác nhận địa chỉ mới. Địa chỉ cũ vẫn đang được dùng.',
    '{name} has not answered their yearly reconfirmation. Check their address still works.' => '{name} chưa trả lời yêu cầu xác nhận lại hằng năm. Hãy kiểm tra xem địa chỉ của họ còn dùng được không.',
    'The message for {name} still holds starter text in square brackets.' => 'Tin nhắn dành cho {name} vẫn còn đoạn mẫu trong ngoặc vuông.',
    'Your yearly review is due: confirm each contact still has what they need.' => 'Đã đến kỳ rà soát hằng năm: hãy xác nhận mỗi người liên hệ vẫn có những gì họ cần.',
    'This install is not using HTTPS. Links in its emails can be read in transit.' => 'Bản cài đặt này không dùng HTTPS. Các liên kết trong email của nó có thể bị đọc trộm khi truyền đi.',

    // Engine: message previews
    'When you miss a check-in and its reminders' => 'Khi bạn bỏ lỡ một lần check-in và các nhắc nhở của nó',
    'When the switch fires' => 'Khi công tắc được kích hoạt',
];
