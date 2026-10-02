-- Applied once to site 1500 by the prelaunch settings hook (see ../README.md).
-- Payers that must not receive NDCs (25169 does receive them).
REPLACE INTO globals (gl_name, gl_index, gl_value) VALUES ('cmsvt_claim_ndc_skip_payer_ids', 0, '87726, 39026, TREST, PAMCD');
-- "Incident to": bill every claim under user 6.
REPLACE INTO globals (gl_name, gl_index, gl_value) VALUES ('cmsvt_claim_rendering_provider_id', 0, '6');
