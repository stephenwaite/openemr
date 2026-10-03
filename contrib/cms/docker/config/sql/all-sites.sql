-- Applied once to every site by the prelaunch settings hook (see ../README.md).
-- Statements run in order and aren't wrapped in a transaction; each one is
-- safe to repeat, so a file that fails partway can simply be applied again.

-- oe-module-cmsvt: registered, installed and enabled (Module Manager does the
-- same; its table.sql is applied separately by the hook).
INSERT INTO modules (mod_name, mod_directory, mod_parent, mod_type, mod_active, mod_ui_name,
    mod_relative_link, mod_ui_order, mod_ui_active, mod_description, mod_nick_name, mod_enc_menu,
    directory, date, sql_run, type, sql_version, acl_version)
SELECT 'CMS Vermont', 'oe-module-cmsvt', '', '', 1, 'CMS Vermont', '', 0, 0,
    'CMS Vermont customizations', '', '', '', NOW(), 1, 0, '', ''
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM modules WHERE mod_directory = 'oe-module-cmsvt');

-- cms-rel-701 added x12_submitter_id as tinyint(1) (upstream #6456) without
-- upstream's fix to smallint(6) (#6459); the upgrade won't change an
-- existing column. It holds a users.id, so tinyint caps it at 127. Widening
-- keeps every value and is safe to repeat.
ALTER TABLE x12_partners MODIFY x12_submitter_id smallint(6) DEFAULT NULL;

-- Production never balanced claims (cluster 9); the upstream default is on.
REPLACE INTO globals (gl_name, gl_index, gl_value) VALUES ('force_claim_balancing', 0, '0');

-- Default units for drug codes (cluster 7). Defaults the biller can change on
-- the line; only codes that exist are touched. Prices are per unit since
-- #14330: convert these codes' prices first (DEPLOYMENT.md section 6).
UPDATE codes SET units = 5  WHERE code_type = (SELECT ct_id FROM code_types WHERE ct_key = 'HCPCS') AND code IN ('C9257', 'Q5124');
UPDATE codes SET units = 2  WHERE code_type = (SELECT ct_id FROM code_types WHERE ct_key = 'HCPCS') AND code = 'J0178';
UPDATE codes SET units = 8  WHERE code_type = (SELECT ct_id FROM code_types WHERE ct_key = 'HCPCS') AND code = 'J0177';
UPDATE codes SET units = 60 WHERE code_type = (SELECT ct_id FROM code_types WHERE ct_key = 'HCPCS') AND code = 'J2777';

-- Statements (cluster 10): every site printed the CMS layout in production
-- (library/statement.inc.php in cms-rel-701), which is PDF Custom here. Each
-- site also needs cms-rel-840's statement.inc.php in config/sites/<site>/
-- (see config/sites/README.md). Sites keep their production statement_logo
-- and letterhead; set the logo in <site>.sql only for a site without one.
REPLACE INTO globals (gl_name, gl_index, gl_value) VALUES ('statement_appearance', 0, '2');
-- REPLACE INTO globals (gl_name, gl_index, gl_value) VALUES ('statement_logo', 0, '<letterhead>.png');
