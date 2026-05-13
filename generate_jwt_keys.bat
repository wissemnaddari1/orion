@echo off
set "OPENSSL_CONF=C:\Program Files\Git\mingw64\etc\ssl\openssl.cnf"
if not exist "%OPENSSL_CONF%" set "OPENSSL_CONF=C:\Program Files\Git\usr\ssl\openssl.cnf"
php "%~dp0generate_jwt_keys.php" %*
exit /b %errorlevel%
