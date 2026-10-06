-- Applied once to site 5200 (podiatry) by the prelaunch settings hook (20-cms-site-settings).
-- Label the encounter onset date "Date Last Seen".
REPLACE INTO globals (gl_name, gl_index, gl_value) VALUES ('cmsvt_encounter_date_last_seen', 0, '1');
-- Routine foot care claim rules for the NPI this site bills as.
REPLACE INTO globals (gl_name, gl_index, gl_value) VALUES ('cmsvt_claim_routine_foot_care_npis', 0, '1134268188');
