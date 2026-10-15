-- Applied once to site 200 by the prelaunch settings hook (20-cms-site-settings).
-- Billing Manager: default to the last 2 months of service dates.
REPLACE INTO globals (gl_name, gl_index, gl_value) VALUES ('cmsvt_billing_manager_dos_months', 0, '2');

-- Eligibility (270): the provider and receiver name production hardcoded in
-- commit f5de47125c (src/Billing/EDI270.php). Fill in, confirm the user's NPI
-- matches, then uncomment.
-- REPLACE INTO globals (gl_name, gl_index, gl_value) VALUES ('cmsvt_elig_provider_id', 0, '<users.id>');
-- REPLACE INTO globals (gl_name, gl_index, gl_value) VALUES ('cmsvt_elig_receiver_name', 0, '<receiver name>');

-- MIPS quality measures report (Reports > Clinic): site 200 only (Stephen, 2026-10-15).
-- Replaces the 509-only switch that the 2026-10-15 hot-patch used.
REPLACE INTO globals (gl_name, gl_index, gl_value) VALUES ('cmsvt_mips_reports', 0, '1');
DELETE FROM globals WHERE gl_name = 'cmsvt_mips_509_report';
