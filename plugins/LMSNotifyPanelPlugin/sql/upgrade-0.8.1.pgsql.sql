CREATE TABLE IF NOT EXISTS lnp_sms_delivery (
 itemid integer PRIMARY KEY REFERENCES messageitems(id) ON DELETE CASCADE,
 idx varchar(64) NOT NULL UNIQUE,
 number varchar(11) NOT NULL,
 externalid varchar(32),
 gateway_status varchar(20) NOT NULL DEFAULT 'SUBMITTING',
 updated_at bigint NOT NULL,
 report_at bigint NOT NULL DEFAULT 0
);
CREATE INDEX IF NOT EXISTS lnp_sms_delivery_external_idx ON lnp_sms_delivery(externalid);
CREATE TABLE IF NOT EXISTS lnp_sms_notices (
 eventid bigint PRIMARY KEY REFERENCES lnp_history(id) ON DELETE CASCADE,
 customerid integer NOT NULL,cycle_key varchar(64) NOT NULL,
 origin varchar(16) NOT NULL,items text NOT NULL,finished integer NOT NULL DEFAULT 0
);
