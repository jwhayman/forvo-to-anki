<?php

/**
 * Process the CSV file, 
 */
function getWordList($file, $index) {
	$row = 1;
	if (($handle = fopen($file, "r")) !== false) {
		while (($data = fgetcsv($handle)) !== false) {
			if (isset($data[$index])) {
				getPronunciation($data[$index]);
			} else die("Invalid index on row $row.\nScript terminated.\n");
			$row++;
		}
	}
}

/**
 * Fetch the best pronunication from Forvo
 */
function getPronunciation($word) {
	global $config;
	$json = json_decode(file_get_contents("http://apifree.forvo.com/key/" . $config["api_key"] . "/format/json/action/word-pronunciations/word/" . $word . "/language/" . $config["language"] . "\n"));
	if (!isset($json)) { echo "Invalid data recieved for $word.\n"; return; }
	if (empty($json->items)) { echo "No pronunication found for $word.\n"; return; }

	$topItem = $json->items[0];
	$highestVotes = 0;
	foreach ($json->items as $item) {
		if ($item->num_votes > $highestVotes) {
			$topItem = $item;
			$highestVotes = $item->num_votes;
		}
	}

	saveAudioFile($word, $topItem->pathmp3);
}

/**
 * Save the audio file to Anki's media collection for later use
 */
function saveAudioFile($word, $file) {
	global $config;
	$audio = file_get_contents($file);

	if (!isset($audio)) { echo "Error retrieving audio file for $word.\n"; return; }
	if (PHP_OS == "WINNT") {
		$result = file_put_contents("wfio://" . $config["media_directory"] . "\\" . $word . ".mp3", $audio);
	}
	else {
		$result = file_put_contents($config["media_directory"] . "\\" . $word . ".mp3", $audio);
	}

	if ($result) {
		echo "Saved $word.mp3 to Anki media directory.\n";
	}
	else {
		echo "Error saving $word.mp3.";
	}
}

/**
 * Verify configuration file exists and is valid
 */
$config = @parse_ini_file('config.ini');
if (!$config || 
	!isset($config["api_key"]) || 
	!isset($config["language"]) ||
	!isset($config["media_directory"])) 
	die("Config file \"config.ini\" missing or invalid.\nPlease see README.md for config settings.\n");

$argv[1] = "A:/Russian/Wordlists/cats.csv";
$argv[2] = 0;

/**
 * Verify user has input a file and an index for csv parsing
 */
if (isset($argv[1]) && is_file($argv[1]) && isset($argv[2]) && is_int($argv[2])) {
	getWordList($argv[1], $argv[2]);
} else die("Usage:\t\tphp -f forvo-to-anki.php \"path_to_csv_file\" \"index_of_words_to_be_translated\"\nExample:\tphp -f forvo-to-anki.php \"C:\\wordlist.csv\" 1\n");