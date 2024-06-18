<?php


/**
 * This PHP script, `counterFolder.php`, manages the directory structure for audio files associated with LimeSurvey sessions.
 * It provides functionalities to construct and retrieve paths for audio storage, count subfolders, and manage a counter
 * that tracks the use of audio subfolders per session. The script is designed to ensure that audio file usage is evenly
 * distributed and managed across survey sessions.
 *
 * Major Functions:
 * - `getPoolPath($surveyId)`: Constructs the path to the pool of audio files for a given survey ID.
 * - `getTotalSubfolders($poolPath)`: Returns the total number of subfolders in the specified pool path.
 * - `updateSubfolderCounter($surveyId, $sessionId)`: Updates or initializes a counter file that tracks which subfolders
 *   have been used for each session, helping to prevent the repeated use of audio files within the same survey session.
 * - `getCurrentSubfolder($surveyId, $sessionId)`: Retrieves the current subfolder for storing or retrieving audio files based on
 *   a counter that cycles through available subfolders.
 *
 * These functions interact with the filesystem to read, write, and modify properties related to audio file management within surveys.
 * Proper logging is implemented to monitor actions and catch potential errors during the operations.
 *
 * Usage:
 * This script is included in other parts of the LimeSurvey plugin where audio file management is needed. It relies on session variables
 * set by other components of the system, specifically `$_SESSION['PLAY_sessionId']` to link audio file usage to specific survey sessions.
 *
 * Note:
 * The system assumes the existence of a structured directory for audio files as defined in the `getPoolPath` function. The script should be
 * included or required in PHP files where audio file directory management is necessary, typically in survey lifecycle hooks or event handlers.
 *
 * @package LimeSurvey\Plugin
 * @category AudioManagement
 * @author Alejandro Gallón
 * @copyright 2023, SchimmerCreative
 * @license GNU General Public License version 2 or later
 * @link www.schimmercreative.com
 */



Yii::import('application.helpers.common_helper', true);


// Initialize surveyId variable
$surveyId = null;

        // Check if $event is set and has 'surveyId'
        if (isset($event) && $event->get('surveyId')) {

            $surveyId = $event->get('surveyId');

                //Yii::log("Survey ID in counterFolder obtained from event: " . $surveyId, 'info');

        } else {

                //Yii::log("Event not set in counterFolder or survey ID not available in event.", 'warning');
                Yii::log("waiting to initialize afterSurveyComplete Method in counterFolder.", 'info');
        }


// Fetch the session ID from the session variable set in AudioPlayerRandomizer.php
$sessionId = $_SESSION['PLAY_sessionId'] ?? null;

        // Check if the session ID is available
        if ($sessionId === null) {

            Yii::log("No valid session ID available.  in counterFolder.", 'warning');

            return;
        } 

















/**
 * Constructs and returns the path to the storage directory for audio files associated with a specific survey ID.
 * This path serves as the base location for all audio files related to the survey, facilitating organized
 * storage and retrieval of these files.
 *
 * The function constructs the path by appending the survey ID to a predefined base path, ensuring that
 * each survey has a unique and isolated directory for its audio files. This is crucial for managing audio
 * resources efficiently and securely, avoiding conflicts and unauthorized access between different surveys.
 *
 * Usage:
 * The returned path is typically used by functions that handle file operations such as reading from or writing
 * to the audio file directory. It is a fundamental utility function for any file management operations in the
 * LimeSurvey audio integration plugin.
 *
 * Example:
 * If a survey with ID 12345 needs to access its audio files, this function will provide the path
 * "C:/xampp/htdocs/limesurvey5/upload/surveys/12345/files", which is used to locate or store audio files.
 *
 * @param int $surveyId The unique identifier of the survey, used to generate the specific path for that survey's audio files.
 * @return string Returns the fully qualified path to the directory where the survey's audio files are stored.
 *                This path is dependent on the server's directory structure and the location of the LimeSurvey installation.
 */
function getPoolPath($surveyId) {

                Yii::log('getPoolPath triggered', 'info');

    
    $basePath = "/[path_to_web_directory]/audioSurvey/upload/surveys";
    //change /audioSurvey/ to your limeSurvey name installation

    $poolPath = "{$basePath}/{$surveyId}/files";

                //Yii::log("Pool path in getPoolPath counterFolderphp for survey {$surveyId}: {$poolPath}", 'info');

    return $poolPath;
}

















/**
 * Calculates and returns the total number of subdirectories within a specified directory path. This function
 * is essential for managing and monitoring the directory structure used for storing audio files in a LimeSurvey
 * installation, particularly when handling multiple surveys with separate audio resources.
 *
 * The function checks if the provided directory path exists and is valid. If valid, it uses the glob function
 * to count all direct subdirectories. This count is critical for functions that distribute or rotate through
 * audio files to avoid overuse of any single directory or to implement load balancing across multiple resources.
 *
 * If the path does not exist or an error occurs during directory reading, the function logs the issue and
 * returns zero, indicating no subdirectories are available or an error state.
 *
 * Usage:
 * This function is typically called to determine the number of audio file containers (subdirectories) available
 * for a survey, aiding in tasks such as load distribution or subdirectory selection.
 *
 * Example:
 * Given a directory path to the survey's audio file storage, this function provides the number of subdirectories
 * to facilitate further processing or monitoring.
 *
 * @param string $poolPath The path to the directory whose subdirectories are to be counted. This path should
 *                          be a fully qualified path to a directory on the server.
 * @return int The number of subdirectories within the specified directory. Returns 0 if the directory does not exist,
 *             is not a directory, or an error occurs during the read operation.
 */
function getTotalSubfolders($poolPath) {


                Yii::log('getTotalSubfolders triggered', 'info');


        // Check if the specified path exists and is a directory
        if (!file_exists($poolPath) || !is_dir($poolPath)) {

                Yii::log("Specified pool path '{$poolPath}' does not exist or is not a directory.", 'error');

            return 0; // Return 0 as the total number of subfolders if the path is invalid
        }
    
    // Use glob to get subdirectories
    $subfolders = glob("{$poolPath}/*", GLOB_ONLYDIR);
    
        // Check if glob() encountered an error
        if ($subfolders === false) {

                Yii::log("Failed to open directory or read its contents: '{$poolPath}'.", 'error');

            return 0; // Return 0 as the total number of subfolders in case of an error
        }
    
    // Log the total number of subfolders found
    $totalSubfolders = count($subfolders);

                Yii::log("Found a total of {$totalSubfolders} subfolders in '{$poolPath}'.", 'info');
    
    return $totalSubfolders;
}











/**
 * Updates a JSON file that tracks the usage of subfolders for storing survey-specific audio files. This function 
 * ensures that audio files are not repeatedly used in the same survey session by incrementally updating a counter
 * which keeps track of the last used subfolder index.
 *
 * If the survey ID or the session ID is not valid, or if the directory for storing audio files does not exist,
 * the function will log an error and return false. It attempts to either read an existing counter file or create
 * one if it doesn't exist, and then update the counter based on the current session's usage.
 *
 * This function is critical for managing the allocation of audio files across multiple survey sessions, helping
 * to prevent the reuse of the same audio content within a single session and ensuring a balanced use of resources.
 *
 * Usage:
 * Should be called whenever a survey page that involves audio content is rendered, ideally at the end of a survey
 * session or when audio files need to be assigned to survey questions.
 *
 * Example:
 * This function is called within lifecycle hooks of the LimeSurvey plugin when the survey completes or when a new
 * survey session starts that requires audio file management.
 *
 * @param int $surveyId The identifier of the survey, used to locate the correct directory for audio files.
 * @param string $sessionId The session identifier, used to ensure unique file usage per session.
 * @return bool Returns true if the counter was successfully updated and written back to the file; otherwise, returns false.
 * @throws none Direct exceptions are not thrown, but the function logs various levels of errors based on the encountered issues.
 */
function updateSubfolderCounter($surveyId, $sessionId) {


                Yii::log('updateSubfolderCounter triggered', 'info');

        if (empty($surveyId)) {

                //Yii::log("Survey ID is missing in updateSubfolderCounter, this may be expected if the survey has not yet been initialized.", 'warning');
                Yii::log("waiting to initialize afterSurveyComplete Method in updateSubfolderCounter.", 'info');

            return false;
        }

    $poolPath = getPoolPath($surveyId);

        if (!is_dir($poolPath)) {

                Yii::log("Pool path '{$poolPath}' does not exist or is not a directory.", 'error');

            return false;
        }

    $counterFilePath = "{$poolPath}/counterSubfolderSession.json";

            // Attempt to read or create the counter file
            //Yii::log("Attempting to update subfolder counter for surveyId: {$surveyId} and sessionId: {$sessionId}", 'info');

        if (!file_exists($counterFilePath)) {

            // Initialize counter data with SubfolderTimesUsed array
            $data = ['lastUsedIndex' => -1, 'totalSubfolders' => getTotalSubfolders($poolPath), 'SubfolderTimesUsed' => array_fill(0, getTotalSubfolders($poolPath), 0)];
            
                //Yii::log("Counter file '{$counterFilePath}' does not exist. Initializing with default values.", 'info');

        } else {

            // Load existing counter data
            $data = json_decode(file_get_contents($counterFilePath), true) ?: ['lastUsedIndex' => -1, 'totalSubfolders' => getTotalSubfolders($poolPath), 'SubfolderTimesUsed' => array_fill(0, getTotalSubfolders($poolPath), 0)];
            
                //Yii::log("Loaded existing counter data from '{$counterFilePath}'.", 'info');
        }

        // Update the counter data
        if (!isset($_SESSION['subfolderIndexUpdatedForSession']) || $_SESSION['subfolderIndexUpdatedForSession'] !== $sessionId) {

            $data['lastUsedIndex'] = ($data['lastUsedIndex'] + 1) % $data['totalSubfolders'];
            $data['SubfolderTimesUsed'][$data['lastUsedIndex']] += 1;

            $_SESSION['subfolderIndexUpdatedForSession'] = $sessionId;

                Yii::log("Subfolder index updated for session: {$sessionId}, new index: {$data['lastUsedIndex']}", 'info');
        }

        // Write the updated data back to the file
        if (file_put_contents($counterFilePath, json_encode($data))) {

                //Yii::log("Successfully updated counter file at '{$counterFilePath}'.", 'info');

            return true;

        } else {

                Yii::log("Failed to write updated counter data to '{$counterFilePath}'.", 'error');   

            return false;
        }
}











/**
 * Retrieves the current subfolder name to be used for audio file storage based on a rotating index counter. 
 * This function ensures that audio files are evenly distributed across different subfolders within a specific survey's file pool.
 *
 * The function first retrieves the base directory path for the survey's audio files and checks if this path exists.
 * It then locates the counter file within this directory, which tracks the usage of subfolders. If the counter file is
 * missing or cannot be read, the function attempts to initialize or update the counter. If successful, it reads and 
 * decodes the counter data from the file to determine the last used subfolder index.
 *
 * It checks if the last used index points to a valid subfolder and returns the name of this subfolder. If any issues
 * arise during these checks, such as the absence of subfolders or invalid counter data, the function logs the error and returns null.
 *
 * Usage:
 * This function is critical for managing the rotation of audio file storage locations within surveys to prevent the
 * overuse of a single directory and to help in load balancing and file organization.
 *
 * Example:
 * After determining which survey and session are active, this function can be called to find out where the next set
 * of audio files should be stored or retrieved from.
 *
 * @param int $surveyId The survey identifier used to determine the correct audio file directory.
 * @param string $sessionId The session identifier used to access and update the correct subfolder usage counter.
 * @return string|null Returns the name of the current subfolder if successful, or null if an error occurs or the data is invalid.
 * @throws none Direct exceptions are not thrown, but the function logs errors and warnings based on the encountered conditions.
 */
function getCurrentSubfolder($surveyId, $sessionId) {


                Yii::log('getCurrentSubfolder triggered', 'info');
	
	// Retrieve the base directory path for the survey's data
    $poolPath = getPoolPath($surveyId);
	
        // Check if the pool path exists; if not, log an error and return null
        if (!$poolPath) {

                Yii::log("Pool path not found for survey ID: {$surveyId}", 'error');

            return null;
        }

	// Define the path to the counter file within the pool path
    $counterFilePath = "{$poolPath}/counterSubfolderSession.json";
	
        // Check if the counter file exists; if not, log and try to initialize counter data
        if (!file_exists($counterFilePath)) {

                    //Yii::log("Counter file '{$counterFilePath}' not found. Attempting to initialize.", 'warning');
                    Yii::log("waiting to initialize afterSurveyComplete Method in getCurrentSubfolder.", 'info');
            
            // Try to update the subfolder counter; if failed, log and return null
            if (!updateSubfolderCounter($surveyId, $sessionId)) {

                    //Yii::log("Failed to initialize in getCurrentSubfolder the counter file at '{$counterFilePath}'.", 'warning');
                    Yii::log("waiting to initialize afterSurveyComplete Method in getCurrentSubfolder.", 'info');

                return null;
            }
        }

	// Read the counter file and decode its JSON content into an associative array
    $data = json_decode(file_get_contents($counterFilePath), true);
	
        // Check if the data is valid and if necessary indices are set; if not, log an error and return null
        if (!$data || !isset($data['lastUsedIndex'], $data['totalSubfolders'])) {

            Yii::log("Invalid or incomplete counter data in '{$counterFilePath}'.", 'error');

            return null;
        }

    // Adjust the last used index to ensure it remains within the valid range of subfolders
    $data['lastUsedIndex'] = $data['lastUsedIndex'] % $data['totalSubfolders'];

	// Retrieve a list of subfolders in the pool path
    $subfolders = glob("{$poolPath}/*", GLOB_ONLYDIR);
	
        // Check if retrieving subfolders was successful; if not, log an error and return null
        if ($subfolders === false || empty($subfolders)) {

                Yii::log("Unable to read subfolders from '{$poolPath}' or no subfolders found.", 'error');

            return null;
        }
	
        // Check if the last used index points to a valid subfolder; if so, get the subfolder name
        if (isset($subfolders[$data['lastUsedIndex']])) {

            $currentSubfolderName = basename($subfolders[$data['lastUsedIndex']]);

                Yii::log("Current subfolder for session determined in getCurrentSubfolder from counterFolderPHP: {$currentSubfolderName}", 'info');

            return $currentSubfolderName;

        } else {
            
                // If the last used index does not correspond to a valid subfolder, log an error and return null
                Yii::log("Last used index '{$data['lastUsedIndex']}' does not correspond to a valid subfolder.", 'error');

            return null;
        }
}





//Logging actions check next:




// Attempt to get the current subfolder for the session based on the survey ID
$currentSubfolder = getCurrentSubfolder($surveyId, $sessionId);

    if ($currentSubfolder !== null) {

            // Log the successful retrieval of the current subfolder
            //Yii::log("Successfully retrieved current subfolder '{$currentSubfolder}' for survey ID {$surveyId}. Proceeding with audio file selection.", 'info');

            // Additional code to use $currentSubfolder for audio file selections within this session can be placed here

    } else {

            // Log the failure to retrieve a valid current subfolder
            //Yii::log("Faisled to retrieve from counterFolderPHP a valid current subfolder for survey ID {$surveyId}. check if Audio file selection cannot proceed or wait for End of Survey to be submitted.", 'warning');
            Yii::log("waiting to initialize afterSurveyComplete Method to retrieve a valid currentSubfolder.", 'info');
    }

// Usage example: Demonstrating how to log the operation of getting the pool path and checking its validity
$poolPath = getPoolPath($surveyId);

    if ($poolPath) {

            // Log that the pool path was successfully obtained
            //Yii::log("Successfully obtained pool path '{$poolPath}' for survey ID {$surveyId}.", 'info');
            
            // Here, you could add code that utilizes the $poolPath, such as scanning for files or subdirectories within it
        
    } else {

            // Log an error if the pool path could not be determined
            //Yii::log("Failed to obtain a valid pool path for survey ID {$surveyId}. Check the survey ID validity and the server's directory structure.", 'error');
            Yii::log("waiting to initialize afterSurveyComplete Method to obtain a valid pool path.", 'info');

    }

?>
