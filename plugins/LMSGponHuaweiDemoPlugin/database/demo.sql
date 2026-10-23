CREATE TABLE IF NOT EXISTS huawei_gpon_demo_olts (
 id BIGSERIAL PRIMARY KEY, name VARCHAR(150) NOT NULL, host VARCHAR(45) NOT NULL UNIQUE,
 enabled BOOLEAN NOT NULL DEFAULT FALSE, snmp_timeout INTEGER NOT NULL DEFAULT 5,
 snmp_retries INTEGER NOT NULL DEFAULT 1, created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(), updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE TABLE IF NOT EXISTS huawei_gpon_demo_snmp_secrets (
 olt_id BIGINT PRIMARY KEY REFERENCES huawei_gpon_demo_olts(id) ON DELETE CASCADE,
 community_ro TEXT, updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE TABLE IF NOT EXISTS huawei_gpon_demo_settings (
 id BIGSERIAL PRIMARY KEY, olt_id BIGINT NOT NULL REFERENCES huawei_gpon_demo_olts(id),
 setting_key VARCHAR(100) NOT NULL, setting_value TEXT NOT NULL, updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(), UNIQUE(olt_id,setting_key)
);
CREATE TABLE IF NOT EXISTS huawei_gpon_demo_ports (
 olt_id BIGINT NOT NULL REFERENCES huawei_gpon_demo_olts(id), olt_host VARCHAR(255) NOT NULL, ifindex BIGINT NOT NULL,
 frame INTEGER NOT NULL, slot INTEGER NOT NULL, port INTEGER NOT NULL, ifname VARCHAR(128) NOT NULL,
 last_seen TIMESTAMPTZ NOT NULL DEFAULT NOW(), PRIMARY KEY(olt_id,ifindex)
);
CREATE TABLE IF NOT EXISTS huawei_gpon_demo_onts (
 id BIGSERIAL PRIMARY KEY, olt_id BIGINT NOT NULL REFERENCES huawei_gpon_demo_olts(id), olt_host VARCHAR(255) NOT NULL,
 ifindex BIGINT NOT NULL, ont_id INTEGER NOT NULL, serial_hex VARCHAR(32), description TEXT,
 line_profile VARCHAR(128), service_profile VARCHAR(128), online_state INTEGER, distance_m INTEGER, last_down_cause INTEGER,
 UNIQUE(olt_id,ifindex,ont_id)
);
CREATE TABLE IF NOT EXISTS huawei_gpon_demo_samples (
 id BIGSERIAL PRIMARY KEY, ont_ref BIGINT NOT NULL REFERENCES huawei_gpon_demo_onts(id) ON DELETE CASCADE,
 sampled_at TIMESTAMPTZ NOT NULL DEFAULT NOW(), temperature_c NUMERIC, rx_dbm NUMERIC, tx_dbm NUMERIC,
 voltage_mv NUMERIC, laser_bias_raw NUMERIC, olt_rx_dbm NUMERIC
);
CREATE INDEX IF NOT EXISTS huawei_gpon_demo_demo_samples_ref_time_idx ON huawei_gpon_demo_samples(ont_ref,sampled_at DESC,id DESC);
