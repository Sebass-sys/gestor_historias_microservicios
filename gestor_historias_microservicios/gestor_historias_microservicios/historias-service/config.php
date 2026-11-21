<?php
// Configuración del servicio de historias (puerto 8002).
return [
    'db' => [
        'host'    => 'localhost',
        'name'    => 'historias_db',
        'user'    => 'root',
        'pass'    => '',
        'charset' => 'utf8mb4',
    ],
    // Dirección del servicio de sprints (para comprobar que el sprint existe)
    'sprints_url' => 'http://127.0.0.1:8001',
];
