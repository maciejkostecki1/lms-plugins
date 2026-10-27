CREATE TABLE IF NOT EXISTS lnp_sms_settings (id integer PRIMARY KEY CHECK(id=1),payload text NOT NULL,token text NOT NULL DEFAULT '',revision integer NOT NULL DEFAULT 1);
