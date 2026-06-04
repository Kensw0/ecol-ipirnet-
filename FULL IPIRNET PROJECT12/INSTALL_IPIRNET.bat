@echo off
setlocal EnableDelayedExpansion
color 0b
title INSTALLATEUR IPIRNET 2026 - RELOADED PRO
cd /d "%~dp0"

:init
cls
echo.
echo  ===========================================================
echo       INSTALLATEUR AUTOMATIQUE  -  IPIRNET 2026
echo       Version : Finale "Audit Edition"
echo  ===========================================================
echo.

:: -----------------------------------------------------------
:: 1. CHECK ADMIN (Optional but recommended)
:: -----------------------------------------------------------
net session >nul 2>&1
if %errorLevel% == 0 (
    echo  [OK] Mode Administrateur detecte.
) else (
    echo  [!] Note : Le mode Administrateur n'est PAS actif. 
    echo      Si XAMPP crash a la fermeture, relancez en Admin.
)
echo.

:: -----------------------------------------------------------
:: 2. DETECT XAMPP
:: -----------------------------------------------------------
echo  [..] Recherche de XAMPP sur votre disque...
set "XAMPP_DIR="
for %%P in ("C:\xampp" "D:\xampp" "E:\xampp" "C:\Program Files\xampp") do (
    if exist "%%~P\mysql\bin\mysql.exe" (set "XAMPP_DIR=%%~P" & goto :found_xampp)
)
for /f "tokens=2*" %%A in ('reg query "HKLM\SOFTWARE\xampp" /ve 2^>nul') do (
    if exist "%%B\mysql\bin\mysql.exe" (set "XAMPP_DIR=%%B" & goto :found_xampp)
)

echo  [X] ERREUR : XAMPP introuvable. 
pause & exit /b 1

:found_xampp
echo  [OK] XAMPP trouve dans : %XAMPP_DIR%
echo.

:: -----------------------------------------------------------
:: 3. START SERVICES (RELIABLE METHOD)
:: -----------------------------------------------------------
echo  [1/4] Activation des services web et DB...

:: Attempt to start via official XAMPP control logic to avoid "Access Violation"
if exist "%XAMPP_DIR%\xampp_start.exe" (
    start "" /B "%XAMPP_DIR%\xampp_start.exe" >nul 2>&1
) else (
    powershell -Command "Start-Process '%XAMPP_DIR%\apache\bin\httpd.exe' -WindowStyle Hidden"
    powershell -Command "Start-Process '%XAMPP_DIR%\mysql\bin\mysqld.exe' -ArgumentList '--defaults-file=%XAMPP_DIR%\mysql\bin\my.ini' -WindowStyle Hidden"
)

:: Wait for MySQL
set /a count=0
:wait_mysql
set /a count+=1
if %count% GTR 10 goto :db_section
"%XAMPP_DIR%\mysql\bin\mysqladmin.exe" -u root ping >nul 2>&1
if errorlevel 1 (
    <nul set /p=.
    timeout /t 2 /nobreak >nul
    goto :wait_mysql
)
echo  [OK] Services prets.
echo.

:db_section
:: -----------------------------------------------------------
:: 4. DEPLOY CODE
:: -----------------------------------------------------------
echo  [2/4] Deploiement du code IPIRNET...
if exist "gestion_des_stagiaires" (
    xcopy /E /I /Y "gestion_des_stagiaires" "%XAMPP_DIR%\htdocs\gestion_des_stagiaires" >nul
    echo        Termine.
) else (
    echo  [X] Dossier source manquant !
    pause & exit /b 1
)
echo.

:: -----------------------------------------------------------
:: 5. DATABASE CONFIG
:: -----------------------------------------------------------
echo  [3/4] Preparation de la base de donnees...
"%XAMPP_DIR%\mysql\bin\mysql.exe" -u root -e "CREATE DATABASE IF NOT EXISTS gestion_des_stagiaires CHARACTER SET utf8mb4;" 2>nul
echo        Termine.

echo  [4/4] Importation des tables et donnees...
if exist "gestion_des_stagiaires.sql" (
    "%XAMPP_DIR%\mysql\bin\mysql.exe" -u root gestion_des_stagiaires < "gestion_des_stagiaires.sql"
    echo        Termine.
) else (
    echo  [X] Fichier SQL manquant !
    pause & exit /b 1
)
echo.

:: -----------------------------------------------------------
:: 6. FINAL LAUNCH (ROBUST METHOD)
:: -----------------------------------------------------------
echo  ===========================================================
echo     INSTALLATION REUSSIE - IPIRNET PRO 2026
echo  ===========================================================
echo.
echo  Lancement du navigateur...

:: Use Explorer to launch URLs (Fixes Admin-to-Browser issues)
explorer "http://localhost/gestion_des_stagiaires/"
explorer "http://localhost/phpmyadmin/"

:: Launch XAMPP Control Panel last
start "" "%XAMPP_DIR%\xampp-control.exe"

echo.
echo  Projet operationnel ! Fermeture automatique...
timeout /t 3 >nul
exit
