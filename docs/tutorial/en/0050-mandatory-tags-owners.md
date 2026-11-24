### Check mandatory tags and owners

1. **Setup > Plugins > FinOps**, **General** tab: enter the **mandatory tags** separated by commas
   (by default `owner, cost-center, env`) and the **owner tag** (by default `owner`), then
   **Save**. An empty field turns the check off.
2. Import a cost file whose `tags` column carries these tags (fictitious data):

   ```csv
   date,provider,account,service,resource,cost,currency,tags
   2026-10-01,aws,123456789012,Amazon EC2,i-0demo01,12.40,USD,owner=a.martin;cost-center=CC42;env=prod
   2026-10-01,aws,123456789012,Amazon S3,bucket-demo,1.10,USD,env=dev
   ```

3. **Management > FinOps > Resources**: the **Mandatory tags** card gives the compliance rate, then
   the detail by provider and by account. The owner is shown in green when its value is the login
   or email of a GLPI user, in orange otherwise, in red when the tag is missing.
4. Turn on **Tagging to fix only** to list the resources to rework. Tags are fixed on the provider
   side: they are read again at the next import.
