CREATE TABLE IF NOT EXISTS lnp_block_notices (
    customerid integer PRIMARY KEY,
    cycle_key varchar(64) NOT NULL,
    sent_at bigint NOT NULL,
    source_historyid bigint NOT NULL,
    source_items text NOT NULL
);
