-- Only plugin-owned tables; no alterations to LMS tables.
CREATE TABLE lnp_settings (id integer PRIMARY KEY CHECK (id = 1), payload text NOT NULL,
    revision integer NOT NULL DEFAULT 1);
INSERT INTO lnp_settings (id,payload) VALUES (1,'{}');
CREATE TABLE lnp_history (id bigserial PRIMARY KEY, created_at bigint NOT NULL,
    customerid integer NOT NULL, action varchar(32) NOT NULL, result varchar(32) NOT NULL,
    details text NOT NULL, userid integer NOT NULL DEFAULT 0);
CREATE INDEX lnp_history_customer ON lnp_history(customerid, action, created_at);
CREATE TABLE lnp_block_notices (customerid integer NOT NULL,cycle_key varchar(64) NOT NULL,
    sent_at bigint NOT NULL,source_historyid bigint NOT NULL,source_items text NOT NULL,origin varchar(16) NOT NULL DEFAULT 'legacy',PRIMARY KEY(customerid,origin));
CREATE TABLE lnp_blocks (nodeid integer PRIMARY KEY, customerid integer NOT NULL,
    created_at bigint NOT NULL);
CREATE TABLE lnp_exclusions (customerid integer PRIMARY KEY, until_time bigint NOT NULL,
    reason varchar(500) NOT NULL);
CREATE TABLE lnp_billing (assignmentid integer PRIMARY KEY, customerid integer NOT NULL,
    created_at bigint NOT NULL, monthly_base numeric(14,4) NOT NULL,
    source_tariffid integer, source_liabilityid integer);

CREATE TABLE IF NOT EXISTS lnp_sms_settings (id integer PRIMARY KEY CHECK(id=1),payload text NOT NULL,token text NOT NULL DEFAULT '',revision integer NOT NULL DEFAULT 1);
