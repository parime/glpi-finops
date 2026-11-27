### Confirm the proposed links to GLPI assets

1. **Setup > Plugins > FinOps**, **General** tab: the **Matching rules for assets** field gives the
   order of the rules (by default `instance_id, vm_uuid, serial, name, glpi_id_tag`). Removing a rule
   turns it off; an empty field turns the proposals off.
2. Create in GLPI a test computer whose **serial number** is `SN-DEMO-01`, then import a cost file
   (fictitious data):

   ```csv
   date,provider,account,service,resource,cost,currency,tags
   2026-10-01,ovhcloud,projet-demo,Public Cloud,SN-DEMO-01,3.10,EUR,
   2026-10-01,aws,123456789012,Amazon EC2,i-0demo02,12.40,USD,glpi_id=Computer:12
   ```

3. **Management > FinOps > Resources**: the resources not linked yet show **Proposed:** and the asset
   found, with the rule (**Found by: Serial number**). "Several assets match" flags an ambiguous
   rule: link that resource by hand.
4. Check the right proposals (the box in the header checks them all), then
   **Link the checked resources to the proposed assets**. For a single resource: open it, check the
   asset with **Open the proposed asset**, then **Confirm the proposal**.
5. The **Resources not linked to an asset** card of the Costs page shows what is left to do.
