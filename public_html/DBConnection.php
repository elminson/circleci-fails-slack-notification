<?php

namespace App;

/**
 * DB  connnection
 */
class DBConnection
{

	/**
	 * PDO instance
	 *
	 * @var type
	 */
	private $pdo;

	public function __construct()
	{

	}

	/**
	 * return in instance of the PDO object that connects to the SQLite database
	 *
	 * @return \PDO
	 */
	public function connect()
	{
		$dotenv = \Dotenv\Dotenv::createImmutable(__DIR__);
		$dotenv->load();
		$host = $_ENV['DB_HOST'];
		$db =  $_ENV['DB_NAME'];
		$user = $_ENV['DB_USERNAME'];
		$pass = $_ENV['DB_PASSWORD'];
		$port =  $_ENV['DB_PORT'];
		$charset = 'utf8mb4';

		$options = [
			\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
			\PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
			\PDO::ATTR_EMULATE_PREPARES => false,
		];
		$dsn = "mysql:host=$host;dbname=$db;charset=$charset;port=$port";

		if ($this->pdo == null) {
			$this->pdo = new \PDO($dsn, $user, $pass);
		}

		return $this->pdo;
	}
}