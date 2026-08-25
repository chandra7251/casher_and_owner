<?php

use App\Http\Controllers\OrderController;
use App\Http\Requests\CreateOrderRequest;
use App\Models\User;
use Illuminate\Foundation\Application;
use Illuminate\Http\Exceptions\HttpResponseException;
use Symfony\Component\HttpKernel\Exception\HttpException;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Application::class)->make('Illuminate\\Contracts\\Console\\Kernel')->bootstrap();
$user = User::query()->findOrFail((int) $argv[1]);
$payload = json_decode($argv[2], true, 512, JSON_THROW_ON_ERROR);
$readyPath = $argv[3];
$startPath = $argv[4];
$request = CreateOrderRequest::create('/cashier/orders', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], json_encode($payload, JSON_THROW_ON_ERROR));
$request->setUserResolver(fn () => $user);
$request->setContainer($app)->setRedirector($app->make('redirect'));
file_put_contents($readyPath, 'ready');
while (! file_exists($startPath)) {
    usleep(1000);
}
try {
    $request->validateResolved();
    $response = app(OrderController::class)->store($request);
    $result = ['status' => $response->getStatusCode(), 'body' => $response->getContent()];
} catch (HttpException $exception) {
    $result = ['status' => $exception->getStatusCode(), 'body' => $exception->getMessage()];
} catch (HttpResponseException $exception) {
    $response = $exception->getResponse();
    $result = ['status' => $response->getStatusCode(), 'body' => $response->getContent()];
} catch (Throwable $exception) {
    $result = ['status' => 500, 'body' => $exception->getMessage()];
}
file_put_contents($readyPath.'.result', json_encode($result, JSON_THROW_ON_ERROR));
