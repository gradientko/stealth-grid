<?php
declare(strict_types=1);

const ALERT_TURNS = 3;
const MAX_LEVEL = 4;

function levelDefinitions(): array
{
    return [
        1 => [
            'name' => 'Night Shift',
            'map' => [
                '###############',
                '#.............#',
                '#.#####.#####.#',
                '#.............#',
                '#.###.....###.#',
                '#.............#',
                '#.#####.#####.#',
                '#.............#',
                '#.###.....###.#',
                '#............E#',
                '###############',
            ],
            'player' => [1, 1],
            'guards' => [
                [
                    'x' => 7, 'y' => 5, 'dir' => 'E', 'range' => 5,
                    'route' => [[7,5],[11,5],[11,7],[7,7],[3,7],[3,5]],
                ],
            ],
        ],
        2 => [
            'name' => 'Keycard Hall',
            'map' => [
                '###############',
                '#.....#.......#',
                '#.....#.......#',
                '#..K..#.......#',
                '#.....#.......#',
                '#.....D.......#',
                '#.....#.......#',
                '#.....#.......#',
                '#.....#......E#',
                '#.....#.......#',
                '###############',
            ],
            'player' => [1, 1],
            'guards' => [
                [
                    'x' => 4, 'y' => 7, 'dir' => 'N', 'range' => 5,
                    'route' => [[4,7],[4,3],[2,3],[2,7]],
                ],
                [
                    'x' => 10, 'y' => 4, 'dir' => 'S', 'range' => 5,
                    'route' => [[10,4],[10,8],[12,8],[12,2],[9,2]],
                ],
            ],
        ],
        3 => [
            'name' => 'Crossfire',
            'map' => [
                '###############',
                '#.....#.......#',
                '#.###.#.#####.#',
                '#...#.#.....#.#',
                '###.#.#####.#.#',
                '#K..#...D...#.#',
                '#.#####.###.#.#',
                '#.......#...#.#',
                '#.#######.###.#',
                '#............E#',
                '###############',
            ],
            'player' => [1, 9],
            'guards' => [
                [
                    'x' => 2, 'y' => 5, 'dir' => 'E', 'range' => 4,
                    'route' => [[2,5],[2,3],[1,3],[1,1],[5,1]],
                ],
                [
                    'x' => 9, 'y' => 7, 'dir' => 'N', 'range' => 5,
                    'route' => [[9,7],[11,7],[11,5],[9,5],[9,3],[11,3]],
                ],
                [
                    'x' => 12, 'y' => 9, 'dir' => 'W', 'range' => 4,
                    'route' => [[12,9],[9,9],[9,7],[11,7],[11,9]],
                ],
            ],
        ],
        4 => [
            'name' => 'The Vault',
            'map' => [
                '###############',
                '#.....#.......#',
                '#.###.#.###.#.#',
                '#.#K#...#...#.#',
                '#.#.#####.###.#',
                '#...D.....#...#',
                '###.#####.#.#.#',
                '#...#.....#.#.#',
                '#.###.#####.#.#',
                '#............E#',
                '###############',
            ],
            'player' => [1, 1],
            'guards' => [
                [
                    'x' => 4, 'y' => 1, 'dir' => 'S', 'range' => 5,
                    'route' => [[4,1],[4,3],[2,3],[2,1]],
                ],
                [
                    'x' => 7, 'y' => 5, 'dir' => 'E', 'range' => 5,
                    'route' => [[7,5],[9,5],[9,3],[7,3],[7,5]],
                ],
                [
                    'x' => 11, 'y' => 7, 'dir' => 'S', 'range' => 5,
                    'route' => [[11,7],[11,9],[13,9],[13,5],[11,5]],
                ],
                [
                    'x' => 6, 'y' => 9, 'dir' => 'E', 'range' => 4,
                    'route' => [[6,9],[10,9],[10,7],[8,7],[6,7]],
                ],
            ],
        ],
    ];
}

function createGame(): array
{
    $game = [
        'level' => 1,
        'turns' => 0,
        'status' => 'playing',
        'message' => 'Stay out of sight and reach the exit.',
        'alert' => 0,
        'keycard' => false,
        'doors_opened' => 0,
        'spotted_count' => 0,
        'settled' => false,
    ];

    loadLevel($game, 1);

    return $game;
}

function loadLevel(array &$game, int $level): void
{
    $definitions = levelDefinitions();

    if (!isset($definitions[$level])) {
        throw new RuntimeException('Unknown level.');
    }

    $definition = $definitions[$level];
    $map = array_map('str_split', $definition['map']);

    $game['level'] = $level;
    $game['level_name'] = $definition['name'];
    $game['map'] = $map;
    $game['player'] = [
        'x' => $definition['player'][0],
        'y' => $definition['player'][1],
    ];

    $game['guards'] = [];

    foreach ($definition['guards'] as $index => $guard) {
        $game['guards'][] = [
            'x' => $guard['x'],
            'y' => $guard['y'],
            'dir' => $guard['dir'],
            'range' => $guard['range'],
            'route' => $guard['route'],
            'route_index' => 0,
            'id' => $index + 1,
        ];
    }

    $game['alert'] = 0;
    $game['message'] = "Level {$level}: {$definition['name']}. Reach the exit.";

    collectCurrentTile($game);
}

function movePlayer(array &$game, int $dx, int $dy): void
{
    if ($game['status'] !== 'playing') {
        return;
    }

    $game['turns']++;

    $x = (int) $game['player']['x'];
    $y = (int) $game['player']['y'];
    $nx = $x + $dx;
    $ny = $y + $dy;

    if (!cellExists($game, $nx, $ny)) {
        $game['message'] = 'You cannot move there.';
        guardPhase($game);
        return;
    }

    $tile = $game['map'][$ny][$nx];

    if ($tile === '#') {
        $game['message'] = 'A wall blocks the way.';
        guardPhase($game);
        return;
    }

    if ($tile === 'D') {
        if (!$game['keycard']) {
            $game['message'] = 'The security door needs a keycard.';
            guardPhase($game);
            return;
        }

        $game['map'][$ny][$nx] = '.';
        $game['doors_opened']++;
        $game['message'] = 'Keycard accepted. The door opens.';
    }

    if (guardAt($game, $nx, $ny) !== null) {
        loseGame($game, 'You walked straight into a guard.');
        return;
    }

    $game['player']['x'] = $nx;
    $game['player']['y'] = $ny;

    collectCurrentTile($game);

    if (($game['map'][$ny][$nx] ?? '#') === 'E') {
        advanceLevel($game);
        return;
    }

    guardPhase($game);
}

function waitTurn(array &$game): void
{
    if ($game['status'] !== 'playing') {
        return;
    }

    $game['turns']++;
    $game['message'] = 'You wait and listen to the patrols.';
    guardPhase($game);
}

function collectCurrentTile(array &$game): void
{
    $x = (int) $game['player']['x'];
    $y = (int) $game['player']['y'];

    if (($game['map'][$y][$x] ?? '#') === 'K') {
        $game['keycard'] = true;
        $game['map'][$y][$x] = '.';
        $game['message'] = 'You found a security keycard.';
    }
}

function advanceLevel(array &$game): void
{
    if ($game['level'] >= MAX_LEVEL) {
        $game['status'] = 'won';
        $game['message'] = 'You reach the vault exit unseen. Mission complete.';
        return;
    }

    $next = (int) $game['level'] + 1;
    loadLevel($game, $next);
    $game['message'] = "Area cleared. Level {$next}: {$game['level_name']}.";
}

function guardPhase(array &$game): void
{
    if ($game['status'] !== 'playing') {
        return;
    }

    $spottedBefore = playerSeenByAnyGuard($game);

    if ($spottedBefore) {
        triggerAlert($game);
    }

    $occupied = [];

    foreach ($game['guards'] as $guard) {
        $occupied[keyOf($guard['x'], $guard['y'])] = true;
    }

    foreach ($game['guards'] as $index => $guard) {
        unset($occupied[keyOf($guard['x'], $guard['y'])]);

        if ($game['alert'] > 0) {
            moveGuardTowardPlayer($game, $index, $occupied);
        } else {
            moveGuardOnPatrol($game, $index, $occupied);
        }

        $guard = $game['guards'][$index];
        $occupied[keyOf($guard['x'], $guard['y'])] = true;

        if (
            $guard['x'] === $game['player']['x'] &&
            $guard['y'] === $game['player']['y']
        ) {
            loseGame($game, 'A guard catches you.');
            return;
        }
    }

    $spottedAfter = playerSeenByAnyGuard($game);

    if ($spottedAfter) {
        triggerAlert($game);
    } elseif ($game['alert'] > 0) {
        $game['alert']--;

        if ($game['alert'] === 0) {
            $game['message'] .= ' The alarm clears. Patrols resume.';
        } else {
            $game['message'] .= " Alarm active for {$game['alert']} more turn(s).";
        }
    }
}

function triggerAlert(array &$game): void
{
    if ($game['alert'] === 0) {
        $game['spotted_count']++;
    }

    $game['alert'] = ALERT_TURNS;
    $game['message'] = 'SPOTTED! Guards are chasing you.';
}

function moveGuardOnPatrol(array &$game, int $index, array $occupied): void
{
    $guard = $game['guards'][$index];
    $route = $guard['route'];

    if (!$route) {
        return;
    }

    $targetIndex = $guard['route_index'] % count($route);
    [$tx, $ty] = $route[$targetIndex];

    if ($guard['x'] === $tx && $guard['y'] === $ty) {
        $targetIndex = ($targetIndex + 1) % count($route);
        $game['guards'][$index]['route_index'] = $targetIndex;
        [$tx, $ty] = $route[$targetIndex];
    }

    $step = nextStepToward(
        $game,
        $guard['x'],
        $guard['y'],
        $tx,
        $ty,
        $occupied
    );

    if ($step !== null) {
        applyGuardStep($game, $index, $step[0], $step[1]);
    }
}

function moveGuardTowardPlayer(array &$game, int $index, array $occupied): void
{
    $guard = $game['guards'][$index];

    $step = nextStepToward(
        $game,
        $guard['x'],
        $guard['y'],
        $game['player']['x'],
        $game['player']['y'],
        $occupied
    );

    if ($step !== null) {
        applyGuardStep($game, $index, $step[0], $step[1]);
    }
}

function nextStepToward(
    array $game,
    int $sx,
    int $sy,
    int $tx,
    int $ty,
    array $occupied
): ?array {
    if ($sx === $tx && $sy === $ty) {
        return null;
    }

    $queue = [[$sx, $sy]];
    $head = 0;
    $visited = [keyOf($sx, $sy) => true];
    $parent = [];

    while ($head < count($queue)) {
        [$x, $y] = $queue[$head++];

        foreach ([[0,-1],[1,0],[0,1],[-1,0]] as [$dx, $dy]) {
            $nx = $x + $dx;
            $ny = $y + $dy;
            $key = keyOf($nx, $ny);

            if (isset($visited[$key])) {
                continue;
            }

            if (!guardCanEnter($game, $nx, $ny, $occupied, $tx, $ty)) {
                continue;
            }

            $visited[$key] = true;
            $parent[$key] = [$x, $y];
            $queue[] = [$nx, $ny];

            if ($nx === $tx && $ny === $ty) {
                $cursor = [$nx, $ny];

                while (true) {
                    $cursorKey = keyOf($cursor[0], $cursor[1]);
                    $previous = $parent[$cursorKey] ?? null;

                    if ($previous === null) {
                        return null;
                    }

                    if ($previous[0] === $sx && $previous[1] === $sy) {
                        return $cursor;
                    }

                    $cursor = $previous;
                }
            }
        }
    }

    return null;
}

function guardCanEnter(
    array $game,
    int $x,
    int $y,
    array $occupied,
    int $targetX,
    int $targetY
): bool {
    if (!cellExists($game, $x, $y)) {
        return false;
    }

    $tile = $game['map'][$y][$x];

    if ($tile === '#' || $tile === 'D') {
        return false;
    }

    if (
        isset($occupied[keyOf($x, $y)]) &&
        !($x === $targetX && $y === $targetY)
    ) {
        return false;
    }

    return true;
}

function applyGuardStep(array &$game, int $index, int $nx, int $ny): void
{
    $guard = $game['guards'][$index];
    $dx = $nx - $guard['x'];
    $dy = $ny - $guard['y'];

    $game['guards'][$index]['dir'] = directionFromDelta($dx, $dy);
    $game['guards'][$index]['x'] = $nx;
    $game['guards'][$index]['y'] = $ny;
}

function directionFromDelta(int $dx, int $dy): string
{
    if ($dx > 0) return 'E';
    if ($dx < 0) return 'W';
    if ($dy > 0) return 'S';
    return 'N';
}

function playerSeenByAnyGuard(array $game): bool
{
    foreach ($game['guards'] as $guard) {
        if (guardSeesCell(
            $game,
            $guard,
            (int) $game['player']['x'],
            (int) $game['player']['y']
        )) {
            return true;
        }
    }

    return false;
}

function guardVisionCells(array $game, array $guard): array
{
    $cells = [];

    $directions = [
        'N' => [0, -1],
        'E' => [1, 0],
        'S' => [0, 1],
        'W' => [-1, 0],
    ];

    [$fx, $fy] = $directions[$guard['dir']];
    $px = -$fy;
    $py = $fx;

    for ($forward = 1; $forward <= $guard['range']; $forward++) {
        $spread = intdiv($forward, 2);

        for ($side = -$spread; $side <= $spread; $side++) {
            $x = $guard['x'] + $fx * $forward + $px * $side;
            $y = $guard['y'] + $fy * $forward + $py * $side;

            if (!cellExists($game, $x, $y)) {
                continue;
            }

            if (!lineClear(
                $game,
                $guard['x'],
                $guard['y'],
                $x,
                $y
            )) {
                continue;
            }

            $tile = $game['map'][$y][$x];

            if ($tile !== '#') {
                $cells[keyOf($x, $y)] = true;
            }
        }
    }

    return $cells;
}

function guardSeesCell(array $game, array $guard, int $x, int $y): bool
{
    $visible = guardVisionCells($game, $guard);
    return isset($visible[keyOf($x, $y)]);
}

function allVisionCells(array $game): array
{
    $all = [];

    foreach ($game['guards'] as $guard) {
        foreach (guardVisionCells($game, $guard) as $key => $_) {
            $all[$key] = true;
        }
    }

    return $all;
}

function lineClear(
    array $game,
    int $x0,
    int $y0,
    int $x1,
    int $y1
): bool {
    $dx = abs($x1 - $x0);
    $dy = abs($y1 - $y0);
    $sx = $x0 < $x1 ? 1 : -1;
    $sy = $y0 < $y1 ? 1 : -1;
    $err = $dx - $dy;

    $x = $x0;
    $y = $y0;

    while (!($x === $x1 && $y === $y1)) {
        $e2 = 2 * $err;

        if ($e2 > -$dy) {
            $err -= $dy;
            $x += $sx;
        }

        if ($e2 < $dx) {
            $err += $dx;
            $y += $sy;
        }

        if ($x === $x1 && $y === $y1) {
            break;
        }

        if (!cellExists($game, $x, $y)) {
            return false;
        }

        $tile = $game['map'][$y][$x];

        if ($tile === '#' || $tile === 'D') {
            return false;
        }
    }

    return true;
}

function loseGame(array &$game, string $message): void
{
    $game['status'] = 'lost';
    $game['message'] = $message;
}

function guardAt(array $game, int $x, int $y): ?int
{
    foreach ($game['guards'] as $index => $guard) {
        if ($guard['x'] === $x && $guard['y'] === $y) {
            return $index;
        }
    }

    return null;
}

function cellExists(array $game, int $x, int $y): bool
{
    return isset($game['map'][$y][$x]);
}

function keyOf(int $x, int $y): string
{
    return $x . ',' . $y;
}

function tileView(array $game, int $x, int $y, array $vision): array
{
    if ($game['player']['x'] === $x && $game['player']['y'] === $y) {
        return ['player', '●', 'Player'];
    }

    $guardIndex = guardAt($game, $x, $y);

    if ($guardIndex !== null) {
        $guard = $game['guards'][$guardIndex];

        return [
            'guard',
            guardGlyph($guard['dir']),
            'Guard ' . $guard['id'] . ' facing ' . $guard['dir'],
        ];
    }

    $tile = $game['map'][$y][$x] ?? '#';

    return match ($tile) {
        '#' => ['wall', '', 'Wall'],
        'D' => ['door', '▥', 'Locked security door'],
        'K' => ['keycard', '◆', 'Keycard'],
        'E' => ['exit', '⇥', 'Exit'],
        default => [
            isset($vision[keyOf($x, $y)]) ? 'floor vision' : 'floor',
            '',
            isset($vision[keyOf($x, $y)]) ? 'Guard vision' : 'Floor',
        ],
    };
}

function guardGlyph(string $direction): string
{
    return match ($direction) {
        'N' => '▲',
        'E' => '▶',
        'S' => '▼',
        'W' => '◀',
        default => '▲',
    };
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
