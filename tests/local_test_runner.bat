echo off
cd /D "%~dp0"
c:\xampp\mysql\bin\mysql.exe -u f3_boilerplate_test --password="testing" f3_boilerplate_test < ..\app\db\testing.sql
move /y ..\app\config\config.php ..\app\config\config.orig.php
copy /y ..\app\config\config.testing.php ..\app\config\config.php
c:\xampp\php\php.exe test_runner.php & move /y ..\app\config\config.orig.php ..\app\config\config.php