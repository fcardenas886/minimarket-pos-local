@echo off
chcp 65001 > nul
echo =======================================================
echo    MINIMARKET POS - PUBLICADOR AUTOMATICO A GITHUB
echo =======================================================
php "%~dp0scripts\publicar.php"
pause
