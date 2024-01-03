:: Run easy-coding-standard (ecs) via this batch file inside your IDE e.g. PhpStorm (Windows only)
:: Install inside PhpStorm the  "Batch Script Support" plugin
cd..
cd..
cd..
cd..
cd..
cd..
php vendor\bin\ecs check vendor/markocupic/contao-oauth2-client/src --fix --config vendor/markocupic/contao-oauth2-client/tools/ecs/config.php
php vendor\bin\ecs check vendor/markocupic/contao-oauth2-client/contao --fix --config vendor/markocupic/contao-oauth2-client/tools/ecs/config.php
php vendor\bin\ecs check vendor/markocupic/contao-oauth2-client/config --fix --config vendor/markocupic/contao-oauth2-client/tools/ecs/config.php
php vendor\bin\ecs check vendor/markocupic/contao-oauth2-client/templates --fix --config vendor/markocupic/contao-oauth2-client/tools/ecs/config.php
php vendor\bin\ecs check vendor/markocupic/contao-oauth2-client/tests --fix --config vendor/markocupic/contao-oauth2-client/tools/ecs/config.php
