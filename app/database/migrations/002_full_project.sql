CREATE TABLE IF NOT EXISTS volunteer_profiles (
    user_id BIGINT UNSIGNED NOT NULL,
    volunteer_code VARCHAR(30) NOT NULL,
    phone VARCHAR(30) NULL,
    email VARCHAR(190) NULL,
    notes VARCHAR(1000) NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id),
    UNIQUE KEY uq_volunteer_profiles_code (volunteer_code),
    CONSTRAINT fk_volunteer_profiles_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS volunteer_skills (
    volunteer_id BIGINT UNSIGNED NOT NULL,
    skill_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (volunteer_id, skill_id),
    CONSTRAINT fk_volunteer_skills_user FOREIGN KEY (volunteer_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_volunteer_skills_skill FOREIGN KEY (skill_id) REFERENCES skills (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS availability (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    volunteer_id BIGINT UNSIGNED NOT NULL,
    start_at DATETIME NOT NULL,
    end_at DATETIME NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_availability_volunteer_time (volunteer_id, start_at, end_at),
    CONSTRAINT fk_availability_user FOREIGN KEY (volunteer_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT chk_availability_time CHECK (start_at < end_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS registration_history (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    registration_id BIGINT UNSIGNED NOT NULL,
    action ENUM('registered', 'withdrawn', 'reregistered') NOT NULL,
    occurred_at DATETIME NOT NULL,
    actor_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (id),
    KEY idx_registration_history_registration (registration_id, occurred_at),
    CONSTRAINT fk_registration_history_registration FOREIGN KEY (registration_id) REFERENCES registrations (id) ON DELETE RESTRICT,
    CONSTRAINT fk_registration_history_actor FOREIGN KEY (actor_id) REFERENCES users (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS schedule_guard (
    id TINYINT UNSIGNED NOT NULL,
    schedule_revision BIGINT UNSIGNED NOT NULL DEFAULT 1,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT chk_schedule_guard_singleton CHECK (id = 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO schedule_guard (id, schedule_revision) VALUES (1, 1);

CREATE TABLE IF NOT EXISTS dataset_versions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    code VARCHAR(100) NOT NULL,
    version VARCHAR(30) NOT NULL,
    sha256 CHAR(64) NOT NULL,
    seed BIGINT NULL,
    timezone VARCHAR(80) NOT NULL,
    snapshot_json JSON NOT NULL,
    source VARCHAR(40) NOT NULL DEFAULT 'fixture',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_dataset_versions_hash (sha256),
    KEY idx_dataset_versions_code (code, version)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS allocation_runs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    event_id BIGINT UNSIGNED NULL,
    dataset_version_id BIGINT UNSIGNED NOT NULL,
    algorithm VARCHAR(80) NOT NULL,
    algorithm_version VARCHAR(30) NOT NULL,
    config_json JSON NOT NULL,
    schedule_revision BIGINT UNSIGNED NOT NULL,
    status ENUM('draft', 'published', 'superseded', 'experiment') NOT NULL DEFAULT 'draft',
    metrics_json JSON NOT NULL,
    unassigned_json JSON NOT NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    published_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_allocation_runs_event_status (event_id, status, created_at),
    CONSTRAINT fk_allocation_runs_event FOREIGN KEY (event_id) REFERENCES events (id) ON DELETE RESTRICT,
    CONSTRAINT fk_allocation_runs_dataset FOREIGN KEY (dataset_version_id) REFERENCES dataset_versions (id) ON DELETE RESTRICT,
    CONSTRAINT fk_allocation_runs_creator FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS run_assignments (
    run_id BIGINT UNSIGNED NOT NULL,
    shift_id BIGINT UNSIGNED NOT NULL,
    volunteer_id BIGINT UNSIGNED NOT NULL,
    is_fixed TINYINT(1) NOT NULL DEFAULT 0,
    reason VARCHAR(500) NULL,
    PRIMARY KEY (run_id, shift_id, volunteer_id),
    KEY idx_run_assignments_shift (shift_id),
    KEY idx_run_assignments_volunteer (volunteer_id),
    CONSTRAINT fk_run_assignments_run FOREIGN KEY (run_id) REFERENCES allocation_runs (id) ON DELETE CASCADE,
    CONSTRAINT fk_run_assignments_shift FOREIGN KEY (shift_id) REFERENCES shifts (id) ON DELETE RESTRICT,
    CONSTRAINT fk_run_assignments_volunteer FOREIGN KEY (volunteer_id) REFERENCES users (id) ON DELETE RESTRICT,
    CONSTRAINT chk_run_assignments_fixed CHECK (is_fixed IN (0, 1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS assignments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    shift_id BIGINT UNSIGNED NOT NULL,
    volunteer_id BIGINT UNSIGNED NOT NULL,
    source_run_id BIGINT UNSIGNED NULL,
    status ENUM('assigned', 'confirmed', 'absent', 'cancelled', 'replaced') NOT NULL DEFAULT 'assigned',
    version INT UNSIGNED NOT NULL DEFAULT 1,
    assigned_by BIGINT UNSIGNED NOT NULL,
    assigned_at DATETIME NOT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_assignments_shift_volunteer (shift_id, volunteer_id),
    KEY idx_assignments_volunteer_status (volunteer_id, status),
    KEY idx_assignments_shift_status (shift_id, status),
    CONSTRAINT fk_assignments_shift FOREIGN KEY (shift_id) REFERENCES shifts (id) ON DELETE RESTRICT,
    CONSTRAINT fk_assignments_volunteer FOREIGN KEY (volunteer_id) REFERENCES users (id) ON DELETE RESTRICT,
    CONSTRAINT fk_assignments_run FOREIGN KEY (source_run_id) REFERENCES allocation_runs (id) ON DELETE SET NULL,
    CONSTRAINT fk_assignments_actor FOREIGN KEY (assigned_by) REFERENCES users (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS replacement_requests (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    assignment_id BIGINT UNSIGNED NOT NULL,
    reason VARCHAR(1000) NOT NULL,
    status ENUM('open', 'resolved', 'unfilled') NOT NULL DEFAULT 'open',
    replacement_assignment_id BIGINT UNSIGNED NULL,
    processed_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    resolved_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_replacement_requests_status (status, created_at),
    CONSTRAINT fk_replacement_requests_assignment FOREIGN KEY (assignment_id) REFERENCES assignments (id) ON DELETE RESTRICT,
    CONSTRAINT fk_replacement_requests_replacement FOREIGN KEY (replacement_assignment_id) REFERENCES assignments (id) ON DELETE RESTRICT,
    CONSTRAINT fk_replacement_requests_processor FOREIGN KEY (processed_by) REFERENCES users (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    actor_id BIGINT UNSIGNED NULL,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(80) NOT NULL,
    entity_id BIGINT UNSIGNED NULL,
    before_json JSON NULL,
    after_json JSON NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_audit_logs_entity (entity_type, entity_id, created_at),
    CONSTRAINT fk_audit_logs_actor FOREIGN KEY (actor_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
