# Adventures of the Dice

A Snakes & Ladders web game built for **CSC 4370/6370 – Web Programming (Fall 2026), Georgia State University**.
Race the Dice Bot from square 1 to 100 on a board that PHP generates fresh every game.

**Tech:** PHP 7.4+ (tested on 8.3), HTML5, CSS3. No JavaScript. No database (accounts and scores are stored in JSON files).

- GitHub: https://github.com/arjunb-commits/project2
- CODD: https://codd.cs.gsu.edu/~abaskar2/project2/index.php


## How to play

1. Register an account, then log in.
2. In the lobby choose **Easy / Normal / Hard** and start a game.
3. Click **Roll Dice**. You roll first, then the Dice Bot. Ladders (green ▲) move you up, snakes (red ▼) slide you down.
4. You need an **exact roll** to land on square 100.
5. Winning games are scored and added to the leaderboard.

**Score** = (1000 − 10×turns + 15×ladders − 10×snakes, minimum 0) × difficulty multiplier (Easy 1.0, Normal 1.5, Hard 2.0), plus 200 for a win.
Leaderboard ties are broken by fewer turns, then by earlier date.

## Features mapped to the project requirements

| Requirement | Where |
|-------------|-------|
| Sessions | `$_SESSION` holds login, the active game (board, positions, log) and tokens: `includes/config.php`, `game.php` |
| Cookies | "Remember my username" cookie `last_user`: `login.php` |
| Form processing & validation | Every form is validated and sanitized server-side; output escaped with `h()`: all pages |
| Login / registration / logout | `register.php`, `login.php`, `logout.php`; `require_login()` guards protected pages |
| PHP-driven game logic | `includes/functions.php` (`generate_board`, `apply_roll`, `play_round`, `calculate_score`) |
| Leaderboard | `leaderboard.php`, data in `data/scores.json` |
| Responsive design | `css/style.css` (CSS Grid board, Flexbox, media queries at 600px and 900px) |

### Security notes
- Passwords stored with `password_hash()` and checked with `password_verify()`; session ID regenerated at login.
- CSRF token on every POST form; one-time `turn_token` on every dice roll (blocks refresh/back/double-click rolls).
- Post/Redirect/Get after every POST. File writes use `flock()`. `data/.htaccess` blocks direct web access to the JSON files.
- Post-login redirects and the leaderboard filter are checked against whitelists.
- Logging out ends the session and its session cookie; the optional `last_user` cookie (username only, never a password) stays until it expires or the user unticks "Remember".

## Screens

| File | Purpose |
|------|---------|
| `index.php` | Redirects to lobby or login |
| `register.php` / `login.php` / `logout.php` | Accounts |
| `lobby.php` | Stats, rules, difficulty and new game |
| `game.php` | Board, dice, bot turn |
| `result.php` | Score breakdown, saves the score once |
| `leaderboard.php` | Top 10 with difficulty filter |
