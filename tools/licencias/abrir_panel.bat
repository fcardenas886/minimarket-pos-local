@echo off
title Panel de licencias - Minimarket POS
rem Abre el panel personal de licencias en http://127.0.0.1:8099 (solo este equipo).
rem Cierra esta ventana para apagar el panel.

set "PANEL=%~dp0panel"
set "PHP=php"
where php >nul 2>nul
if errorlevel 1 (
  for /d %%D in ("C:\laragon\bin\php\php-*") do set "PHP=%%D\php.exe"
)

echo Panel de licencias en http://127.0.0.1:8099
echo Cierra esta ventana para apagarlo.
start "" "http://127.0.0.1:8099"
"%PHP%" -S 127.0.0.1:8099 -t "%PANEL%"
pause
