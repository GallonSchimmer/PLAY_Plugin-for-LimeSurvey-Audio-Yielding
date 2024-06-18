<?php

/**
 * Handles the selection and marking of audio files for specific survey questions within LimeSurvey. This script includes
 * functionalities for audio file management tied to survey sessions, ensuring that each session has access to unique
 * audio files without repetition across different survey instances or within the same survey session.
 *
 * The script sets up the necessary environment, including database connections and session management, checks for the
 * existence of survey and session IDs, and selects audio files based on predefined criteria. It also converts file paths
 * to relative URLs for web accessibility and handles the marking of used audio files to avoid repeats.
 *
 * Dependencies:
 * - Requires `counterFolder.php` for managing subfolder counters related to audio file storage.
 * - Uses session variables set by other parts of the system, specifically `$_SESSION['PLAY_sessionId']`, to manage audio files.
 *
 * Functions:
 * - `convertToRelativeURL($filePath, $surveyId)`: Converts a file path into a relative URL based on the survey ID.
 * - `selectAndMarkAudioFile($sessionId, $pdo, $surveyId, $code, $event)`: Selects and marks an audio file as used for a specific session and survey.
 *
 * This script is triggered by specific actions within the LimeSurvey that require audio file management, such as initializing a survey
 * session or rendering survey questions that involve audio playback.
 *
 * Usage:
 * Include or require this script in parts of the LimeSurvey plugin where audio file selection and marking are needed.
 *
 * Example:
 * Typically called when a new survey session starts, or a survey page that involves audio content is rendered.
 *
 * @package LimeSurvey\Plugin
 * @category AudioManagement
 * @author Alejandro Gallón
 * @copyright 2023, SchimmerCreative
 * @license GNU General Public License version 2 or later
 * @link www.schimmercreative.com
 */





// Database credentials
$dbHost = '';
$dbName = '';
$dbUser = '';
$dbPass = '';





// Include the updated counterFolder.php to use its functions
require_once('counterFolder.php');


// Initialize surveyId variable
$surveyId = null;

    // Check if $event is set and has 'surveyId'
    if (isset($event) && $event->get('surveyId')) {

        $surveyId = $event->get('surveyId');

                //Yii::log("Survey ID in audio_file_selection obtained from event: " . $surveyId, 'info');
    } else {

                //Yii::log("Event not set in audio_file_selection or survey ID not available in event.", 'warning');
                Yii::log("waiting to get Event and Survey ID for an allowed question type in audio_file_selection.", 'info');
    }




// Fetch the session ID from the session variable set in AudioPlayerRandomizer.php
$sessionId = $_SESSION['PLAY_sessionId'] ?? null;

    // Check if the session ID is available
    if ($sessionId === null) {

                Yii::log("No valid session ID available. Skipping audio file selection in audio_file_selection.", 'warning');

        return;
    }   
	

	

	
	





/**
 * Converts an absolute file system path to a relative URL for use within web pages. This function is essential
 * for ensuring that audio files stored on the server can be accessed through web interfaces in LimeSurvey.
 *
 * The function checks if the provided file path starts with the expected directory base path for the survey's
 * audio files. If it does, the path is converted to a relative URL by replacing the local system path with
 * a web-accessible path. This is critical for the functionality of web-based audio players that rely on
 * relative URLs to fetch audio content.
 *
 * If the survey ID is not provided or the file path does not match the expected structure, the function logs
 * an error and returns null to prevent improper URL usage.
 *
 * Usage:
 * This function is called whenever an audio file needs to be embedded in a survey page and requires a URL that
 * can be accessed by the client's browser.
 *
 * Example:
 * A server-side file path like "C:/xampp/htdocs/limesurvey5/upload/surveys/12345/files/audio.mp3" would be
 * converted to "/limesurvey5/upload/surveys/12345/files/audio.mp3", making it accessible via a web browser.
 *
 * @param string $filePath The absolute path to the file that needs to be converted to a relative URL.
 * @param int $surveyId The survey ID to ensure the path conversion is specific to the survey's directory structure.
 * @return string|null Returns the relative URL if conversion is successful, otherwise returns null if the path does not
 *                     conform to the expected format or if critical parameters are missing.
 */
function convertToRelativeURL($filePath, $surveyId) {


                Yii::log('convertToRelativeURL triggered', 'info');

                Yii::log('Attempting in audio_file_selection to convert filePath in convertToRelativeURL: ' . $filePath, 'info');
        
        // Handle cases where surveyId might be null
        if (!$surveyId) {

                 Yii::log("Survey ID in in audio_file_selection not available in convertToRelativeURL", 'error');

            return null;
        }

        
        
        $baseDir = "/[path_to_web_directory]/audioSurvey/upload/surveys/{$surveyId}/files/"; 
        // audioSurvey is an example of the directory where LimeSurvey resides, the name you used for your LimeSurvey installation
	
	

        if (strpos($filePath, $baseDir) === 0) {

            $relativeURL = str_replace($baseDir, "/audioSurvey/upload/surveys/{$surveyId}/files/", $filePath); //change /audioSurvey/ to your LimeSurvey name installation
            
                    Yii::log('Converted in in audio_file_selection to relative URL in convertToRelativeURL: ' . $relativeURL, 'info');

            return $relativeURL;

        } else {

                    Yii::log('Path in in audio_file_selectiondoes not match the expected structure in convertToRelativeURL', 'error');

            return null;
        }
}








// Since 'surveyId' is set, now check for 'code'
if (isset($event) && $event->get('code')) {

    $code = $event->get('code');

            Yii::log("Code in audio_file_selection for the selectAndMarkAudioFile function obtained from event: " . $code, 'info');

} else {

            //Yii::log("Code not available in event in audio_file_selection.", 'warning');
	        Yii::log("waiting for an allowed question type to get Code in event in audio_file_selection.", 'info');

} 






/**
 * Selects an audio file from a specified survey subfolder based on a given code and marks it as used in the session.
 * This function is central to managing audio file distribution across different survey sessions, ensuring that each
 * session utilizes a unique set of audio files without repetition.
 *
 * The function starts by ensuring that the session tracking array for selected audio files is initialized. It then
 * validates the provided code to extract an AudioNumberValue and a question type. Using these values, the function
 * determines the current subfolder from which to select the audio file, checks the availability of files, and selects
 * one that matches the AudioNumberValue.
 *
 * Once a file is selected, it is marked as used by adding it to the session's tracking array. The file path is then
 * converted to a relative URL for web use. Additionally, the function records the usage of the file in a database to
 * prevent its future selection within the same session, enhancing the diversity of audio content presented to users.
 *
 * Usage:
 * This function is typically called when initializing audio components for survey questions, especially when these
 * questions are to be presented with unique audio stimuli.
 *
 * Example:
 * A survey may need to present different audio files randomly but without repetition to each participant. This function
 * facilitates such requirements by managing which files have been used and selecting accordingly.
 *
 * @param string $sessionId The session identifier used to track and manage the selection of audio files.
 * @param PDO $pdo The PDO database connection object used for recording the usage of audio files.
 * @param int $surveyId The identifier of the survey for which audio files are being managed.
 * @param string $code A coded string that typically contains identifiers related to the audio files to be selected.
 * @param Event $event The LimeSurvey event object that may contain additional context or parameters needed during the selection.
 * @return string|null Returns the relative URL of the selected audio file if successful; otherwise, returns null if no suitable
 *                     file could be selected or if an error occurred during the process.
 * @throws Exception Errors are logged and managed within the function, particularly those related to file path conversions
 *                   and database operations.
 */
function selectAndMarkAudioFile($sessionId, $pdo, $surveyId, $code,$event) {



                        Yii::log('selectAndMarkAudioFile triggered', 'info');

        // Ensure session variable for tracking selected audio files is initialized
        if (!isset($_SESSION['selectedAudios'])) {

            $_SESSION['selectedAudios'] = [];

        }
	
	

    
        // Validate the code format and extract the AudioNumberValue
        if (preg_match('/^audq(\d{2})([kxfsml])$/', $code, $matches)) { //lower cased 

            $audioNumberValue = $matches[1]; // This is the 'AudioNumberValue' e.g., 01, 02

                    //Yii::log("Looking for AudioNumberValue: {$audioNumberValue} from code: {$code}", 'info');

            $questionType = $matches[2]; // This is the question type e.g., K, X, F, S, M, L

                    //Yii::log("Code parsed with AudioNumberValue: {$audioNumberValue} and QuestionType: {$questionType}", 'info');

        } else {

                     Yii::log("Invalid code format: {$code}. Unable to extract AudioNumberValue and QuestionType.", 'error');

            return null;
        }

    

    

    // Proceed directly to use the previously determined current subfolder
    $currentSubfolder = getCurrentSubfolder($surveyId, $sessionId);
	
	
        // Validate subfolder format
        if (!preg_match('/^\d{2}$/', $currentSubfolder)) {

                    $errorMessage = "Subfolder format is incorrect: {$currentSubfolder}. Subfolder must be two digits (e.g., '01', '02', ..., '99').";
                    Yii::log($errorMessage, 'error');

            $helpText = "<div style='color: red;'>{$errorMessage}</div>";
            $currentHelp = $event->get('help');
            $event->set('help', $currentHelp . "\n\n" . $helpText);

            return null;
        }                       
	
	

    // Get the path to audio files within the current subfolder
    $audioFilesPath = getPoolPath($surveyId) . "/{$currentSubfolder}";

                    //Yii::log("Scanning {$audioFilesPath} for audio files matching AudioNumberValue: {$audioNumberValue}", 'info');

    $audioFiles = glob("{$audioFilesPath}/*.mp3");
 

        if (empty($audioFiles)) {

                    Yii::log("No audio files found in subfolder: {$currentSubfolder} for session ID {$sessionId}.", 'error');

                    $errorMessage = "No audio files found in subfolder: {$currentSubfolder} for session ID {$sessionId}. Please check the subfolder name and audio filenames.";
                    Yii::log($errorMessage, 'error');


            // Append the error message to the current help text of the event
            $helpText = "<div style='color: red;'>{$errorMessage}</div>";
            $currentHelp = $event->get('help');
            $event->set('help', $currentHelp . "\n\n" . $helpText);

            return null;
        }

    // Filter out already selected audio files
    $unselectedFiles = array_diff($audioFiles, $_SESSION['selectedAudios']);

        if (empty($unselectedFiles)) {

            // Reset selection if all files have been used
            $_SESSION['selectedAudios'] = [];
            $unselectedFiles = $audioFiles;

        }

    // Convert the array of unselected files to a string and log it
    $unselectedFilesList = implode(', ', $unselectedFiles);
    
                    Yii::log("Unselected files: " . $unselectedFilesList, 'info');


    // Find the specific audio file that matches the AudioNumberValue
    $selectedFile = null;

    foreach ($unselectedFiles as $file) {

        $fileBasename = basename($file);

                Yii::log("Evaluating file: {$fileBasename} against AudioNumberValue: {$audioNumberValue}.mp3", 'info');

            if ($fileBasename === "{$audioNumberValue}.mp3") {

                $selectedFile = $file;

                break;
            }
    }

		
		
		
		// If no file was selected from unselected files, check all files again
        if (!$selectedFile) {

                        Yii::log("Rechecking all files as no match was found in unselected files.", 'info');

            foreach ($audioFiles as $file) {

                $fileBasename = basename($file);

                        Yii::log("Evaluating file: {$fileBasename} for recheck against AudioNumberValue: {$audioNumberValue}.mp3", 'info');

                if ($fileBasename === "{$audioNumberValue}.mp3") {

                        Yii::log("Match found on recheck: {$fileBasename}", 'info');

                    $selectedFile = $file;

                    break;
                }
            }
        }
		
		

        if (!$selectedFile) {
            
                    Yii::log("No audio file matching AudioNumberValue: {$audioNumberValue} found.", 'error');


            $errorMessage = "No audio file matching AudioNumberValue: {$audioNumberValue} found. Check the subfolder and filenames.";

                    Yii::log($errorMessage, 'error');

            // Append the error message to the current help text of the event
            $helpText = "<div style='color: red;'>{$errorMessage}</div>";
            $currentHelp = $event->get('help');
            $event->set('help', $currentHelp . "\n\n" . $helpText);


            return null;
        }


    // Mark the selected file as used
    $_SESSION['selectedAudios'][] = $selectedFile;

    $relativeAudioURL = convertToRelativeURL($selectedFile, $surveyId);

        if (!$relativeAudioURL) {

                    Yii::log("Could not convert file path to relative URL: {$selectedFile}", 'error');


            return null;
        } 

    try {
        $insertStmt = $pdo->prepare("INSERT INTO used_audio_files (session_id, audio_url) VALUES (:session_id, :audio_url)");
        $insertStmt->bindParam(':session_id', $sessionId);
        $insertStmt->bindParam(':audio_url', $selectedFile);
        $insertStmt->execute();

        $_SESSION['audioUrl'] = $relativeAudioURL;

                    Yii::log("Selected and marked audio file: {$selectedFile} for session: {$sessionId} with code: {$code}", 'info');

        return $relativeAudioURL;

    } catch (PDOException $e) {

                    Yii::log("Error in selectAndMarkAudioFile: " . $e->getMessage(), 'error');

        return null;
    }
}








?>


