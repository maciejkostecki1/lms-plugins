CREATE TABLE IF NOT EXISTS net24_ksef_erste_export_status (
    division_id bigint NOT NULL,
    invoice_id bigint NOT NULL,
    last_exported_at bigint NOT NULL,
    PRIMARY KEY (division_id, invoice_id)
);
