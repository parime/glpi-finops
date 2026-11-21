# FinOps for GLPI

[🇫🇷 Français](README.md)

GLPI plugin that follows the **cost of cloud and virtualised resources** right inside the asset
inventory: costs imported by provider and by month, resources linked to GLPI computers and
appliances, then (roadmap) showback per entity, under-used resources and overspending alerts.

## Overview

- **Cost import** from a CSV file in the common format (date, provider, account or subscription,
  service, resource, cost, currency, tags): any billing export from AWS, Azure, Google Cloud,
  OVHcloud, a hosting company or a hypervisor can be brought back to it. Preview before writing,
  re-runnable import without duplicates, import log.
- **Table by provider and by month**, one table per currency (the plugin never adds up two
  currencies).
- **Linking a resource** (instance, VM, database...) to a GLPI asset, with a "FinOps" tab on the asset.
- **Extensible sources**: a source (AWS, Azure, GCP, OVHcloud, Proxmox, Hyper-V, VMware, Sangfor,
  H3C...) is a `src/Source/<Name>Source.php` class, discovered without any central list.

Roadmap: [repository issues](https://github.com/parime/glpi-finops/issues).

## Principles

- **Read-only** towards providers and hypervisors: the plugin reads, it never stops, deletes or
  changes anything on their side.
- **Nothing changes by itself in GLPI**: the plugin proposes, a human validates.
- **Entities respected**: each line belongs to the entity chosen at import; everyone only sees the
  entities of their session.
- **No outgoing call** without an explicit setting by an administrator; secrets encrypted.
- Settings in the plugin page only, a single menu entry (Management > FinOps).

The plugin follows the [common charter of the plugins](https://github.com/parime/glpi-plugin-template/blob/main/CHARTE.md).

## Compatibility

- GLPI 11 and 12 (a single code base, see `src/Compat/`).
- PHP 8.2 to 8.5 (GLPI 12: 8.3 to 8.5).

## Installation

1. Download the archive of the latest version (*Releases* tab) and extract it into `plugins/`: the
   folder must be named `finops`.
2. Setup > Plugins: install then enable **FinOps**.
3. Profiles that can change the GLPI configuration get every right of the plugin; the others are set
   in Administration > Profiles > **FinOps** tab.

## Usage

- **Setup > Plugins > FinOps**: default currency, largest file accepted, available cost sources.
- **Management > FinOps**: costs by provider and by month, resources, import.

The step-by-step tutorial is assembled in `docs/TUTORIAL.md` at each release, from `docs/tutorial/`.

## Documentation

- [CHANGELOG.md](CHANGELOG.md): changes by version.
- [CONTRIBUTING.md](CONTRIBUTING.md): contributing, and adding a feature without conflicts.
- [SECURITY.md](SECURITY.md): reporting a vulnerability.

## Reuse and citation

This plugin is licensed under **GPL-3.0-or-later**. Any copy or adaptation, even of a single file,
keeps the licence header of each file and the `NOTICE` file, stays under the GPL with its source
code, and states the changes. To cite the project, see `CITATION.cff`.

## License

GNU General Public License v3.0 or later: see [LICENSE](LICENSE).
