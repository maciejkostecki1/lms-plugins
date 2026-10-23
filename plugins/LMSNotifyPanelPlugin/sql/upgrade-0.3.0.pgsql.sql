CREATE TABLE IF NOT EXISTS lnp_billing (assignmentid integer PRIMARY KEY, customerid integer NOT NULL,
    created_at bigint NOT NULL, monthly_base numeric(14,4) NOT NULL,
    source_tariffid integer, source_liabilityid integer);
