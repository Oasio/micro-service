<?php

/**
 * Routeur pour le serveur web intégré de PHP (`php -S ... -t public public/router.php`).
 *
 * Sans ce routeur, `index.php` intercepte TOUTES les requêtes — y compris les fichiers
 * statiques (CSS/JS de la Swagger UI) — et Symfony renvoie alors du HTML à la place des
 * assets, ce qui casse l'affichage de /api/docs.
 *
 * Ici : si l'URL correspond à un fichier réel sous public/, on le sert tel quel
 * (return false) ; sinon on passe la main au contrôleur frontal Symfony.
 */

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/');
$file = __DIR__.$uri;

if ('/' !== $uri && is_file($file)) {
    return false; // laisse le serveur intégré servir le fichier statique (bon Content-Type)
}

// Le runtime Symfony (autoload_runtime.php) recharge le script pointé par SCRIPT_FILENAME
// en attendant qu'il renvoie un callable. On le force sur index.php, sinon il rechargerait
// router.php (qui ne renvoie pas de callable) → "callable object expected".
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['SCRIPT_FILENAME'] = __DIR__.'/index.php';
require __DIR__.'/index.php';
