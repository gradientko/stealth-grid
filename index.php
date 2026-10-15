<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/functions.php';

if (!isset($_SESSION['stealth_stats'])) {
    $_SESSION['stealth_stats'] = [
        'runs' => 0,
        'wins' => 0,
        'best_level' => 1,
        'best_turns' => null,
        'fewest_spots' => null,
    ];
}

if (!isset($_SESSION['stealth_game'])) {
    $_SESSION['stealth_game'] = createGame();
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';

    if (!is_string($token) || !hash_equals($_SESSION['csrf_token'], $token)) {
        $error = 'Invalid request token.';
    } else {
        $action = $_POST['action'] ?? '';
        $game = $_SESSION['stealth_game'];

        if ($action === 'new') {
            $_SESSION['stealth_game'] = createGame();
            header('Location: index.php');
            exit;
        }

        if ($game['status'] === 'playing') {
            match ($action) {
                'up' => movePlayer($game, 0, -1),
                'right' => movePlayer($game, 1, 0),
                'down' => movePlayer($game, 0, 1),
                'left' => movePlayer($game, -1, 0),
                'wait' => waitTurn($game),
                default => null,
            };

            $_SESSION['stealth_stats']['best_level'] = max(
                $_SESSION['stealth_stats']['best_level'],
                $game['level']
            );

            if ($game['status'] !== 'playing' && empty($game['settled'])) {
                $_SESSION['stealth_stats']['runs']++;

                if ($game['status'] === 'won') {
                    $_SESSION['stealth_stats']['wins']++;

                    $bestTurns = $_SESSION['stealth_stats']['best_turns'];

                    if ($bestTurns === null || $game['turns'] < $bestTurns) {
                        $_SESSION['stealth_stats']['best_turns'] = $game['turns'];
                    }

                    $fewest = $_SESSION['stealth_stats']['fewest_spots'];

                    if ($fewest === null || $game['spotted_count'] < $fewest) {
                        $_SESSION['stealth_stats']['fewest_spots'] = $game['spotted_count'];
                    }
                }

                $game['settled'] = true;
            }

            $_SESSION['stealth_game'] = $game;
        }

        header('Location: index.php');
        exit;
    }
}

$game = $_SESSION['stealth_game'];
$stats = $_SESSION['stealth_stats'];
$vision = allVisionCells($game);
$height = count($game['map']);
$width = count($game['map'][0]);
$winRate = $stats['runs'] > 0
    ? (int) round(($stats['wins'] / $stats['runs']) * 100)
    : 0;
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Stealth Grid — Pure PHP</title>
    <meta
        name="description"
        content="A turn-based stealth game with guard vision, patrols and chase AI written in pure PHP."
    >
    <link rel="stylesheet" href="style.css">
</head>
<body>
<main class="shell">
    <header class="hero">
        <div>
            <span class="eyebrow">PURE PHP STEALTH GAME</span>
            <h1>Stay unseen.</h1>
            <p>
                Read the patrols, cross vision cones, steal the keycard and reach the exit.
                Every move advances the guards.
            </p>
        </div>

        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
            <input type="hidden" name="action" value="new">
            <button class="text-button" type="submit">New run</button>
        </form>
    </header>

    <section class="stats">
        <div>
            <span>Level</span>
            <strong><?= (int) $game['level'] ?>/<?= MAX_LEVEL ?></strong>
        </div>
        <div>
            <span>Turns</span>
            <strong><?= (int) $game['turns'] ?></strong>
        </div>
        <div>
            <span>Spotted</span>
            <strong><?= (int) $game['spotted_count'] ?></strong>
        </div>
        <div>
            <span>Win rate</span>
            <strong><?= $winRate ?>%</strong>
        </div>
    </section>

    <section class="layout">
        <section class="game-card">
            <div class="game-head">
                <div>
                    <span class="eyebrow">
                        LEVEL <?= (int) $game['level'] ?> · <?= e($game['level_name']) ?>
                    </span>
                    <h2><?= e($game['message']) ?></h2>
                </div>

                <?php if ($game['alert'] > 0): ?>
                    <span class="status-pill alert">
                        ALERT <?= (int) $game['alert'] ?>
                    </span>
                <?php else: ?>
                    <span class="status-pill <?= e($game['status']) ?>">
                        <?= strtoupper(e($game['status'])) ?>
                    </span>
                <?php endif; ?>
            </div>

            <?php if ($error !== null): ?>
                <div class="notice"><?= e($error) ?></div>
            <?php endif; ?>

            <div
                class="map"
                style="--cols: <?= $width ?>;"
                role="grid"
                aria-label="Stealth map"
            >
                <?php for ($y = 0; $y < $height; $y++): ?>
                    <?php for ($x = 0; $x < $width; $x++): ?>
                        <?php [$class, $glyph, $label] = tileView($game, $x, $y, $vision); ?>
                        <div
                            class="tile <?= e($class) ?>"
                            role="gridcell"
                            title="<?= e($label) ?>"
                        >
                            <?= e($glyph) ?>
                        </div>
                    <?php endfor; ?>
                <?php endfor; ?>
            </div>

            <?php if ($game['status'] === 'playing'): ?>
                <section class="controls">
                    <div class="pad">
                        <span></span>

                        <form method="post">
                            <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                            <input type="hidden" name="action" value="up">
                            <button type="submit" aria-label="Move up">↑</button>
                        </form>

                        <span></span>

                        <form method="post">
                            <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                            <input type="hidden" name="action" value="left">
                            <button type="submit" aria-label="Move left">←</button>
                        </form>

                        <form method="post">
                            <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                            <input type="hidden" name="action" value="down">
                            <button type="submit" aria-label="Move down">↓</button>
                        </form>

                        <form method="post">
                            <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                            <input type="hidden" name="action" value="right">
                            <button type="submit" aria-label="Move right">→</button>
                        </form>
                    </div>

                    <form method="post" class="wait-form">
                        <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                        <input type="hidden" name="action" value="wait">
                        <button type="submit">
                            Wait one turn
                            <span>Let patrols move</span>
                        </button>
                    </form>
                </section>
            <?php else: ?>
                <form method="post" class="again">
                    <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="action" value="new">
                    <button type="submit">
                        <?= $game['status'] === 'won' ? 'Run again' : 'Retry mission' ?>
                    </button>
                </form>
            <?php endif; ?>
        </section>

        <aside class="side">
            <section class="panel">
                <span class="eyebrow">LOADOUT</span>
                <dl>
                    <div>
                        <dt>Keycard</dt>
                        <dd><?= $game['keycard'] ? 'YES' : 'NO' ?></dd>
                    </div>
                    <div>
                        <dt>Doors opened</dt>
                        <dd><?= (int) $game['doors_opened'] ?></dd>
                    </div>
                    <div>
                        <dt>Alert</dt>
                        <dd><?= (int) $game['alert'] ?></dd>
                    </div>
                    <div>
                        <dt>Guards</dt>
                        <dd><?= count($game['guards']) ?></dd>
                    </div>
                </dl>
            </section>

            <section class="legend">
                <div><i class="player-glyph">●</i><span>You</span></div>
                <div><i class="guard-glyph">▲</i><span>Guard + facing</span></div>
                <div><i class="vision-glyph"></i><span>Vision cone</span></div>
                <div><i class="key-glyph">◆</i><span>Keycard</span></div>
                <div><i class="door-glyph">▥</i><span>Security door</span></div>
                <div><i class="exit-glyph">⇥</i><span>Exit</span></div>
            </section>

            <section class="panel">
                <span class="eyebrow">GUARD AI</span>
                <p>
                    Guards follow patrol routes while calm. If any cone catches you,
                    all guards switch to shortest-path chase mode for <?= ALERT_TURNS ?> turns.
                </p>
            </section>

            <section class="panel">
                <span class="eyebrow">RECORDS</span>
                <dl>
                    <div>
                        <dt>Runs</dt>
                        <dd><?= (int) $stats['runs'] ?></dd>
                    </div>
                    <div>
                        <dt>Wins</dt>
                        <dd><?= (int) $stats['wins'] ?></dd>
                    </div>
                    <div>
                        <dt>Best level</dt>
                        <dd><?= (int) $stats['best_level'] ?></dd>
                    </div>
                    <div>
                        <dt>Best win</dt>
                        <dd><?= $stats['best_turns'] === null ? '—' : ((int)$stats['best_turns'] . ' turns') ?></dd>
                    </div>
                    <div>
                        <dt>Fewest spots</dt>
                        <dd><?= $stats['fewest_spots'] === null ? '—' : (int)$stats['fewest_spots'] ?></dd>
                    </div>
                </dl>
            </section>

            <section class="panel">
                <span class="eyebrow">SERVER SIDE</span>
                <p>
                    No JavaScript. Vision, pathfinding, patrols, chase AI, doors,
                    keycards, levels and game state all run in PHP.
                </p>
            </section>
        </aside>
    </section>

    <footer>
        <span>PHP sessions · BFS chase AI · vision cones · no JavaScript</span>
        <span>stealth-grid</span>
    </footer>
</main>
</body>
</html>
