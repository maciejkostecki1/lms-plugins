CREATE TABLE IF NOT EXISTS lnp_bulk_jobs (
    id bigserial PRIMARY KEY,created_at bigint NOT NULL,userid integer NOT NULL,
    revision integer NOT NULL,status varchar(20) NOT NULL,total integer NOT NULL);
CREATE TABLE IF NOT EXISTS lnp_bulk_items (
    jobid bigint NOT NULL REFERENCES lnp_bulk_jobs(id),customerid integer NOT NULL,
    status varchar(20) NOT NULL,details text NOT NULL DEFAULT '',
    PRIMARY KEY(jobid,customerid));
