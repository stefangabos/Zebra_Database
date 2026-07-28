@echo off
::
:: Runs the test suite. The Windows counterpart of run-tests.sh - keep the two in step.
::
:: Connection settings come from phpunit.xml if present, or from phpunit.xml.dist otherwise - see the
:: comments in phpunit.xml.dist for how to point the suite at your own database.
::
:: Any arguments are passed straight through to PHPUnit, so the usual flags all work:
::
::   run-tests.bat                                  run everything
::   run-tests.bat --testdox                        readable output
::   run-tests.bat --filter dcount                  only tests matching "dcount"
::   run-tests.bat CacheTest.php                    a single file
::   run-tests.bat --coverage-html coverage-html    with a coverage report (needs xdebug or pcov)
::   run-tests.bat --static                         also the compatibility, static analysis and
::                                                  coding standard checks
::
:: The three checks that --static adds are also available on their own, and those are the ones to use
:: while working through what they report, since they take arguments:
::
::   composer check-compat / check-compat-legacy / analyse / check-style
::
:: Set PHP to use an interpreter that is not the one on the PATH - handy for checking the suite against
:: another version, and required on setups where "php" is not on the PATH at all:
::
::   set "PHP=C:\php\8.3\php.exe"
::   run-tests.bat
::
:: The memcache and redis tests skip themselves unless both a suitable extension and a running server are
:: found. To include them, start the two servers first - they need no configuration and hold nothing of
:: value, so they can be stopped again afterwards.
::

setlocal enabledelayedexpansion

cd /d "%~dp0"

if "%PHP%"=="" set "PHP=php"

:: pull --static out of the arguments and pass everything else on to PHPUnit
set RUN_STATIC=0
set "ARGS="

:parse_arguments
if "%~1"=="" goto arguments_parsed
if /i "%~1"=="--static" (
    set RUN_STATIC=1
) else (
    set "ARGS=!ARGS! %1"
)
shift
goto parse_arguments
:arguments_parsed

:: running it is a better check than "where", which only searches the PATH and would refuse a PHP set to
:: a full path like C:\php\8.3\php.exe
"%PHP%" -v >nul 2>nul
if errorlevel 1 (
    echo PHP interpreter "%PHP%" not found - put php on your PATH or set PHP=C:\path\to\php.exe
    exit /b 1
)

if not exist ..\vendor\bin\phpunit (
    echo PHPUnit not found - run "composer install" in the project root first.
    exit /b 1
)

for /f "delims=" %%V in ('"%PHP%" -r "echo PHP_BINARY . ' (' . PHP_VERSION . ')';"') do echo Using %%V

"%PHP%" ..\vendor\bin\phpunit%ARGS%
set TEST_RESULT=%errorlevel%

if "%RUN_STATIC%"=="0" exit /b %TEST_RESULT%

:: the static analysis runs from the project root, where its configuration lives - phpstan.neon and
:: coding-standards.xml both name paths relative to it
cd ..

echo.
echo --- php compatibility ---
"%PHP%" vendor\bin\phpcs -p --standard=tests/php-compatibility.xml --runtime-set ignore_warnings_on_exit 1

echo.
echo --- static analysis ---
"%PHP%" vendor\bin\phpstan analyse --no-progress

echo.
echo --- coding standard ---
"%PHP%" vendor\bin\phpcs -p --standard=coding-standards.xml --report=summary

exit /b %TEST_RESULT%
