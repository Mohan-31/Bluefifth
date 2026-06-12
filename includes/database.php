<?php
// Load .env before anything else so DATABASE_URL is available
// regardless of which file included us first.
(static function () {
    $f = __DIR__ . '/../.env';
    if (!file_exists($f)) return;
    foreach (file($f, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) continue;
        [$k, $v] = explode('=', $line, 2);
        $k = trim($k); $v = trim($v);
        if ($k !== '' && getenv($k) === false) {
            putenv("$k=$v");
            $_ENV[$k] = $_SERVER[$k] = $v;
        }
    }
})();

// Supports Neon's full DATABASE_URL or individual DB_* env vars.
$databaseUrl = getenv('DATABASE_URL');

if ($databaseUrl) {
    $parts    = parse_url($databaseUrl);
    $host     = $parts['host'];
    $port     = $parts['port'] ?? 5432;
    $dbname   = ltrim($parts['path'], '/');
    $username = $parts['user'];
    $password = $parts['pass'];

    // Convert URL query string (&) to PDO DSN format (;), strip channel_binding
    // (old libpq doesn't support it — sslmode=require is sufficient)
    $queryParts = [];
    if (isset($parts['query'])) {
        parse_str($parts['query'], $queryParts);
    }
    unset($queryParts['channel_binding']);
    $queryParts['sslmode'] = $queryParts['sslmode'] ?? 'require';

    // SNI workaround for old libpq: pass endpoint ID via options.
    // Must NOT URL-encode the '=' in options value — build DSN manually.
    $endpointId = explode('.', $host)[0];
    $endpointId = preg_replace('/-pooler$/', '', $endpointId);
    $sslmode    = $queryParts['sslmode'] ?? 'require';

    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname;sslmode=$sslmode;options=endpoint=$endpointId";
} else {
    $host     = getenv('DB_HOST')     ?: 'localhost';
    $port     = getenv('DB_PORT')     ?: '5432';
    $dbname   = getenv('DB_NAME')     ?: 'neondb';
    $username = getenv('DB_USER')     ?: 'postgres';
    $password = getenv('DB_PASSWORD') ?: '';
    $sslmode  = getenv('DB_SSLMODE')  ?: 'require';
    $dsn      = "pgsql:host=$host;port=$port;dbname=$dbname;sslmode=$sslmode";
}

try {
    $conn = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    error_log("Database connection error: " . $e->getMessage());
    die(json_encode(['success' => false, 'message' => 'Database unavailable']));
}

function getConnection() {
    global $conn;
    return $conn;
}
