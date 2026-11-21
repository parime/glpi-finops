### Read a Sangfor HCI platform (SCP)

1. On Sangfor SCP (6.3.0 or later, the console that sees all the nodes), create an operator
   account with only **read** rights on the virtual machines and the hosts. The plugin never
   starts, stops or changes anything. A single aSV node, without SCP, is not read yet.
2. **Setup > Plugins > FinOps**, **Cost sources** tab, **Sangfor SCP platforms** card: **Add a
   Sangfor SCP platform**, give a name, the entity, the address (`https://scp.example.org`, no
   path), the user name and the password (encrypted by GLPI, never shown again). Leave **Accept a
   self-signed certificate** unticked, unless SCP keeps its factory certificate: installing the
   authority of the certificate on the GLPI server is safer.
3. Fill in the **price grid** (per month: one vCPU, 1 GB of memory, 1 GB of disk) to get a cost,
   or leave 0 for a plain inventory. vCPU and memory count only when the machine is not powered
   off; the disk always counts.
4. **Management > FinOps > Import**, **Read from Sangfor SCP** card: **Read now**. The inventory
   page opens: nodes, machines, allocations and cost of the month. Reading again creates no
   duplicate; the cost of a month is set by its first read.
5. In the **Asset in GLPI** column, check the proposed computer (same UUID or same name) and click
   **Link**. Without a proposal, **Choose an asset** opens the resources page.
6. If the read fails (login refused, certificate, network), nothing is written: the inventory and
   the costs already read stay, the card shows the reason and the date of the last success.
