### Import costs and link a resource to a computer

1. Prepare a CSV file (fictitious data) whose first line gives the column names:

   ```csv
   date,provider,account,service,resource,cost,currency,tags
   2026-10-01,aws,123456789012,Amazon EC2,i-0demo01,12.40,USD,team=web;env=prod
   2026-10-01,ovhcloud,projet-demo,Public Cloud,b2-7-demo,3.10,EUR,
   ```

   The `date`, `provider` and `cost` columns are required; other names are accepted
   (`subscription`, `amount`, `resource_id`...). Separator: comma, semicolon or tab.
2. **Management > FinOps > Import**: choose the entity, upload the file, click **Preview**. The
   preview counts the new, already imported and rejected lines (with the line number and the
   reason); nothing is written yet.
3. Click **Import the new lines**. Importing the same file again creates no duplicate; the log at
   the bottom of the page keeps every import, which can be deleted with its lines.
4. **Management > FinOps > Costs**: one table per currency, months as rows, providers as columns.
   The orange card counts the resources not yet linked to an asset.
5. **Management > FinOps > Resources**: click a resource marked "Not linked", choose the asset type
   (computer, appliance, network device) and the asset, then **Link**. The computer gets a
   **FinOps** tab with its resources and their cost by month.
