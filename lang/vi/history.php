<?php
/** Vietnamese wording for the history log: entries written from now on, and the words that fill messages in. */
declare(strict_types=1);

return [
    // Check-in methods (also stored as the way the last check-in came in)
    'the dashboard' => 'trang tổng quan',
    'the emailed link' => 'liên kết trong email',
    'another node' => 'nút khác',
    'arming the switch' => 'việc bật công tắc',
    'pausing the switch' => 'việc tạm dừng công tắc',
    'ending the pause' => 'việc kết thúc tạm dừng',

    // Cycle
    'Weekly check-in email sent.' => 'Đã gửi email check-in hằng tuần.',
    'Reminder {number} sent to the owner.' => 'Đã gửi nhắc nhở số {number} cho chủ sở hữu.',
    'No check-in after 72 hours. The next of kin has been asked.' => 'Không có check-in sau 72 giờ. Người thân nhất đã được hỏi.',
    'No check-in after 72 hours. The next of kin could not be emailed; the 72-hour wait has started anyway.' => 'Không có check-in sau 72 giờ. Không gửi được email cho người thân nhất; thời gian chờ 72 giờ vẫn được bắt đầu.',
    'The next of kin was reminded.' => 'Người thân nhất đã được nhắc.',
    'The next of kin answered "Something\'s wrong". The switch has fired.' => 'Người thân nhất đã trả lời "Có điều không ổn". Công tắc đã được kích hoạt.',
    'Neither the owner nor the next of kin confirmed all is well. The switch has fired.' => 'Cả chủ sở hữu lẫn người thân nhất đều không xác nhận rằng mọi việc vẫn ổn. Công tắc đã được kích hoạt.',
    '{role} ({name}) was sent their message.' => 'Đã gửi tin nhắn cho {role} ({name}).',
    '{name} was told it was a false alarm.' => '{name} đã được báo rằng đó là báo động nhầm.',
    'The next of kin answered "All fine". Nobody else was contacted.' => 'Người thân nhất đã trả lời "Mọi việc đều ổn". Không ai khác được liên hệ.',
    'The next of kin answered "All fine" ({via}). Nobody else was contacted.' => 'Người thân nhất đã trả lời "Mọi việc đều ổn" ({via}). Không ai khác được liên hệ.',
    'The weekly check-in email was sent by another node.' => 'Email check-in hằng tuần đã được gửi bởi một nút khác.',
    'Another node fired the switch.' => 'Một nút khác đã kích hoạt công tắc.',
    'Checked in by {via}.' => 'Đã check-in qua {via}.',
    'Checked in by another node ({via}).' => 'Đã check-in qua nút khác ({via}).',
    'False-alarm notices sent to {names}.' => 'Đã gửi thông báo báo động nhầm đến {names}.',

    // Switch
    'The pause ended. The switch is active again.' => 'Thời gian tạm dừng đã kết thúc. Công tắc hoạt động trở lại.',
    'The switch was armed.' => 'Công tắc đã được bật.',
    'The switch was disarmed. {reason}' => 'Công tắc đã được tắt. {reason}',
    'The switch was paused for {duration}.' => 'Công tắc đã được tạm dừng trong {duration}.',
    'The pause was ended early.' => 'Việc tạm dừng đã được kết thúc sớm.',
    'Yearly review completed.' => 'Đã hoàn tất rà soát hằng năm.',
    'The data folder can be read from the web.' => 'Thư mục dữ liệu có thể đọc được từ web.',

    // Contacts
    '{name} confirmed as {role}.' => '{name} đã xác nhận vai trò {role}.',
    'The new address for {name} was declined. The old one is still in use.' => 'Địa chỉ mới của {name} đã bị từ chối. Địa chỉ cũ vẫn đang được dùng.',
    '{name} declined to be {role}.' => '{name} đã từ chối vai trò {role}.',
    '{name} ({role}) was deleted.' => 'Đã xóa {name} ({role}).',
    '{name} was added as {role}.' => 'Đã thêm {name} với vai trò {role}.',

    // Sending
    'Notified {where}' => 'Đã thông báo qua {where}',
    'Could not notify {where}.' => 'Không thông báo được qua {where}.',
    'Sent "{subject}" to {to}' => 'Đã gửi "{subject}" đến {to}',
    'Could not send "{subject}" to {to}' => 'Không gửi được "{subject}" đến {to}',

    // Settings and sign-in
    'Backup email address confirmed: {address}.' => 'Đã xác nhận địa chỉ email dự phòng: {address}.',
    'Main email address confirmed: {address}.' => 'Đã xác nhận địa chỉ email chính: {address}.',
    'A wrong password was entered at sign-in.' => 'Đã nhập sai mật khẩu khi đăng nhập.',

    // Message placeholders (contact page)
    'The name of the person receiving the message' => 'Tên của người nhận tin nhắn',
    'Whole days since your last check-in' => 'Số ngày tròn kể từ lần check-in gần nhất của bạn',
    'The date of your last check-in' => 'Ngày check-in gần nhất của bạn',
    'Next of kin: name, email, phone' => 'Người thân nhất: tên, email, điện thoại',
    'Technical contact: name, email, phone' => 'Người liên hệ kỹ thuật: tên, email, điện thoại',
    'Everyone else on your list, one per line' => 'Tất cả những người còn lại trong danh sách của bạn, mỗi người một dòng',

    // Settings, cluster, install, storage
    'The weekly check-in time was changed to {day} at {time} ({tz}).' => 'Thời điểm check-in hằng tuần đã được đổi thành {day} lúc {time} ({tz}).',
    'Mail settings were changed.' => 'Cài đặt email đã được thay đổi.',
    'The owner password was changed.' => 'Mật khẩu chủ sở hữu đã được thay đổi.',
    'A node was added to the cluster.' => 'Đã thêm một nút vào cụm.',
    'A node public address was changed.' => 'Địa chỉ công khai của một nút đã được thay đổi.',
    'A node was removed from the cluster.' => 'Đã xóa một nút khỏi cụm.',
    'The cluster settings were changed.' => 'Cài đặt cụm đã được thay đổi.',
    'Death Plan Switch {version} was installed.' => 'Đã cài đặt Death Plan Switch {version}.',
    'Vault API error: {detail}' => 'Lỗi API của két an toàn: {detail}',
    '{name}.json was unreadable. The last good copy was used instead.' => 'Không đọc được {name}.json. Bản sao tốt gần nhất đã được dùng thay thế.',
];
