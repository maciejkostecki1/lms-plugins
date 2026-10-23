ALTER TABLE lnp_bulk_jobs ADD COLUMN IF NOT EXISTS mode varchar(20) NOT NULL DEFAULT 'plain';
ALTER TABLE lnp_bulk_items ADD COLUMN IF NOT EXISTS history_hash varchar(64);
