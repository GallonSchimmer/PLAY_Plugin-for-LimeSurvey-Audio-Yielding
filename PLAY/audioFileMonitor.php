<?php


/**
 * This PHP script manages the scanning and updating of audio files within a specified survey's directory in LimeSurvey.
 * It scans directories for new audio files, inserts new entries into the database, and ensures that the database's
 * list of audio files remains synchronized with the file system. This process helps maintain a consistent and accurate
 * record of all audio files available for use in surveys, preventing duplication and ensuring all files are accessible
 * when needed.
 *
 * The script connects to a MySQL database where it records the paths of audio files. It performs a recursive scan of 
 * directories starting from a base directory associated with a specific survey ID, checking each file against previously
 * recorded entries in the database to avoid duplications.
 *
 * Key Functions:
 * - `scanSubdirectories($directory, $pdo, &$existingFiles)`: Recursively scans subdirectories for audio files.
 * - `scanDirectory($directory, $pdo, &$existingFiles)`: Scans a single directory and updates the database with new audio files.
 *
 * This script ensures that any new audio files placed in the survey's directory are quickly integrated into the system, 
 * available for immediate use in the survey without requiring manual updates to the database.
 *
 * Usage:
 * This script is meant to be run as part of a maintenance routine or triggered by specific actions within the LimeSurvey
 * platform that necessitate checking for new or updated audio files.
 *
 * Example:
 * A cron job could trigger this script nightly to ensure that any audio files added during the day are available for
 * surveys the following day.
 *
 * @package LimeSurvey\Plugin
 * @category AudioManagement
 * @author Alejandro Gallón
 * @copyright 2023, SchimmerCreative
 * @license GNU General Public License version 2 or later
 * @link www.schimmercreative.com
 *
 * @throws PDOException Captures and logs database connection and query errors.
 * @return void Outputs are logged to the system log, and any critical failures result in the script terminating.
 */





// Database credentials
$dbHost = 'localhost';
$dbName = ''; //Database name you used for the LimeSurvey installation
$dbUser = ''; //Database user
$dbPass = ''; //Database passsword


/**
 * Recursively scans all subdirectories within a specified base directory for audio files, updating the database
 * to reflect any new files found. This function is a key component of maintaining a synchronized and comprehensive
 * listing of audio resources available in the file system for use in LimeSurvey.
 *
 * It traverses each subdirectory found within the provided base directory, calling another function to perform the
 * actual scanning and updating process for each directory. This recursive approach ensures that no directory is
 * overlooked, allowing for a thorough update of the audio file inventory.
 *
 * Usage:
 * Typically invoked to ensure that all new files added to the directory structure of a survey's audio resources
 * are accounted for in the database, thus making them available for use in the survey system without manual intervention.
 *
 * Example:
 * When a new audio file is added to any subdirectory under the survey-specific audio storage path, this function
 * ensures it is discovered and recorded, thereby preventing any delays in its availability for survey deployment.
 *
 * @param string $directory The base directory from which the recursive scan should start. This should be the root
 *                          directory containing all subdirectories to be scanned.
 * @param PDO $pdo The PDO object for database access, used to insert new audio file records into the database.
 * @param array &$existingFiles A reference to an array that tracks the paths of files already processed, to avoid
 *                              duplication in the database.
 * @return void The function does not return a value but updates the database and the existingFiles array.
 */
function scanSubdirectories($directory, $pdo, &$existingFiles) {


                Yii::log('scanSubdirectories triggered', 'info');

    $subdirs = glob($directory . '/*', GLOB_ONLYDIR);

    foreach ($subdirs as $subdir) {

        scanDirectory($subdir, $pdo, $existingFiles);
    }
}



/**
 * Scans a specified directory for audio files (specifically .mp3 files) and updates the database with new entries if they do not already exist.
 * This function ensures that each audio file in the directory is uniquely recorded in the system's database, allowing LimeSurvey to access and
 * utilize these files without duplication.
 *
 * Each file found is checked against a list of already existing files to ensure no duplicates are added to the database. If the file is not
 * already listed, it is added both to the database and to the local tracking array to prevent future duplication during the same scan session.
 *
 * Usage:
 * This function is integral to the audio file management system in LimeSurvey, particularly when new files are added to the server's file system
 * and need to be made available for surveys without delay.
 *
 * Example:
 * If a new directory of audio files is added to a survey's resource folder, this function can be called to quickly scan that directory and 
 * integrate all new audio files into the database, ensuring they are immediately ready for use in the survey.
 *
 * @param string $directory The path to the directory that needs to be scanned for audio files.
 * @param PDO $pdo The PDO object for database access, used for inserting new audio file records.
 * @param array &$existingFiles A reference to an array that keeps track of files already processed during the current update session to avoid duplicate entries.
 * @return void The function does not return a value but directly modifies the database and the existingFiles array to reflect the new files found.
 */
function scanDirectory($directory, $pdo, &$existingFiles) {


                Yii::log('scanDirectory triggered', 'info');

    // Scan the directory for files
    $files = glob($directory . '/*.mp3');

    foreach ($files as $file) {

        $webAccessiblePath = str_replace('\\', '/', $file);

            // Use the full path to check against existing entries to ensure uniqueness
            if (!in_array($webAccessiblePath, $existingFiles)) {

                // Insert new file into the database if it's not already listed
                $insertStmt = $pdo->prepare("INSERT INTO audio_uploads (audio_url) VALUES (:audio_url) ON DUPLICATE KEY UPDATE audio_url = VALUES(audio_url)");
                $insertStmt->bindParam(':audio_url', $webAccessiblePath);
                $insertStmt->execute();

                // Add this file's full path to the list of existing files to prevent duplicates
                $existingFiles[] = $webAccessiblePath;
            }
    }
}

/**
 * This try-catch block manages the process of connecting to the database, fetching existing audio file records,
 * and scanning directories for new audio files to add to the database. The primary function of this block is to
 * ensure that the database remains synchronized with the file system and that new files are integrated seamlessly
 * into LimeSurvey's audio resources without duplication.
 *
 * The process starts by checking for a valid session ID, which is essential for processing within the context of
 * an active user session in LimeSurvey. If the session ID is valid, the script proceeds to establish a database
 * connection and fetches all existing audio file URLs to avoid re-adding files already present in the database.
 *
 * The block then scans for new audio files in the specified survey directory and updates the database accordingly.
 * If any database operations fail, such as a connection issue or a SQL error, the exception is caught, logged,
 * and the script execution is terminated to prevent corrupt data interactions.
 *
 * Usage:
 * This block is critical for maintaining the integrity and consistency of audio file data within LimeSurvey,
 * particularly in environments where new audio content is frequently added or updated.
 *
 *
 * @param none No parameters are directly passed to this block, but it uses global variables and session state.
 * @throws PDOException Handles PDO-related exceptions if database connection attempts fail or queries result in errors.
 * @return void The block does not return a value but will terminate script execution on encountering an exception.
 */
try {

    $sessionId = $_SESSION['PLAY_sessionId'] ?? null;

        if ($sessionId === null) {

                Yii::log("No valid session ID available. Skipping audio file processing.", 'warning');

            return; // Skip further processing
        }

    $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName", $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Fetch all existing audio URLs to avoid duplicates
    $existingFiles = [];
    $stmt = $pdo->query("SELECT audio_url FROM audio_uploads");

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {

            $existingFiles[] = $row['audio_url']; // Use full path for uniqueness

        }

    // survey ID is correctly obtained
    $surveyId = $event->get('surveyId');
    
    $audioDirectory = "/[path_to_web_directory]/[path_to_limesurvey]/upload/surveys/{$surveyId}/files/"; 
    //path_to_limesurvey is the name of the directory where LimeSurvey resides, the one you called your limeSurvey installation
    
	


    // Scan the subdirectories and their files
    scanSubdirectories($audioDirectory, $pdo, $existingFiles);

                Yii::log("Audio files updated successfully.", 'info');

} catch (PDOException $e) {

                Yii::log("Error: " . $e->getMessage(), 'error');

    exit();
}


?>
