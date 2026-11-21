# ScrumBoard UB — Gestor de historias de usuario y sprints (microservicios)

Versión de **microservicios** del mismo sistema del taller monolítico (`gestor_historias_monolito`): la aplicación se divide en **servicios independientes** que se comunican por HTTP y devuelven JSON.
Materia: Programación Avanzada · Universidad de Boyacá. Stack: PHP 8 + MySQL, HTML, CSS y JavaScript puros.

## Arquitectura

```
                         ┌──────────────────────────────┐
  Navegador ───────────► │  API GATEWAY + Front  :8000  │
  (front/ HTML+JS)       └──────┬──────────┬─────────┬──┘
                      /api/sprints  /api/historias  /api/informes
                                │          │         │
                       ┌────────▼───┐ ┌────▼───────┐ ┌▼───────────┐
                       │  SPRINTS   │ │ HISTORIAS  │ │  INFORMES  │
                       │   :8001    │◄┤   :8002    │◄┤   :8003    │
                       └─────┬──────┘ └─────┬──────┘ └────────────┘
                             │              │        (sin base de datos:
                       ┌─────▼──────┐ ┌─────▼──────┐  consulta a los otros dos)
                       │ sprints_db │ │historias_db│
                       └────────────┘ └────────────┘
```

| Servicio | Puerto | Responsabilidad | Base de datos | Llama a |
|---|---|---|---|---|
| **sprints-service** | 8001 | CRUD de sprints | `sprints_db` | historias (antes de eliminar un sprint) |
| **historias-service** | 8002 | CRUD de historias y flujo de estados | `historias_db` | sprints (valida que el sprint exista) |
| **informes-service** | 8003 | Reportes de avance | ninguna | sprints e historias |
| **gateway** | 8000 | Punto de entrada único: sirve el front y reenvía `/api/*` | — | los tres servicios |

## Cómo ejecutarlo en XAMPP (Windows)

1. Copia la carpeta `gestor_historias_microservicios` donde quieras (no hace falta `htdocs`).
2. Enciende **MySQL** en XAMPP. **Apache no es necesario**: cada servicio corre con el servidor integrado de PHP.
3. En `http://localhost/phpmyadmin` → pestaña **SQL** (sin elegir base), pega `database.sql` y pulsa **Continuar**. Crea las bases `sprints_db` e `historias_db` con datos de ejemplo.
4. Haz doble clic en **`iniciar.bat`**: abre 4 ventanas (3 servicios + gateway) y el navegador en `http://127.0.0.1:8000/`.
   - Si tu XAMPP no está en `C:\xampp`, edita la línea `set PHP=...` del `.bat`.
   - Linux/macOS: `./iniciar.sh`.
5. Si MySQL tiene contraseña, cámbiala en `sprints-service/config.php` y `historias-service/config.php`.

El pie de página del front muestra con un punto verde/rojo si cada servicio responde.

## API (se puede probar con Postman o `curl`)

**Sprints** — `/api/sprints`
`GET /` · `GET /{id}` · `POST /` · `PUT /{id}` · `DELETE /{id}` (409 si el sprint tiene historias)

**Historias** — `/api/historias`
`GET /?sprint_id=&estado=` · `GET /{id}` · `POST /` · `PUT /{id}` · `POST /{id}/avanzar` · `DELETE /{id}`

**Informes** — `/api/informes`
`GET /general` · `GET /sprint/{id}`

Cada servicio también expone `GET /<nombre>/health`. Los errores usan códigos HTTP: `404` no existe, `409` conflicto de negocio, `422` datos inválidos, `503` otro servicio no responde.

Ejemplo:
```bash
curl -X POST http://127.0.0.1:8000/api/historias -H "Content-Type: application/json" \
  -d '{"titulo":"Exportar a PDF","descripcion":"Como líder quiero exportar el informe","responsable":"Laura","puntos":5,"sprint_id":2}'
```

## Del monolito a microservicios: qué cambió

| | Monolito | Microservicios |
|---|---|---|
| Despliegue | Una carpeta, una aplicación | 3 servicios + gateway, cada uno por separado |
| Base de datos | Una sola, con llave foránea `historias → sprints` | Una por servicio; **sin llave foránea** entre ellas |
| Integridad entre módulos | La resuelve MySQL (`ON DELETE CASCADE`) | La resuelven los servicios: historias pregunta a sprints si el sprint existe; sprints pregunta a historias antes de borrar |
| Comunicación | Llamadas a funciones/clases | Peticiones HTTP con JSON |
| Informes | Consulta SQL con `JOIN` | El servicio de informes arma el reporte con datos de los otros dos |
| Interfaz | Vistas PHP renderizadas en el servidor | Front estático en JS que consume la API |
| Si un módulo falla | Cae toda la aplicación | Los demás siguen (ej.: sin informes, aún se gestionan sprints e historias) |

Decisiones de diseño para sustentar:
- **Base de datos por servicio:** cada servicio es dueño de sus datos; nadie más lee sus tablas, solo su API.
- **Eliminar un sprint:** en el monolito borraba las historias en cascada; aquí se **bloquea** si tiene historias (respuesta `409`), porque un servicio no debe borrar datos de otro.
- **API Gateway:** el front conoce una sola dirección y no necesita CORS; los servicios pueden cambiar de puerto sin tocar el front.
- **Tolerancia a fallos:** si un servicio no responde, los otros devuelven un `503` con un mensaje claro en vez de romperse.
- **`lib.php` repetido:** cada servicio lleva su copia de las utilidades; así son realmente independientes y se podrían separar en repositorios distintos.

## Estructura

```
gestor_historias_microservicios/
├── database.sql           Crea sprints_db e historias_db con datos de ejemplo
├── iniciar.bat / .sh      Arrancan los 4 procesos
├── gateway/router.php     API Gateway + servidor del front
├── sprints-service/       index.php (rutas), lib.php (utilidades), config.php
├── historias-service/     index.php, lib.php, config.php
├── informes-service/      index.php, lib.php, config.php (sin base de datos)
└── front/                 index.html, js/app.js, css/style.css
```

## Mejoras posibles
Autenticación con tokens en el gateway, cola de mensajes para eventos entre servicios, un contenedor Docker por servicio (`docker compose`), pruebas automáticas por servicio.
