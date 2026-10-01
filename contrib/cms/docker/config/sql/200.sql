-- Applied once to site 200 by the postupgrade hook (see ../README.md).
-- Billing Manager: default to the last 2 months of service dates.
REPLACE INTO globals (gl_name, gl_index, gl_value) VALUES ('cmsvt_billing_manager_dos_months', 0, '2');

-- Eligibility (270): the provider and receiver name production hardcoded in
-- commit f5de47125c (src/Billing/EDI270.php). Fill in, confirm the user's NPI
-- matches, then uncomment.
-- REPLACE INTO globals (gl_name, gl_index, gl_value) VALUES ('cmsvt_elig_provider_id', 0, '<users.id>');
-- REPLACE INTO globals (gl_name, gl_index, gl_value) VALUES ('cmsvt_elig_receiver_name', 0, '<receiver name>');
