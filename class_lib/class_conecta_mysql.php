<?php
error_reporting(0);
$filePath = $_SERVER['DOCUMENT_ROOT'] . '/.env';
if (!file_exists($filePath)) {
	throw new Exception("Archivo .env no encontrado.");
}

$lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
foreach ($lines as $line) {
	if (strpos($line, '=') !== false) {
		list($name, $value) = explode('=', $line, 2);
		$name = trim($name);
		$value = str_replace('"', '', trim($value));

		if (!array_key_exists($name, $_ENV)) {
			putenv("$name=$value");
			$_ENV[$name] = $value;
			$_SERVER[$name] = $value;
		}
	}
}

if (strpos($_SERVER['HTTP_HOST'], 'roelplant') !== false) {
  $host = getenv("DB_HOST");
  $user = getenv("DB_USER");
  $password = getenv("DB_PASSWORD");
  $dbname = getenv("DB_NAME");
  $dbpresta = getenv("DB_NAME_PRESTASHOP");  
  $dbuserpresta = getenv("DB_USER_PRESTASHOP");
  $dbpasspresta = getenv("DB_PASSWORD_PRESTASHOP");
  $dbprestahost = getenv("DB_HOST_PRESTASHOP");
}
else{
  $host = getenv("DB_HOST_LOCAL");
  $user = getenv("DB_USER_LOCAL");
  $password = getenv("DB_PASSWORD_LOCAL");
  $dbname = getenv("DB_NAME_LOCAL");
  $dbpresta = getenv("DB_NAME_PRESTASHOP_LOCAL");
}

?>