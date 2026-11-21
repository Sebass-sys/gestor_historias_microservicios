@echo off
REM Inicia los 3 microservicios y el gateway (Windows + XAMPP).
REM Requisitos: MySQL encendido en XAMPP y database.sql ya importado.
REM Si tu XAMPP no esta en C:\xampp, cambia la ruta de PHP aqui:
set PHP=C:\xampp\php\php.exe

cd /d "%~dp0"

start "Servicio SPRINTS (8001)"   cmd /k "%PHP% -S 127.0.0.1:8001 -t sprints-service sprints-service\index.php"
start "Servicio HISTORIAS (8002)" cmd /k "%PHP% -S 127.0.0.1:8002 -t historias-service historias-service\index.php"
start "Servicio INFORMES (8003)"  cmd /k "%PHP% -S 127.0.0.1:8003 -t informes-service informes-service\index.php"
start "API GATEWAY (8000)"        cmd /k "%PHP% -S 127.0.0.1:8000 -t gateway gateway\router.php"

timeout /t 2 >nul
start http://127.0.0.1:8000/
echo Servicios iniciados. Cierra las 4 ventanas negras para detenerlos.
