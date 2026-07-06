<?php
// db.php — shared database connection
// keeping this separate so it's not duplicated across pages

function getDb() {
    static $pdo = null;
    if ($pdo === null) {
        $host = "mysql.railway.internal";
        $dbname = "railway";
        $user = "root";
        $pass = "BISNsLaswbQETesNrYNTbBCEsZWvyMFv";

        try {
            $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        } catch (PDOException $e) {
            die("DB connection failed. Did you run schema.sql? — " . $e->getMessage());
        }
    }
    return $pdo;
}

function e($str) {
    // small helper, used everywhere to avoid XSS when echoing user input
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}
