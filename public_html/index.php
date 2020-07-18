<?php
require 'vendor/autoload.php';
require 'DBConnection.php';

use App\SQLiteConnection;
use GuzzleHttp\Client;

class circleCISlackNotifier
{

	private $usersNamesMap = [];

	private $token;
	private $tokenBot;
	private $channel;
	private $limit;
	private $asUser;
	private $pdo;
	private $baseUriHistory;
	private $baseUriMessage;
	private $logFileName;
	private $slackMessage;
	/**
	 * @var array
	 */
	private $resultMessages = [];
	/**
	 * @var array
	 */
	private $messagesToSend = [];

	public function __construct()
	{

		$dotenv = \Dotenv\Dotenv::createImmutable(__DIR__);
		$dotenv->load();

		$this->token = $_ENV['TOKEN'];
		$this->tokenBot = $_ENV['TOKEN_BOT'];
		$this->channel = $_ENV['SLACK_CHANNEL']; // dev-circleci
		$this->limit = $_ENV['LIMIT'];
		$this->asUser = $_ENV['SEND_AS_USER']; // circleci-notification
		$this->pdo = (new DBConnection())->connect();
		$this->baseUriHistory = $_ENV['BASE_URI_HISTORY'];
		$this->baseUriMessage = $_ENV['BASE_URI_MESSAGE'];
		$this->logFileName = $_ENV['LOG_FILE_NAME'];
		$this->slackMessage = $_ENV['SLACK_MESSAGE'];

	}

	/**
	 * @param $url
	 *
	 * @return mixed
	 * @throws \GuzzleHttp\Exception\GuzzleException
	 */
	function callSlack($url)
	{

		try {

			$client = new Client(['base_uri' => $url, 'timeout' => 5.0,]);
			$response = $client->request('GET');
			$response->getBody();

			return json_decode($response->getBody(), JSON_OBJECT_AS_ARRAY);
		} catch (\GuzzleHttp\Exception\GuzzleException $e) {

			echo "{\"code\": " . $e->getCode() . ",\"message\": \"Exception\", \"data\":\"" . $e->getMessage() . "\",time\": " . date("Y-m-d H:i:s") . "}\n";
			die();

		}

	}

	/**
	 * @throws \GuzzleHttp\Exception\GuzzleException
	 */
	function getMessage()
	{

		$fileMessage = $this->logFileName . ': ' . filesize($this->logFileName) . ' bytes';
		if (filesize($this->logFileName) > 1000000) {
			$fp = fopen($this->logFileName, "r+");
			ftruncate($fp, 0);
			fclose($fp);
			echo $fileMessage . "\n";
			echo "file was Truncated\n";
		}

		$url = $this->baseUriHistory . $this->token . "&channel=" . $this->channel . "&limit=" . $this->limit . "&pretty=1";
		$body = $this->callSlack($url);
		$messages = [];
		$this->getUsersNamesMap();

		foreach ($body['messages'] as $message) {

			$ts = $message['ts'];
			$hash = hash('ripemd160', json_encode($message));
			if (!isset($message['attachments'])) {
				$this->resultMessages[] = "{\"sent\": false, \"hash\": \"$hash\", \"message\": \"Skipping this message => " . $message['text'] . " \", \"time\": " . date("Y-m-d H:i:s") . "}\n";
				continue;
			}

			$user = $message['attachments'][0]['text'];
			$user = strstr($user, "Failed: "); //gets all text from needle on
			$user = str_replace("Failed: ", '', strstr($user, "'s", true)); //gets all text before needle
			$slackId = $this->usersNamesMap[$user]['slack_id'];
			$userName = $this->usersNamesMap[$user]['name'];


			if ($this->checkNotificationSent($hash, $ts)) {

				$messageOriginal = $message['attachments'][0]['fallback'];

				preg_match_all('#\bhttps?://[^,\s()<>]+(?:\([\w\d]+\)|([^,[:punct:]\s]|/))#', $messageOriginal, $matchLink);

				$urls = "";
				$urls .= "Build:  => <" . $matchLink[0][4] . ">\n\n";
				$urls .= "Commit: => <" . $matchLink[0][3] . ">\n\n";

				$this->messagesToSend[] = ['slackId' => $slackId, 'message' => htmlentities(strip_tags($messageOriginal)) . "\n\n" . $urls];

				$this->resultMessages[] = "{\"sent\": true, \"hash\": \"$hash\", \"message\": \"Message sent to  $userName \", \"time\": " . date("Y-m-d H:i:s") . "}\n";
			} else {
				$this->resultMessages[] = "{\"sent\": false, \"hash\": \"$hash\", \"message\": \"No message to send to $userName\", \"time\": " . date("Y-m-d H:i:s") . "}\n";
			}
		}

	}

	/**
	 * Send the notification
	 *
	 * @throws \GuzzleHttp\Exception\GuzzleException
	 */
	public function sendNotification()
	{

		foreach ($this->messagesToSend as $data) {

			$message = "<@" . $data['slackId'] . ">,  $this->slackMessage." . urlencode($data['message']) . "\n\n";
			$url = $this->baseUriMessage . $this->tokenBot . "&channel=" . $data['slackId'] . "&text=" . $message . "&as_user=" . $this->asUser . "&pretty=1";
			$this->callSlack($url);

		}

	}

	/**
	 * Get Users name map from table github_slack_users
	 *
	 * @return bool
	 *
	 */
	private function getUsersNamesMap()
	{

		try {

			$stmt = $this->pdo->query("SELECT * FROM github_slack_users");
			if (!$stmt) {

				$message = json_encode($this->pdo->errorInfo());
				echo "{\"message\": \"$message\", time\": " . date("Y-m-d H:i:s") . "}\n";
				die();

			}

			$users = $stmt->fetchAll();
			foreach ($users as $user) {
				$this->usersNamesMap[$user['github_username']] = ['slack_id' => $user['slack_id'], 'name' => $user['name']];
			}

			return true;
		} catch (\PDOException $e) {
			$message = json_encode(\PDOException($e->getMessage(), (int)$e->getCode()));
			echo "{\"code\": " . $e->getCode() . ",\"message\": \"$message\", \"data\":\"" . $e->getMessage() . "\",time\": " . date("Y-m-d H:i:s") . "}\n";
			die();

		}

		return false;

	}

	/**
	 * Check id the notification was already sent
	 *
	 * @param $hash
	 *
	 * @param $ts
	 *
	 * @return bool
	 */
	private function checkNotificationSent($hash, $ts)
	{

		try {

			$stmt = $this->pdo->query("SELECT * FROM circleci_slack_hash WHERE hash = '" . $hash . "' AND '" . $ts . "'");
			if (!$stmt) {

				$message = json_encode($this->pdo->errorInfo());
				echo "{\"message\": \"$message\", time\": " . date("Y-m-d H:i:s") . "}\n";
				die();

			}

			$result = $stmt->fetchAll();

			if (count($result) >= 1) {
				return false;
			} else {

				// if not exist
				$sql = "INSERT INTO circleci_slack_hash(hash, ts) VALUES('" . $hash . "', '" . $ts . "')";
				$stmt = $this->pdo->prepare($sql);
				if (!$stmt) {
					die("Execute query error, because: " . print_r($this->pdo->errorInfo(), true));
				}
				$stmt->execute();

				return true;
			}

		} catch (\PDOException $e) {
			$message = json_encode(\PDOException($e->getMessage(), (int)$e->getCode()));
			echo "{\"code\": " . $e->getCode() . ",\"message\": \"$message\", \"data\":\"" . $e->getMessage() . "\",time\": " . date("Y-m-d H:i:s") . "}\n";
			die();

		}

		return false;
	}

	/**
	 * Run the app entry point
	 *
	 * @throws \GuzzleHttp\Exception\GuzzleException
	 */
	public function entryPoint($echo = true)
	{

		$this->getMessage();
		$this->sendNotification();

		if ($echo) {

			print_r($this->resultMessages);

			return;

		}

		return $result;

	}


}

$slackNotifier = new circleCISlackNotifier();
$slackNotifier->entryPoint(true);
