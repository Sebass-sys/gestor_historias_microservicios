<?php
// Configuración del servicio de sprints (puerto 8001).
return [
    'db' => [
        'host'    => 'localhost',
        'name'    => 'sprints_db',
        'user'    => 'root',
        'pass'    => '',
        'charset' => 'utf8mb4',
    ],
    // Dirección de otro servicio al que este necesita consultar
    'historias_url' => 'http://127.0.0.1:8002',
];
