ALTER TABLE replacement_requests
    ADD COLUMN resolution_note VARCHAR(1000) NULL AFTER status;

ALTER TABLE allocation_runs
    ADD COLUMN comparison_group VARCHAR(40) NULL AFTER algorithm_version,
    ADD COLUMN project_version VARCHAR(80) NOT NULL DEFAULT 'unknown' AFTER comparison_group,
    ADD KEY idx_allocation_runs_comparison (comparison_group, created_at);
