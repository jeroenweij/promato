$app->withFacades();
$app->withEloquent();
$app->configure('view');

$app->middleware([
    \App\Http\Middleware\VerifyCsrfToken::class
]);

// Disable CSRF protection for the specific route
$app->routeMiddleware([
    'csrf' => \App\Http\Middleware\VerifyCsrfToken::class,
]);
