[![](https://poggit.pmmp.io/shield.state/RedBluePill)](https://poggit.pmmp.io/p/RedBluePill)
# Red Pill Blue Pill

Red Pill Blue Pill is a family-friendly PocketMine-MP plugin that presents players with a cinematic, one-time choice when they first join the server. Each choice grants permanent, balanced abilities designed to feel meaningful without breaking gameplay.

This plugin is ideal for family servers, kid-friendly communities, and small SMP worlds.

---

## Features

- Cinematic first-join intro using titles and sounds
- Two permanent choices with distinct abilities
- Lightweight particle effects that follow the player
- Parent/admin-only reset command
- Simple mechanics designed for younger players

---

## Pill Choices

### Red Pill — Brave Explorer

- Speed I
- Night Vision
- Fire Resistance
- Slightly reduced maximum health for balance
- Green sparkle particle effect

### Blue Pill — Unstoppable Hero

- Resistance I
- Regeneration
- Saturation
- Increased maximum health
- Heart particle effect

Each player may choose only once unless reset by an admin.

---

## Commands

| Command | Description | Permission |
|--------|-------------|------------|
| `/redochoice` | Reset your pill choice | `redbluepill.redo` |

---

## Permissions

```yaml
redbluepill.redo:
  default: op
