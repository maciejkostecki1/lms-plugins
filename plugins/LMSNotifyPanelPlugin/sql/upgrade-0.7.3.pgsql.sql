ALTER TABLE lnp_block_notices DROP CONSTRAINT IF EXISTS lnp_block_notices_pkey;
ALTER TABLE lnp_block_notices ADD PRIMARY KEY(customerid,origin);
