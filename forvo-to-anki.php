<?php

/**
 * Process the CSV file
 */
function getWordList($file, $index) {
    $row = 1;
    file_put_contents("log.txt", "\n\nProcessing wordlist\t" . date(DATE_COOKIE) . "\n", FILE_APPEND);
    if (($handle = fopen($file, "r")) !== false) {
        while (($data = fgetcsv($handle)) !== false) {
            if (isset($data[$index])) {
                echo "Processing row $row.\n";
                getPronunciation(rtrim($data[$index]));
            } else die("Invalid index on row $row.\nScript terminated.\n");
            $row++;
        }
        echo "Wordlist processing complete.\n\n";
        file_put_contents("log.txt", "Wordlist processing complete.\n\n", FILE_APPEND);
    }
}

/**
 * Fetch the best pronunication from Forvo
 */
function getPronunciation($word) {
    global $config;
    $json = json_decode(file_get_contents("http://apifree.forvo.com/key/" . $config["api_key"] . "/format/json/action/word-pronunciations/word/" . urlencode($word) . "/language/" . $config["language"] . "\n"));

    if (is_array($json) && $json[0] == "Limit/day reached.") {
    	file_put_contents("log.txt", "Forvo daily API request limit reached.\n", FILE_APPEND);
        die("Forvo daily API request limit reached.\nCannot continue program execution.\nPlease try again tomorrow.\n");
    }
    
    if (!isset($json)) {
        file_put_contents("log.txt", "Invalid data recieved for $word.\n", FILE_APPEND);
        return;
    }
    if (empty($json->items)) {
        file_put_contents("log.txt", "No pronunication found for $word.\n", FILE_APPEND);
        return;
    }
    
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
    
    if (!isset($audio)) {
        file_put_contents("log.txt", "Error retrieving audio file for $word.\n", FILE_APPEND);
        return;
    }
    if (PHP_OS == "WINNT") {
        $result = file_put_contents("wfio://" . $config["media_directory"] . "\\" . $word . ".mp3", $audio);
    } else {
        $result = file_put_contents($config["media_directory"] . "\\" . $word . ".mp3", $audio);
    }
    
    if ($result) {
        file_put_contents("log.txt", "Saved $word.mp3 to Anki media directory.\n", FILE_APPEND);
    } else {
        file_put_contents("log.txt", "Error saving $word.mp3.\n", FILE_APPEND);
    }
}

/**
 * Verify configuration file exists and is valid
 */
$config = @parse_ini_file('config.ini');
if (!$config || !isset($config["api_key"]) || !isset($config["language"]) || !isset($config["media_directory"])) die("Config file \"config.ini\" missing or invalid.\nPlease see README.md for config settings.\n");

/**
 * Verify user has input a file and an index for csv parsing
 */
if (isset($argv[1]) && is_file($argv[1]) && isset($argv[2]) && is_numeric($argv[2])) {
    getWordList($argv[1], $argv[2]);
} else die("Usage:\t\tphp forvo-to-anki.php \"path_to_csv_file\" \"index_of_words_to_be_translated\"\nExample:\tphp -f forvo-to-anki.php \"C:\\wordlist.csv\" 1\n");
?>
