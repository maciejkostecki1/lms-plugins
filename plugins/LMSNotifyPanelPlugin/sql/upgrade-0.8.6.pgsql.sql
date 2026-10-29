CREATE TABLE IF NOT EXISTS lnp_runtime (name varchar(64) PRIMARY KEY,value text NOT NULL);
CREATE INDEX IF NOT EXISTS lnp_sms_notices_pending_customer ON lnp_sms_notices(customerid,eventid) WHERE finished=0;
CREATE INDEX IF NOT EXISTS lnp_history_customer_latest ON lnp_history(customerid,created_at DESC,id DESC);
INSERT INTO lnp_runtime(name,value) VALUES('invoice_catchup_from',to_char((CURRENT_TIMESTAMP AT TIME ZONE 'Europe/Warsaw')::date+1,'YYYYMMDD')) ON CONFLICT(name) DO NOTHING;
