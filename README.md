# stealth-grid

A turn-based stealth game written in pure PHP.

There is **no JavaScript** in this project.

Move one tile at a time, study guard patrols, stay outside their vision cones, collect keycards and reach the exit.

Every player action advances the guards.

## Features

- pure PHP
- no JavaScript
- server-rendered 15×11 maps
- 4 handcrafted levels
- guard patrol routes
- directional guard facing
- triangular vision cones
- walls and doors block line of sight
- keycards
- locked security doors
- wait action
- global alert state
- chase mode after detection
- BFS shortest-path guard AI
- alarm cooldown
- multiple guards
- level progression
- win / loss state
- turns counter
- spotted counter
- session statistics
- best completed run
- fewest detections
- CSRF protection
- POST / Redirect / GET
- no framework
- no database

## Requirements

PHP 8.1+

## Run

```bash
php -S localhost:8000
```

Open:

```text
http://localhost:8000
```

## Controls

The controls are normal HTML forms:

```text
↑   move up
↓   move down
←   move left
→   move right

Wait one turn
```

There are no keyboard listeners because the project intentionally contains no JavaScript.

## Guard vision

Each guard has:

- a facing direction
- a vision range
- a patrol route

The visible area expands as distance increases, creating a grid-based vision cone.

Walls and closed doors block line of sight.

The map shows current vision cells so the player can plan the next server-side turn.

## Alert mode

If a guard sees the player:

```text
ALERT = 3 turns
```

During alert mode all guards temporarily stop following their normal patrol routes.

Instead they use a shortest-path search toward the player's current position.

If the player avoids detection long enough, the alert counter reaches zero and normal patrols resume.

## Pathfinding

Guard chase movement uses breadth-first search (BFS).

Blocked cells include:

- walls
- closed doors
- other guards

This makes the chase logic a real server-side pathfinding system rather than random movement.

## Keycards and doors

Some levels contain:

```text
◆   keycard
▥   locked door
```

Walking onto a keycard collects it.

Without a keycard, a security door blocks movement.

With a keycard, entering the door opens it permanently for the current level.

## Levels

The game contains four areas:

```text
1. Night Shift
2. Keycard Hall
3. Crossfire
4. The Vault
```

Later levels add:

- more guards
- longer patrols
- tighter corridors
- doors
- overlapping vision cones

## Pure PHP architecture

The entire game state is stored in:

```php
$_SESSION['stealth_game']
```

PHP handles:

- movement
- collision
- keycard collection
- doors
- level progression
- guard patrols
- line of sight
- vision cones
- alert detection
- BFS chase pathfinding
- win / loss logic
- persistent session statistics

Each turn is a regular HTML `POST` request followed by a redirect.

## Project structure

```text
stealth-grid/
├── index.php
├── functions.php
├── style.css
└── README.md
```

## GitHub Pages

GitHub Pages cannot execute PHP.

Use PHP-capable hosting, a VPS, Docker/PHP-Apache, or PHP's built-in development server.

## License

MIT
