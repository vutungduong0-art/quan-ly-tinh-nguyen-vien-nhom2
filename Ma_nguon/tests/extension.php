<?php
require __DIR__ . '/helper.php';
require __DIR__ . '/../src/RegistrationPolicy.php';
$p = new RegistrationPolicy();
same($p->decide(10, 9, true, true), 'confirmed', 'available');
same($p->decide(10, 10, true, true), 'waiting', 'full-wait');
same($p->decide(10, 10, true, false), 'rejected', 'full-no-wait');
same($p->decide(10, 2, false, true), 'rejected', 'closed');
same($p->decide(0, 0, true, true), 'rejected', 'invalid-capacity');
same($p->decide(10, -1, true, true), 'rejected', 'invalid-count');
same($p->decide(10, 11, true, true), 'rejected', 'corrupt-over-count');
same($p->decide(1, 0, true, false), 'confirmed', 'first-seat');
// Ca bổ sung 1: Sự kiện đã đóng nhưng đồng thời đầy chỗ và có bật waiting list (kết quả bắt buộc là rejected)
same($p->decide(10, 10, false, true), 'rejected', 'closed-and-full');
// Ca bổ sung 2: Ca biên cực tiểu sức chứa 1, đã có 1 người đăng ký, sự kiện mở và bật waiting (kết quả là waiting)
same($p->decide(1, 1, true, true), 'waiting', 'single-seat-full-wait');
finish();
