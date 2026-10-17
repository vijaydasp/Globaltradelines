@echo off
setlocal
if exist "%~dp0..\.tools\php84\php.exe" (
    "%~dp0..\.tools\php84\php.exe" %*
) else (
    php %*
)
exit /b %errorlevel%
