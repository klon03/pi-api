<?php 
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Factory\AppFactory;
use GuzzleHttp\Client;

use function PHPUnit\Framework\throwException;

require __DIR__ . '/../vendor/autoload.php';

$app = AppFactory::create();
$raspberryIp = "10.20.40.114";
$raspberryPort = $raspberryIp . ":5001"; 


$app->addErrorMiddleware(true, false, false);

// Konfiguracja nagłówków CORS
$app->options('/{routes:.+}', function ($request, $response, $args) {
    return $response;
});

// Middleware do obsługi CORS
$app->add(function ($request, $handler) {
    $allowedOrigins = [
        'http://127.0.0.1:5500',
        'https://kwachowski.pl/'
    ];

    $origin = $request->getHeaderLine('Origin');

    if (in_array($origin, $allowedOrigins)) {
        $response = $handler->handle($request);
        return $response
            ->withHeader('Access-Control-Allow-Origin', $origin)
            ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization')
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST');
    }

    return new Response('Not allowed by CORS', 403);
});


$app->get('/', function(Request $request, Response $response) {
    $response->getBody()->write("The API is working!");
    return $response;
});

// Sprawdzanie statusu urządzenia
$app->get('/status', function(Request $request, Response $response) {

    $check = checkRaspberry();

    $response->getBody()->write(
        json_encode($check)
    );
    return $response->withHeader('Content-Type', 'application/json');
});

// Odtwarzanie pojedynczego dźwięku
$app->post('/play-sound', function(Request $request, Response $response) {

    $check = checkRaspberry();
    if ($check['serverStatus'] !== true) {
        $response->getBody()->write(
            json_encode(["error" => "Unable to connect to Raspberry server."])
        );
        return $response->withHeader('Content-Type', 'application/json');
    }

    global $raspberryPort;

    $data = $request->getParsedBody();
    $soundToPlay = isset($data['soundToPlay']) ? $data['soundToPlay'] : '';

    if(empty($soundToPlay)) {
        // Zwracanie błędu, jeśli brakuje parametru
        $error = ["error" => "Incorrect parameters."];
        $response->getBody()->write(json_encode($error));

        return $response->withHeader('Content-Type', 'application/json');
    }
   

    
    $client = new Client();
    $responseRaspberry = $client->post("$raspberryPort/play-sound", [
        'form_params' => ['soundToPlay' => $soundToPlay],
    ]);

    $response->getBody()->write(
        $responseRaspberry->getBody()->getContents()
    );
    return $response->withHeader('Content-Type', 'application/json');
});

// Odtwarzanie piosenki
$app->post('/play-song', function(Request $request, Response $response) {

    $check = checkRaspberry();
    if ($check['serverStatus'] !== true) {
        $response->getBody()->write(
            json_encode(["error" => "Unable to connect to Raspberry server."])
        );
        return $response->withHeader('Content-Type', 'application/json');
    }

    global $raspberryPort;

    $data = $request->getParsedBody();
    $songToPlay = isset($data['songToPlay']) ? $data['songToPlay'] : '';

    if(empty($songToPlay)) {
        // Zwracanie błędu, jeśli brakuje parametru
        $error = ["error" => "Incorrect parameters."];
        $response->getBody()->write(json_encode($error));

        return $response->withHeader('Content-Type', 'application/json');
    }
   

    
    $client = new Client();
    $responseRaspberry = $client->post("$raspberryPort/play-song", [
        'form_params' => ['songToPlay' => $songToPlay],
    ]);

    $response->getBody()->write(
        $responseRaspberry->getBody()->getContents()
    );
    return $response->withHeader('Content-Type', 'application/json');
});

// Pobieranie dostępnych dźwięków
$app->get('/get-sounds', function(Request $request, Response $response) {

    $check = checkRaspberry();
    if ($check['serverStatus'] !== true) {
        $response->getBody()->write(
            json_encode(["error" => "Unable to connect to Raspberry server."])
        );
        return $response->withHeader('Content-Type', 'application/json');
    }
    
    global $raspberryPort;
    $client = new Client();
    $responseRaspberry = $client->get("$raspberryPort/get-sounds");

    $response->getBody()->write(
        $responseRaspberry->getBody()->getContents()
    );
    return $response->withHeader('Content-Type', 'application/json');
});


/* =========== Funkcje =========== */
function pingRaspberry() {
    global $raspberryIp;
    $cmd = PHP_OS_FAMILY === 'Windows' ? "ping -n 1 $raspberryIp" : "ping -c 1 $raspberryIp";
    exec($cmd, $output, $resultCode);
    return $resultCode === 0;
}

function checkRaspberry() {
    global $raspberryPort;
    $client = new Client();
    $isAlive = pingRaspberry();
    try {
        $responseRaspberry = $client->get("$raspberryPort/get-status");
        if ($responseRaspberry->getStatusCode() === 200) {
            $serverStatus = json_decode($responseRaspberry->getBody()->getContents(), true);
            $serverStatus = $serverStatus['status'];
        } else {
            throw new Exception();
        }
    } catch (GuzzleHttp\Exception\ConnectException $e) {
        $serverStatus = false;
    } catch (Exception $e) {
        $serverStatus = false;
    }

    return ["deviceStatus" => $isAlive, "serverStatus" => $serverStatus];
}

$app->run();