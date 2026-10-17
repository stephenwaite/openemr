-- Applied once to site 4800 by the prelaunch settings hook (20-cms-site-settings).
REPLACE INTO globals (gl_name, gl_index, gl_value) VALUES ('cmsvt_lab_results_per_lab', 0, '50');

-- Drugs and fee sheet list (after the upgrade, which adds billing_units,
-- ndc_uom and ndc_quantity). Prices stay per unit, set in production
-- (J1010 $1.00 per mg, J3301 $5.00 per 10 mg). Products are found by NDC.
-- Usual dose: 40 mg = 1 mL; the biller adjusts units and NDC quantity.
UPDATE drugs SET billing_units = 40, ndc_uom = 'ML', ndc_quantity = 1
 WHERE active = 1 AND ndc_number IN ('0009-0280-02', '0703-0043-01');   -- methylprednisolone acetate 40 mg/mL, J1010
UPDATE drugs SET billing_units = 4, ndc_uom = 'ML', ndc_quantity = 1
 WHERE active = 1 AND ndc_number = '70121-1168-1';                      -- triamcinolone acetonide 40 mg/mL, J3301
-- Each J1010 list entry names its product, so the line gets that vial's NDC.
UPDATE fee_sheet_options f JOIN drugs d ON d.active = 1 AND d.ndc_number = '0703-0043-01'
   SET f.fs_option = REPLACE(f.fs_option, 'triamcinolone', 'methylprednisolone'),
       f.fs_codes = CONCAT('HCPCS|J1010|', d.drug_id)
 WHERE f.fs_codes LIKE 'HCPCS|J1010|%' AND f.fs_option LIKE '%Teva%';
UPDATE fee_sheet_options f JOIN drugs d ON d.active = 1 AND d.ndc_number = '0009-0280-02'
   SET f.fs_codes = CONCAT('HCPCS|J1010|', d.drug_id)
 WHERE f.fs_codes LIKE 'HCPCS|J1010|%' AND f.fs_option LIKE '%Depo-Medrol%';
