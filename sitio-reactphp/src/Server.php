<?php

use React\Http\HttpServer;
use React\Socket\SocketServer;
use Psr\Http\Message\ServerRequestInterface;
use React\Http\Message\Response;
use React\MySQL\Factory;
use React\MySQL\QueryResult;

require __DIR__ . '/../vendor/autoload.php';

$port = 8080;
$dsn = 'root:@127.0.0.1/sitio_reactphp?charset=utf8mb4';

$factory = new Factory();
$db = $factory->createLazyConnection($dsn);

function serveStaticFile(string $path, string $contentType): Response
{
    $filePath = __DIR__ . '/../public' . $path;
    
    if (!file_exists($filePath)) {
        return new Response(404, ['Content-Type' => 'text/html'], '404 Not Found');
    }
    
    $content = file_get_contents($filePath);
    return new Response(200, ['Content-Type' => $contentType], $content);
}

function validateContactData(array $data): ?string
{
    if (empty($data['name'])) {
        return 'El nombre es requerido';
    }
    if (empty($data['email'])) {
        return 'El email es requerido';
    }
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        return 'El email no es válido';
    }
    return null;
}

//---------------------------------------------------------------------------------
$http = new HttpServer(function (ServerRequestInterface $request) use ($db) {
    $path = $request->getUri()->getPath(); // Obtiene la ruta solicitada
    
    // Manejo de CORS para las peticiones AJAX
    if (strpos($path, '/api/') === 0) {
        $headers = [
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type'
        ];
        
        if ($request->getMethod() === 'OPTIONS') {
            return new Response(200, $headers, '');
        }
    }
    
    switch ($path) {
        case '/': // Ruta de inicio
            return serveStaticFile('/index.html', 'text/html');
            
        case '/contact':   // Ruta de contacto
            if ($request->getMethod() === 'POST') {
                $data = $request->getParsedBody();
                
                $validationError = validateContactData($data);
                if ($validationError) {
                    return new Response(400, ['Content-Type' => 'text/html'], $validationError);
                }
                
                $name = htmlspecialchars($data['name']);
                $email = filter_var($data['email'], FILTER_SANITIZE_EMAIL);
                $message = htmlspecialchars($data['message'] ?? '');
                
                $promise = $db->query(
                    'INSERT INTO contacts (name, email, message) VALUES (?, ?, ?)',
                    [$name, $email, $message]
                );
                
        
            }
            return serveStaticFile('/contact.html', 'text/html');
            
        case '/data':
            return serveStaticFile('/data.html', 'text/html');
            
        case '/api/contacts':
            switch ($request->getMethod()) {
                case 'GET':
                    // READ: Obtener todos los contactos
                    $promise = $db->query('SELECT * FROM contacts ORDER BY created_at DESC')
                        ->then(
                            function (QueryResult $result) use ($headers) {
                                return new Response(
                                    200,
                                    array_merge($headers, ['Content-Type' => 'application/json']),
                                    json_encode($result->resultRows)
                                );
                            },
                            function (Exception $error) use ($headers) {
                                return new Response(
                                    500,
                                    array_merge($headers, ['Content-Type' => 'text/plain']),
                                    'Error de base de datos: ' . $error->getMessage()
                                );
                            }
                        );
                    return $promise;
                    
                case 'PUT':
                    // UPDATE: Actualizar contacto existente
                    $data = json_decode((string)$request->getBody(), true);
                    
                    if (empty($data['id'])) {
                        return new Response(
                            400,
                            array_merge($headers, ['Content-Type' => 'application/json']),
                            json_encode(['error' => 'ID de contacto no proporcionado'])
                        );
                    }
                    
                    $validationError = validateContactData($data);
                    if ($validationError) {
                        return new Response(
                            400,
                            array_merge($headers, ['Content-Type' => 'application/json']),
                            json_encode(['error' => $validationError])
                        );
                    }
                    
                    $id = (int)$data['id'];
                    $name = htmlspecialchars($data['name']);
                    $email = filter_var($data['email'], FILTER_SANITIZE_EMAIL);
                    $message = htmlspecialchars($data['message'] ?? '');
                    
                    $promise = $db->query(
                        'UPDATE contacts SET name = ?, email = ?, message = ? WHERE id = ?',
                        [$name, $email, $message, $id]
                    )->then(
                        function () use ($headers, $db, $id) {
                            // Devuelve el contacto actualizado
                            return $db->query('SELECT * FROM contacts WHERE id = ?', [$id])
                                ->then(
                                    function (QueryResult $result) use ($headers) {
                                        if (empty($result->resultRows)) {
                                            return new Response(
                                                404,
                                                array_merge($headers, ['Content-Type' => 'application/json']),
                                                json_encode(['error' => 'Contacto no encontrado'])
                                            );
                                        }
                                        return new Response(
                                            200,
                                            array_merge($headers, ['Content-Type' => 'application/json']),
                                            json_encode($result->resultRows[0])
                                        );
                                    }
                                );
                        },
                        function (Exception $error) use ($headers) {
                            return new Response(
                                500,
                                array_merge($headers, ['Content-Type' => 'application/json']),
                                json_encode(['error' => 'Error al actualizar contacto: ' . $error->getMessage()])
                            );
                        }
                    );
                    return $promise;
                    
                case 'DELETE':
                    // DELETE: Eliminar contacto
                    $data = json_decode((string)$request->getBody(), true);
                    
                    if (empty($data['id'])) {
                        return new Response(
                            400,
                            array_merge($headers, ['Content-Type' => 'application/json']),
                            json_encode(['error' => 'ID de contacto no proporcionado'])
                        );
                    }
                    
                    $id = (int)$data['id'];
                    
                    $promise = $db->query('DELETE FROM contacts WHERE id = ?', [$id])
                        ->then(
                            function (QueryResult $result) use ($headers) {
                                if ($result->affectedRows === 0) {
                                    return new Response(
                                        404,
                                        array_merge($headers, ['Content-Type' => 'application/json']),
                                        json_encode(['error' => 'Contacto no encontrado'])
                                    );
                                }
                                return new Response(
                                    200,
                                    array_merge($headers, ['Content-Type' => 'application/json']),
                                    json_encode(['success' => true, 'message' => 'Contacto eliminado'])
                                );
                            },
                            function (Exception $error) use ($headers) {
                                return new Response(
                                    500,
                                    array_merge($headers, ['Content-Type' => 'application/json']),
                                    json_encode(['error' => 'Error al eliminar contacto: ' . $error->getMessage()])
                                );
                            }
                        );
                    return $promise;
                    
                default:
                    return new Response(
                        405,
                        array_merge($headers, ['Content-Type' => 'application/json']),
                        json_encode(['error' => 'Método no permitido'])
                    );
            }
            break;
            
        case preg_match('/\.css$/', $path) ? true : false:
            return serveStaticFile($path, 'text/css');
            
        case preg_match('/\.(jpg|jpeg|png|gif)$/', $path) ? true : false:
            return serveStaticFile($path, 'image/' . pathinfo($path, PATHINFO_EXTENSION));
            
        default:
            return new Response(404, ['Content-Type' => 'text/html'], '404 Not Found');
    }
});

$socket = new SocketServer("0.0.0.0:$port");
$http->listen($socket);

echo "Servidor corriendo en http://localhost:$port\n";




