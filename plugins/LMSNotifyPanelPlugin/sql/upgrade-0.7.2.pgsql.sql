ALTER TABLE lnp_block_notices ADD COLUMN IF NOT EXISTS origin varchar(16) NOT NULL DEFAULT 'legacy';
ALTER TABLE lnp_bulk_jobs ADD COLUMN IF NOT EXISTS start_countdown integer NOT NULL DEFAULT 0;
