### Read the costs of an OVHcloud account

1. On the OVHcloud API of your region (Europe or Canada), create an application and a consumer key
   with only **GET** rights on `/me`, `/me/bill/*` and `/cloud/project/*/usage/*`. The plugin never
   changes anything at OVHcloud.
2. **Setup > Plugins > FinOps**, **Cost sources** tab, **OVHcloud accounts** card: **Add an OVHcloud
   account**, give a name, the entity, the region, the three keys and, if needed, the identifiers of
   the Public Cloud projects (32 characters). The keys are encrypted by GLPI and never shown again;
   to change one, type it again, an empty field keeps it.
3. **Management > FinOps > Import**, **Read from OVHcloud** card: click **Read now**. The bills and
   the consumption of the listed projects come into the costs, like a CSV import. Reading again
   creates no duplicate.
4. If the read fails (keys refused, network, OVHcloud unavailable), nothing is written: the costs
   already read stay, the card shows the reason and the date of the last success.
