# Quince Link Blocks

**Blocks of links for the footer of a PrestaShop 9.2 shop.**

Quince Link Blocks lets the shop build the link lists of its footer: a heading and
the links under it, pointing at categories, content pages or addresses of your own.
A block does not draw its own column. It names a hook, and the Quince Engine footer
places that hook in a column, so the columns can be rearranged without touching the
blocks.

> Quince Link Blocks is a free, open-source module (OSL-3.0). There is nothing to
> buy. If it saves you time, you can support development, see
> [Supporting the project](#supporting-the-project).

---

## Features

- **Blocks with a heading.** Each block has a heading in every language, can show or
  hide that heading, and can be switched off without deleting it.
- **Three kinds of links.** A category, a content page or an address of your own.
  An address of your own can point somewhere else in each language.
- **Names stay current.** A link to a category or a content page keeps only its
  number. The name and the address are read from the catalogue when the page is
  shown, so renaming a category changes the footer at once. A caption can replace
  the catalogue name when needed.
- **New tab per link.** Any link can open in a new tab.
- **Order by dragging.** Blocks and the links inside them are reordered by dragging.
- **Placement by hook.** A block names the hook it appears in. The module registers
  itself on every hook its blocks use, plus `displayFooter` as a starting point.
- **Multilingual.** English base with a full Polish translation, on PrestaShop's
  standard translation system.

## Requirements

| Requirement | Version |
|-------------|---------|
| PrestaShop  | 9.2 (tested on 9.2.0) |
| PHP         | 8.4 (tested), 8.1 or newer required |
| Quince Engine | required |

Quince Engine hosts the module's settings screen and its footer settings decide which
hook stands in which column. The module declares it as a dependency, so PrestaShop
refuses to install Quince Link Blocks without it.

## Installation

The release ZIP already contains everything the module needs. Composer and the
command line are not required.

**From the back office**

1. Download the latest **`quince_linkblocks.zip`** from the
   [**Releases**](https://github.com/quinceproject/quince_linkblocks/releases) page.
2. In the back office go to **Modules → Module Manager → Upload a module** and
   choose the ZIP. PrestaShop installs it.

**By copying the files to the server**

1. Unpack the release ZIP. It contains a `quince_linkblocks` folder.
2. Upload that folder to the `modules/` directory of the shop, for example by FTP.
3. In **Modules → Module Manager** find Quince link blocks and click **Install**.

**From Quince Engine** *(coming soon)*

The module list in Quince Engine will install the module straight from its release,
without downloading anything by hand.

**With Composer** *(coming soon)*

Installing with `composer require quinceproject/linkblocks` will work once the
package is published on Packagist.

> ⚠️ **Do not** use the green **Code → Download ZIP** button on this page. That
> archive contains the source only and is **missing the `vendor/` folder**, so the
> module will not run. Always install from a
> [**Release**](https://github.com/quinceproject/quince_linkblocks/releases).

Uninstalling the module removes the blocks and their links.

## Configuration

Open **Quince project → Link blocks** in the back office menu, or click
**Configure** next to the module in the Module Manager.

1. Click **Add block** and give it a heading in each language.
2. In **Hook**, enter the hook you set for a footer column in the Quince Engine
   footer settings.
3. Add links: pick a category or a content page, or type an address of your own.
4. Drag blocks and links into order and save.

## Architecture

- **`LinkBlocks`** keeps blocks and links in four tables (`quince_linkblock`,
  `quince_linkblock_lang`, `quince_linkblock_link`, `quince_linkblock_link_lang`)
  and resolves category and content page links into names and addresses.
- **One hook method for every hook.** The module answers any `display*` hook it is
  registered on and shows the blocks assigned to it.
- **Symfony controller and Twig** for the settings screen, inside the Quince Engine
  back office shell. The module reports itself to the engine through the
  `actionQuinceRegisterFeature` hook.

## Development / building from source

This repository tracks **source only**. The `vendor/` directory is git-ignored, so a
`git clone` is **not** directly installable. **Start with `composer install`**: the
development environment mounts module directories straight from disk, and without
that step a fresh clone gives a broken back office.

```bash
git clone https://github.com/quinceproject/quince_linkblocks.git
cd quince_linkblocks
composer install
```

The folder must be called `quince_linkblocks`, the same as the module. A clone of
this repository already has that name.

To build an installable archive, zip the `quince_linkblocks/` folder **with its
`vendor/` directory included**, leaving out `.git/` and `.gitignore`.

## License

Code: [Open Software License 3.0 (OSL-3.0)](LICENSE). It is a reciprocal licence:
whoever modifies and distributes this code has to publish the changes under the same
licence and keep the original copyright and author notices.

**The name "Quince" is not covered by the code licence.** A fork may use the code; it
may not use the name.

© 2026 Marcin Gajewski

## Supporting the project

Quince modules are free downloads and there is nothing to buy. If the module is useful
to you, you can support its development by buying me a coffee:

**[buymeacoffee.com/marcingajewski](https://buymeacoffee.com/marcingajewski)**
