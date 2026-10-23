<?php if ($events === []): ?>
    <div class="panel empty-inline">Chưa có hoạt động sắp diễn ra.</div>
<?php else: ?>
    <div class="event-grid">
        <?php foreach ($events as $index => $event):
            $isOpen = ($event['status'] ?? '') === 'published' && (string) $event['end_at'] >= gmdate('Y-m-d H:i:s');
            $statusLabel = ($event['status'] ?? '') === 'closed' ? 'Đã đóng' : ($isOpen ? 'Đang mở đăng ký' : 'Đã kết thúc');
        ?>
            <article class="event-card">
                <div class="event-cover cover-<?= ($index % 3) + 1 ?>">
                    <span class="event-date"><strong><?= h((new DateTimeImmutable($event['start_at'], new DateTimeZone('UTC')))->setTimezone(new DateTimeZone(Validator::DISPLAY_TIMEZONE))->format('d')) ?></strong><?= h((new DateTimeImmutable($event['start_at'], new DateTimeZone('UTC')))->setTimezone(new DateTimeZone(Validator::DISPLAY_TIMEZONE))->format('m/Y')) ?></span>
                    <span class="cover-symbol" aria-hidden="true"><?= ['♧', '⌁', '✦'][$index % 3] ?></span>
                </div>
                <div class="event-card-body">
                    <span class="status-pill <?= $isOpen ? '' : 'status-muted' ?>"><?= h($statusLabel) ?></span>
                    <h3><?= h($event['name']) ?></h3>
                    <p class="event-location">⌖ <?= h($event['location']) ?></p>
                    <p class="event-time">◷ <?= h(format_local_datetime($event['start_at'])) ?></p>
                    <div class="event-meta"><span><?= h($event['shift_count']) ?> ca</span><span><?= h($event['registration_count']) ?> đăng ký</span></div>
                    <div class="card-actions">
                        <a class="button button-secondary button-small" href="<?= h(route_url('activity', ['id' => $event['id']])) ?>">Xem chi tiết</a>
                        <?php if (($currentUser['role'] ?? '') === 'volunteer' && $isOpen): ?>
                            <form method="post"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="register"><input type="hidden" name="event_id" value="<?= h($event['id']) ?>"><button class="button button-small" type="submit">Đăng ký</button></form>
                        <?php elseif (!$currentUser && $isOpen): ?><a class="button button-small" href="<?= h(route_url('login')) ?>">Đăng ký</a><?php endif; ?>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
