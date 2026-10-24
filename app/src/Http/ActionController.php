<?php

declare(strict_types=1);

final class ActionController
{
    public function __construct(private readonly mysqli $db, private readonly Auth $auth)
    {
    }

    public function dispatch(string $action): never
    {
        switch ($action) {
            case 'login': $this->login();
            case 'signup': $this->signup();
            case 'logout': $this->logout();
            case 'create_event': $this->createEvent();
            case 'update_event': $this->updateEvent();
            case 'close_event': $this->closeEvent();
            case 'create_shift': $this->createShift();
            case 'update_shift': $this->updateShift();
            case 'delete_shift': $this->deleteShift();
            case 'register': $this->register();
            case 'withdraw': $this->withdraw();
            case 'save_profile': $this->saveProfile();
            case 'save_skills': $this->saveSkills();
            case 'add_availability': $this->addAvailability();
            case 'delete_availability': $this->deleteAvailability();
            case 'generate_allocation': $this->generateAllocation();
            case 'compare_allocation': $this->compareAllocation();
            case 'publish_allocation': $this->publishAllocation();
            case 'manual_assign': $this->manualAssign();
            case 'transfer_assignment': $this->transferAssignment();
            case 'confirm_assignment': $this->confirmAssignment();
            case 'report_absent': $this->reportAbsent();
            case 'mark_absent': $this->markAbsent();
            case 'cancel_assignment': $this->cancelAssignment();
            case 'replace_absent': $this->replaceAbsent();
            case 'mark_replacement_unfilled': $this->markReplacementUnfilled();
            case 'reopen_replacement': $this->reopenReplacement();
            default: throw new HttpException(400, 'Yêu cầu không hợp lệ.');
        }
    }

    private function login(): never
    {
        $username = trim(is_string($_POST['username'] ?? null) ? $_POST['username'] : '');
        $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
        if ($this->auth->attempt($username, $password)) {
            set_flash('success', 'Đăng nhập thành công. Chào mừng bạn trở lại!');
            redirect_to('dashboard');
        }
        set_flash('error', 'Tên đăng nhập hoặc mật khẩu không đúng.', ['username' => $username]);
        redirect_to('login');
    }

    private function signup(): never
    {
        if ($this->auth->user() !== null) {
            throw new HttpException(403, 'Bạn đã đăng nhập nên không thể tạo thêm tài khoản tại đây.');
        }

        $errors = [];
        $username = Validator::requiredText($_POST['username'] ?? null, 'Tên đăng nhập', 80, $errors);
        $fullName = Validator::requiredText($_POST['full_name'] ?? null, 'Họ tên', 160, $errors);
        $email = Validator::requiredText($_POST['email'] ?? null, 'Email', 190, $errors);
        $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
        $confirmation = is_string($_POST['password_confirmation'] ?? null) ? $_POST['password_confirmation'] : '';

        if (!preg_match('/^[A-Za-z0-9._-]{4,80}$/', $username)) {
            $errors[] = 'Tên đăng nhập phải có 4–80 ký tự và chỉ gồm chữ, số, dấu chấm, gạch dưới hoặc gạch ngang.';
        }
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors[] = 'Email không đúng định dạng.';
        }
        if (strlen($password) < 8 || strlen($password) > 128) {
            $errors[] = 'Mật khẩu phải có từ 8 đến 128 ký tự.';
        }
        if (!hash_equals($password, $confirmation)) {
            $errors[] = 'Mật khẩu nhập lại không khớp.';
        }

        $old = ['username' => $username, 'full_name' => $fullName, 'email' => $email];
        if ($errors !== []) {
            set_flash('error', implode(' ', $errors), $old);
            redirect_to('signup');
        }

        try {
            $this->auth->registerVolunteer([
                'username' => $username,
                'full_name' => $fullName,
                'email' => $email,
                'password' => $password,
            ]);
            set_flash('success', 'Tạo tài khoản tình nguyện viên thành công. Bạn có thể đăng nhập ngay.');
            redirect_to('login');
        } catch (DomainException $exception) {
            set_flash('error', $exception->getMessage(), $old);
            redirect_to('signup');
        }
    }

    private function logout(): never
    {
        $this->auth->requireLogin();
        $this->auth->logout();
        session_start();
        set_flash('success', 'Bạn đã đăng xuất an toàn.');
        redirect_to('login');
    }

    private function createEvent(): never
    {
        $user = $this->auth->requireRole('coordinator');
        [$data, $errors] = $this->eventData();
        if ($errors !== []) {
            set_flash('error', implode(' ', $errors), $_POST);
            redirect_to('new_activity');
        }
        $id = (new EventRepository($this->db))->create($data, (int) $user['id']);
        set_flash('success', 'Đã tạo hoạt động và lưu vào cơ sở dữ liệu.');
        redirect_to('activity', ['id' => $id]);
    }

    private function updateEvent(): never
    {
        $user = $this->auth->requireRole('coordinator');
        $eventId = $this->positiveId('event_id', 'Hoạt động');
        [$data, $errors] = $this->eventData();
        if ($errors !== []) {
            set_flash('error', implode(' ', $errors), $_POST);
            redirect_to('activity', ['id' => $eventId]);
        }
        try {
            (new EventRepository($this->db))->updateEvent($eventId, $data, (int) $user['id']);
            set_flash('success', 'Đã cập nhật hoạt động; các phương án nháp cũ sẽ được kiểm tra lại.');
        } catch (DomainException $exception) {
            set_flash('error', $exception->getMessage(), $_POST);
        }
        redirect_to('activity', ['id' => $eventId]);
    }

    private function closeEvent(): never
    {
        $user = $this->auth->requireRole('coordinator');
        $eventId = $this->positiveId('event_id', 'Hoạt động');
        try {
            (new EventRepository($this->db))->closeEvent($eventId, (int) $user['id']);
            set_flash('success', 'Đã đóng hoạt động và giữ nguyên dữ liệu lịch sử.');
        } catch (DomainException $exception) {
            set_flash('error', $exception->getMessage());
        }
        redirect_to('activity', ['id' => $eventId]);
    }

    private function createShift(): never
    {
        $this->auth->requireRole('coordinator');
        $eventId = $this->positiveId('event_id', 'Hoạt động');
        [$data, $errors] = $this->shiftData();
        if ($errors !== []) {
            set_flash('error', implode(' ', $errors), $_POST);
            redirect_to('activity', ['id' => $eventId]);
        }
        try {
            (new EventRepository($this->db))->createShift($eventId, $data, selected_skill_ids($_POST['skill_ids'] ?? []));
            set_flash('success', 'Đã tạo ca và gắn kỹ năng yêu cầu.');
        } catch (DomainException $exception) {
            set_flash('error', $exception->getMessage(), $_POST);
        }
        redirect_to('activity', ['id' => $eventId]);
    }

    private function updateShift(): never
    {
        $user = $this->auth->requireRole('coordinator');
        $eventId = $this->positiveId('event_id', 'Hoạt động');
        $shiftId = $this->positiveId('shift_id', 'Ca');
        [$data, $errors] = $this->shiftData();
        if ($errors !== []) {
            set_flash('error', implode(' ', $errors), $_POST);
            redirect_to('activity', ['id' => $eventId]);
        }
        try {
            (new EventRepository($this->db))->updateShift(
                $eventId,
                $shiftId,
                $data,
                selected_skill_ids($_POST['skill_ids'] ?? []),
                (int) $user['id']
            );
            set_flash('success', 'Đã cập nhật ca và tăng phiên bản lịch.');
        } catch (DomainException $exception) {
            set_flash('error', $exception->getMessage(), $_POST);
        }
        redirect_to('activity', ['id' => $eventId]);
    }

    private function deleteShift(): never
    {
        $user = $this->auth->requireRole('coordinator');
        $eventId = $this->positiveId('event_id', 'Hoạt động');
        $shiftId = $this->positiveId('shift_id', 'Ca');
        try {
            (new EventRepository($this->db))->deleteShift($eventId, $shiftId, (int) $user['id']);
            set_flash('success', 'Đã xóa ca chưa được sử dụng.');
        } catch (DomainException $exception) {
            set_flash('error', $exception->getMessage());
        }
        redirect_to('activity', ['id' => $eventId]);
    }

    private function register(): never
    {
        $user = $this->auth->requireRole('volunteer');
        $eventId = $this->positiveId('event_id', 'Hoạt động');
        try {
            (new EventRepository($this->db))->register($eventId, (int) $user['id']);
            set_flash('success', 'Đăng ký tham gia thành công.');
        } catch (DomainException $exception) {
            set_flash('error', $exception->getMessage());
        }
        redirect_to('activity', ['id' => $eventId]);
    }

    private function withdraw(): never
    {
        $user = $this->auth->requireRole('volunteer');
        $eventId = $this->positiveId('event_id', 'Hoạt động');
        try {
            (new EventRepository($this->db))->withdraw($eventId, (int) $user['id']);
            set_flash('success', 'Đã rút đăng ký hoạt động.');
        } catch (DomainException $exception) {
            set_flash('error', $exception->getMessage());
        }
        redirect_to('activity', ['id' => $eventId]);
    }

    private function saveProfile(): never
    {
        $user = $this->auth->requireLogin();
        $targetId = $user['role'] === 'coordinator' ? $this->positiveId('volunteer_id', 'Tình nguyện viên') : (int) $user['id'];
        $errors = [];
        $data = [
            'full_name' => Validator::requiredText($_POST['full_name'] ?? null, 'Họ tên', 160, $errors),
            'volunteer_code' => Validator::requiredText($_POST['volunteer_code'] ?? null, 'Mã tình nguyện viên', 30, $errors),
            'phone' => Validator::optionalText($_POST['phone'] ?? null, 'Điện thoại', 30, $errors),
            'email' => Validator::optionalText($_POST['email'] ?? null, 'Email', 190, $errors),
            'notes' => Validator::optionalText($_POST['notes'] ?? null, 'Ghi chú', 1000, $errors),
        ];
        if ($data['email'] !== '' && filter_var($data['email'], FILTER_VALIDATE_EMAIL) === false) {
            $errors[] = 'Email không đúng định dạng.';
        }
        if ($errors !== []) {
            set_flash('error', implode(' ', $errors), $_POST);
        } else {
            (new VolunteerRepository($this->db))->saveProfile($targetId, $data);
            set_flash('success', 'Đã cập nhật hồ sơ.');
        }
        redirect_to('volunteers', ['id' => $targetId]);
    }

    private function saveSkills(): never
    {
        $user = $this->auth->requireLogin();
        $targetId = $user['role'] === 'coordinator' ? $this->positiveId('volunteer_id', 'Tình nguyện viên') : (int) $user['id'];
        try {
            (new VolunteerRepository($this->db))->replaceSkills($targetId, selected_skill_ids($_POST['skill_ids'] ?? []));
            set_flash('success', 'Đã cập nhật kỹ năng. Các phương án nháp cũ sẽ được kiểm tra lại.');
        } catch (DomainException $exception) {
            set_flash('error', $exception->getMessage());
        }
        redirect_to('volunteers', ['id' => $targetId]);
    }

    private function addAvailability(): never
    {
        $user = $this->auth->requireLogin();
        $targetId = $user['role'] === 'coordinator' ? $this->positiveId('volunteer_id', 'Tình nguyện viên') : (int) $user['id'];
        $errors = [];
        $start = Validator::dateTimeToUtc($_POST['start_at'] ?? null, 'Bắt đầu lịch rảnh', $errors);
        $end = Validator::dateTimeToUtc($_POST['end_at'] ?? null, 'Kết thúc lịch rảnh', $errors);
        Validator::orderedRange($start, $end, 'lịch rảnh', $errors);
        if ($errors !== []) {
            set_flash('error', implode(' ', $errors));
        } else {
            (new VolunteerRepository($this->db))->addAvailability($targetId, (string) $start, (string) $end);
            set_flash('success', 'Đã thêm khoảng lịch rảnh.');
        }
        redirect_to('volunteers', ['id' => $targetId]);
    }

    private function deleteAvailability(): never
    {
        $user = $this->auth->requireLogin();
        $targetId = $user['role'] === 'coordinator' ? $this->positiveId('volunteer_id', 'Tình nguyện viên') : (int) $user['id'];
        $slotId = $this->positiveId('slot_id', 'Khoảng lịch rảnh');
        try {
            (new VolunteerRepository($this->db))->deleteAvailability($slotId, $targetId);
            set_flash('success', 'Đã xóa khoảng lịch rảnh.');
        } catch (DomainException $exception) {
            set_flash('error', $exception->getMessage());
        }
        redirect_to('volunteers', ['id' => $targetId]);
    }

    private function generateAllocation(): never
    {
        $user = $this->auth->requireRole('coordinator');
        $eventId = $this->positiveId('event_id', 'Hoạt động');
        $algorithm = is_string($_POST['algorithm'] ?? null) ? $_POST['algorithm'] : '';
        try {
            $runId = (new AllocationRepository($this->db))->generate($eventId, $algorithm, (int) $user['id']);
            set_flash('success', "Đã tạo phương án #$runId và kiểm tra không có vi phạm.");
        } catch (DomainException $exception) {
            set_flash('error', $exception->getMessage());
        }
        redirect_to('assignments', ['event_id' => $eventId]);
    }

    private function compareAllocation(): never
    {
        $user = $this->auth->requireRole('coordinator');
        $eventId = $this->positiveId('event_id', 'Hoạt động');
        try {
            $result = (new AllocationRepository($this->db))->generateComparison($eventId, (int) $user['id']);
            set_flash('success', 'Đã chạy đối chứng trên cùng snapshot: baseline #' . $result['baseline_run_id']
                . ' và cải tiến #' . $result['improved_run_id'] . '.');
        } catch (DomainException $exception) {
            set_flash('error', $exception->getMessage());
        }
        redirect_to('assignments', ['event_id' => $eventId]);
    }

    private function publishAllocation(): never
    {
        $user = $this->auth->requireRole('coordinator');
        $runId = $this->positiveId('run_id', 'Bản chạy');
        $eventId = $this->positiveId('event_id', 'Hoạt động');
        try {
            (new AllocationRepository($this->db))->publish($runId, (int) $user['id']);
            set_flash('success', 'Đã công bố lịch phân công.');
        } catch (DomainException $exception) {
            set_flash('error', $exception->getMessage());
        }
        redirect_to('assignments', ['event_id' => $eventId]);
    }

    private function manualAssign(): never
    {
        $user = $this->auth->requireRole('coordinator');
        $shiftId = $this->positiveId('shift_id', 'Ca');
        $volunteerId = $this->positiveId('volunteer_id', 'Tình nguyện viên');
        $eventId = $this->positiveId('event_id', 'Hoạt động');
        try {
            (new AssignmentRepository($this->db))->manualAssign($shiftId, $volunteerId, (int) $user['id']);
            set_flash('success', 'Đã phân công thủ công và kiểm tra ràng buộc.');
        } catch (DomainException $exception) {
            set_flash('error', $exception->getMessage());
        }
        redirect_to('assignments', ['event_id' => $eventId]);
    }

    private function transferAssignment(): never
    {
        $user = $this->auth->requireRole('coordinator');
        $assignmentId = $this->positiveId('assignment_id', 'Phân công');
        $volunteerId = $this->positiveId('volunteer_id', 'Người nhận mới');
        $version = $this->positiveId('version', 'Phiên bản');
        $eventId = $this->positiveId('event_id', 'Hoạt động');
        try {
            (new AssignmentRepository($this->db))->transfer($assignmentId, $volunteerId, $version, (int) $user['id']);
            set_flash('success', 'Đã chuyển phân công nguyên tử; phân công cũ và mới được cập nhật cùng giao dịch.');
        } catch (DomainException $exception) {
            set_flash('error', $exception->getMessage());
        }
        redirect_to('assignments', ['event_id' => $eventId]);
    }

    private function confirmAssignment(): never
    {
        $user = $this->auth->requireRole('volunteer');
        $assignmentId = $this->positiveId('assignment_id', 'Phân công');
        $version = $this->positiveId('version', 'Phiên bản');
        try {
            (new AssignmentRepository($this->db))->confirm($assignmentId, (int) $user['id'], $version);
            set_flash('success', 'Đã xác nhận tham gia ca.');
        } catch (DomainException $exception) {
            set_flash('error', $exception->getMessage());
        }
        redirect_to('my_shifts');
    }

    private function reportAbsent(): never
    {
        $user = $this->auth->requireRole('volunteer');
        $assignmentId = $this->positiveId('assignment_id', 'Phân công');
        $version = $this->positiveId('version', 'Phiên bản');
        $reason = $this->text('reason', 'Không thể tham gia');
        try {
            (new AssignmentRepository($this->db))->markAbsent($assignmentId, (int) $user['id'], $version, $reason, (int) $user['id']);
            set_flash('success', 'Đã báo vắng. Điều phối viên sẽ tìm người thay.');
        } catch (DomainException $exception) {
            set_flash('error', $exception->getMessage());
        }
        redirect_to('my_shifts');
    }

    private function markAbsent(): never
    {
        $user = $this->auth->requireRole('coordinator');
        $assignmentId = $this->positiveId('assignment_id', 'Phân công');
        $version = $this->positiveId('version', 'Phiên bản');
        $eventId = $this->positiveId('event_id', 'Hoạt động');
        try {
            (new AssignmentRepository($this->db))->markAbsent($assignmentId, (int) $user['id'], $version, $this->text('reason', 'Vắng mặt'));
            set_flash('success', 'Đã ghi nhận vắng và mở yêu cầu tìm người thay.');
        } catch (DomainException $exception) {
            set_flash('error', $exception->getMessage());
        }
        redirect_to('assignments', ['event_id' => $eventId]);
    }

    private function cancelAssignment(): never
    {
        $user = $this->auth->requireRole('coordinator');
        $assignmentId = $this->positiveId('assignment_id', 'Phân công');
        $version = $this->positiveId('version', 'Phiên bản');
        $eventId = $this->positiveId('event_id', 'Hoạt động');
        try {
            (new AssignmentRepository($this->db))->cancel($assignmentId, $version, (int) $user['id']);
            set_flash('success', 'Đã gỡ phân công.');
        } catch (DomainException $exception) {
            set_flash('error', $exception->getMessage());
        }
        redirect_to('assignments', ['event_id' => $eventId]);
    }

    private function replaceAbsent(): never
    {
        $user = $this->auth->requireRole('coordinator');
        $requestId = $this->positiveId('request_id', 'Yêu cầu');
        $volunteerId = $this->positiveId('volunteer_id', 'Người thay');
        $eventId = $this->positiveId('event_id', 'Hoạt động');
        try {
            (new AssignmentRepository($this->db))->replaceAbsent($requestId, $volunteerId, (int) $user['id']);
            set_flash('success', 'Đã thay người và cập nhật lịch trong một giao dịch.');
        } catch (DomainException $exception) {
            set_flash('error', $exception->getMessage());
        }
        redirect_to('assignments', ['event_id' => $eventId]);
    }

    private function markReplacementUnfilled(): never
    {
        $user = $this->auth->requireRole('coordinator');
        $requestId = $this->positiveId('request_id', 'Yêu cầu');
        $eventId = $this->positiveId('event_id', 'Hoạt động');
        try {
            (new AssignmentRepository($this->db))->markReplacementUnfilled(
                $requestId,
                $this->text('resolution_note', 'Không tìm được người thay phù hợp'),
                (int) $user['id']
            );
            set_flash('success', 'Đã kết thúc yêu cầu với trạng thái chưa tìm được người thay.');
        } catch (DomainException $exception) {
            set_flash('error', $exception->getMessage());
        }
        redirect_to('assignments', ['event_id' => $eventId]);
    }

    private function reopenReplacement(): never
    {
        $user = $this->auth->requireRole('coordinator');
        $requestId = $this->positiveId('request_id', 'Yêu cầu');
        $eventId = $this->positiveId('event_id', 'Hoạt động');
        try {
            (new AssignmentRepository($this->db))->reopenReplacement($requestId, (int) $user['id']);
            set_flash('success', 'Đã mở lại yêu cầu tìm người thay.');
        } catch (DomainException $exception) {
            set_flash('error', $exception->getMessage());
        }
        redirect_to('assignments', ['event_id' => $eventId]);
    }

    private function eventData(): array
    {
        $errors = [];
        $data = [
            'name' => Validator::requiredText($_POST['name'] ?? null, 'Tên hoạt động', 180, $errors),
            'location' => Validator::requiredText($_POST['location'] ?? null, 'Địa điểm', 255, $errors),
            'start_at' => Validator::dateTimeToUtc($_POST['start_at'] ?? null, 'Thời gian bắt đầu', $errors),
            'end_at' => Validator::dateTimeToUtc($_POST['end_at'] ?? null, 'Thời gian kết thúc', $errors),
            'description' => Validator::optionalText($_POST['description'] ?? null, 'Mô tả', 3000, $errors),
        ];
        Validator::orderedRange($data['start_at'], $data['end_at'], 'hoạt động', $errors);
        return [$data, $errors];
    }

    private function shiftData(): array
    {
        $errors = [];
        $data = [
            'name' => Validator::requiredText($_POST['name'] ?? null, 'Tên ca', 160, $errors),
            'start_at' => Validator::dateTimeToUtc($_POST['start_at'] ?? null, 'Thời điểm bắt đầu', $errors),
            'end_at' => Validator::dateTimeToUtc($_POST['end_at'] ?? null, 'Thời điểm kết thúc', $errors),
            'required_count' => Validator::positiveInteger($_POST['required_count'] ?? null, 'Số người cần', $errors),
        ];
        Validator::orderedRange($data['start_at'], $data['end_at'], 'ca', $errors);
        return [$data, $errors];
    }

    private function positiveId(string $field, string $label): int
    {
        $value = filter_var($_POST[$field] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($value === false) {
            throw new HttpException(422, $label . ' không hợp lệ.');
        }
        return (int) $value;
    }

    private function text(string $field, string $fallback): string
    {
        $value = trim(is_string($_POST[$field] ?? null) ? $_POST[$field] : '');
        return $value !== '' ? substr($value, 0, 1000) : $fallback;
    }
}
