-- Applied once to site 1400 by the prelaunch settings hook (20-cms-site-settings).
REPLACE INTO globals (gl_name, gl_index, gl_value) VALUES ('cmsvt_collections_hide_agency_export', 0, '1');

-- Per-unit prices (applied after the upgrade, before Apache starts, so 7.0.1
-- never bills them as whole lines). Production's prices were whole doses for
-- the units it forced; each is that price ÷ the usual units. Fixed values:
-- safe to re-apply. Agreed with the practice; re-applied if this file is edited.
UPDATE prices p JOIN codes c ON c.id = p.pr_id
   SET p.pr_price = CASE c.code
         WHEN 'J0178' THEN 2500.00   -- Eylea, $5,000 / 2
         WHEN 'J0177' THEN 1000.00   -- Eylea HD, $8,000 / 8
         WHEN 'J2777' THEN 100.00    -- Vabysmo, $6,000 / 60
         WHEN 'Q5124' THEN 640.00    -- Byooviz, $3,200 / 5
         WHEN 'C9257' THEN 12.00     -- bevacizumab small dose, $60 / 5
       END
 WHERE p.pr_selector = '' AND p.pr_level = 'standard'
   AND c.code IN ('J0178', 'J0177', 'J2777', 'Q5124', 'C9257')
   AND c.code_type = (SELECT ct_id FROM code_types WHERE ct_key = 'HCPCS');

-- Vabysmo's Inventory NDC had a typo; claims were billed with 50242-0096-06.
UPDATE drugs SET ndc_number = '50242-0096-06' WHERE ndc_number = '450242-0096-86';

-- Usual units per dose, from each drug's related code.
UPDATE drugs SET billing_units = CASE related_code
         WHEN 'HCPCS:C9257' THEN 5
         WHEN 'HCPCS:J0177' THEN 8
         WHEN 'HCPCS:J0178' THEN 2
         WHEN 'HCPCS:J2777' THEN 60
         WHEN 'HCPCS:Q5124' THEN 5
         WHEN 'HCPCS:J9035' THEN 1
       END
 WHERE active = 1
   AND related_code IN ('HCPCS:C9257', 'HCPCS:J0177', 'HCPCS:J0178', 'HCPCS:J2777', 'HCPCS:Q5124', 'HCPCS:J9035');

-- NDC unit and quantity: production sends UN 1 (one vial/syringe) for all.
UPDATE drugs SET ndc_uom = 'UN', ndc_quantity = 1
 WHERE active = 1
   AND related_code IN ('HCPCS:C9257', 'HCPCS:J0177', 'HCPCS:J0178', 'HCPCS:J2777', 'HCPCS:Q5124', 'HCPCS:J9035');
-- Sample or free drug: the biller still overrides the line's fee (production
-- billed those at a token amount by hand); the fee starts at price × units.
