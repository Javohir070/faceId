<?php

/**
 * `php artisan serve` routeri — ServeCommand loyiha ildizida shu fayl bo‘lsa, framework'nikini emas, shuni ishlatadi.
 *
 * PHP ichki serveri statik fayllarni siqmaydi va kesh sarlavhasisiz beradi: 630 KB lik app.js ngrok va sekin
 * internetda har ochilishda bir necha soniya ketadi. Shuning uchun Vite build fayllari (`/build/assets/*`,
 * nomida hash bor — yangi build yangi nom oladi) gzip bilan siqib va bir yillik kesh bilan beriladi.
 * Qolgan hamma so‘rovlar — framework'ning standart routeri.
 */
$publicPath = getcwd();

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '');

$file = $publicPath.$uri;

if (str_starts_with($uri, '/build/assets/') && ! str_contains($uri, '..') && is_file($file)) {
    $types = ['js' => 'text/javascript; charset=utf-8', 'css' => 'text/css; charset=utf-8'];
    $type = $types[strtolower(pathinfo($file, PATHINFO_EXTENSION))] ?? null;

    header('Content-Type: '.($type ?? (mime_content_type($file) ?: 'application/octet-stream')));
    header('Cache-Control: public, max-age=31536000, immutable');
    header('Vary: Accept-Encoding');

    $body = file_get_contents($file);

    if ($type !== null && str_contains($_SERVER['HTTP_ACCEPT_ENCODING'] ?? '', 'gzip')) {
        $body = gzencode($body, 6);
        header('Content-Encoding: gzip');
    }

    header('Content-Length: '.strlen($body));

    if ($_SERVER['REQUEST_METHOD'] !== 'HEAD') {
        echo $body;
    }

    return true;
}

return require __DIR__.'/vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php';
