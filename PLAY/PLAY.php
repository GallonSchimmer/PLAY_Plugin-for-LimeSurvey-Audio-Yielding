<?php

// Include the audio file selection script at the top of the file
include_once("audio_file_selection.php");




/**
 * The PLAY class extends the LimeSurvey PluginBase to provide audio functionalities within LimeSurvey. 
 * It enables embedding of audio files in survey questions and handles events triggered throughout 
 * the survey lifecycle, such as before rendering questions and completing the survey. This class 
 * subscribes to several events to inject audio handling logic where necessary, ensuring audio files 
 * are appropriately managed and played as per survey configurations.
 *
 * The class initializes with database connections to manage audio files associated with surveys and 
 * logs important information for debugging purposes. It ensures audio files are unique to each session 
 * and handles the complexity of audio file selection based on predefined codes associated with survey questions.
 *
 * Usage:
 * - The class should be instantiated by the LimeSurvey plugin manager.
 * - It automatically hooks into the LimeSurvey event system.
 *
 * Example:
 * Assuming PLAY is correctly installed and activated as a LimeSurvey plugin, it works automatically 
 * based on its subscription to LimeSurvey's internal events.
 *
 * @package LimeSurvey\Plugin
 * @category Audio Handling
 * @author Alejandro Gallón
 * @copyright 2023, SchimmerCreative
 * @license GNU General Public License version 5 or later
 * @link www.schimmercreative.com
 * @see \LimeSurvey\PluginManager\PluginBase
 */
class PLAY extends \LimeSurvey\PluginManager\PluginBase { 


    protected $storage = 'DbStorage';  





    /**
 * Initializes the PLAY plugin by subscribing to various LimeSurvey events necessary for audio handling.
 * It sets up the plugin to interact with different stages of the survey lifecycle, such as rendering questions,
 * updating survey settings, processing before a survey page is rendered, and after survey completion.
 * 
 * This method also establishes a connection to the database, which is used to manage the audio files associated
 * with the surveys. It logs the status of the database connection to help with troubleshooting and ensure the
 * plugin is ready to handle audio functionalities correctly.
 * 
 * Events Subscribed:
 * - 'beforeQuestionRender': To handle audio embedding before a question is rendered.
 * - 'newSurveySettings': To manage plugin settings within the survey's administrative interface.
 * - 'beforeSurveyPage': To execute logic before a survey page is loaded, ensuring audio files are ready.
 * - 'afterSurveyComplete': To handle any cleanup or data processing needed after the survey is completed.
 *
 * The database connection uses PDO and catches any exceptions thrown during the connection attempt, logging
 * the outcome accordingly.
 *
 * @throws PDOException If the connection to the database fails, an exception is logged and handled within the method.
 */
     public function init() { 

        $this->subscribe('beforeQuestionRender'); 
        $this->subscribe('newSurveySettings'); 
        $this->subscribe('beforeSurveyPage'); 
		$this->subscribe('afterSurveyComplete');  // Subscribe to the AfterSurveyComplete event for calling updateSubfolderCounter function
		
                    Yii::log('PLAY Plugin Initialized in init()', 'info'); 
		
            try {

                
                $pdo = new PDO("mysql:host=[DB_HOST];dbname=[DB_NAME]", "[DB_USER]", "[DB_PASSWORD]");


                        Yii::log("Database connection successfully established", 'info');

            } catch (PDOException $e) {

                        Yii::log("Database connection failed: " . $e->getMessage(), 'error');
            }
		
    } 
	


	




















    /**
 * Handles operations before a survey question is rendered by embedding audio content, validating codes, and managing session data.
 * This method is triggered for each question rendering event in a survey where the PLAY plugin is active.
 * It performs several checks and operations to ensure the appropriate audio content is embedded based on the question's requirements and session data.
 *
 * The method first checks for the existence of the event object and retrieves the necessary data like question ID and survey ID.
 * It then verifies if the question type is allowed to have embedded audio based on predefined allowed types. If a question meets the criteria,
 * the method proceeds to check and validate a code associated with the question, which helps in selecting the appropriate audio file.
 * If the code is valid and an audio file URL is set in the session or newly obtained, it embeds an audio player into the question content.
 *
 * Finally, the method also handles the registration of client-side JavaScript necessary for audio playback and interaction within the survey.
 *
 * Usage:
 * This method is automatically invoked by LimeSurvey during the question rendering phase if the PLAY plugin is active and configured.
 *
 * @param none Direct parameters are not used, but the method operates using the global event context provided by LimeSurvey.
 * @return void The method does not return a value but directly modifies the survey's question rendering behavior by embedding audio and scripts.
 * @throws none No exceptions are thrown directly from this method, but it logs various levels of information and warnings based on execution conditions.
 */
    public function beforeQuestionRender() {

        $event = $this->getEvent();	

            if (!$event) {

                    Yii::log('No event object available in beforeQuestionRender', 'error');

                return;
            }
                    Yii::log('beforeQuestionRender triggered', 'info');


	    //try to place it before the call for embedAudioQuestion function
	    $questionId = $event->get('qid');

            if (!$questionId) {

                    Yii::log('No question ID found in event in beforeQuestionRender', 'warning');

                return;
            }
                    //Yii::log('Question ID in beforeQuestionRender: ' . $questionId, 'info');
	
        $surveyId = $event->get('surveyId');
	
	    // Get the current Survey Response ID
        $sessionId = $_SESSION['survey_' . $surveyId]['srid'] ?? 'default';

                    //Yii::log('Session variable, srid or Survey Response ID in beforeQuestionRender PLAY_sessionId set to: ' . $sessionId, 'info');
        
                    // Debugging log
                    //Yii::log("beforeQuestionRender: Question ID = $questionId, Survey ID = $surveyId, Session ID or Survey Response ID = $sessionId", 'info');


        // Try to fetch the code from the event object or from the session if not present in the event
        $code = $event->get('code') ?? $_SESSION['code'] ?? null;

            if ($code === null) {

                    Yii::log('No code found in event or session for question', 'warning');

                return;
            }

        // Inspect the code by converting it to hexadecimal
        $hexCode = bin2hex($code);

                    //Yii::log("Code in hexadecimal format: {$hexCode}", 'info');

                    // Log the original code for comparison
                    Yii::log("Original code: {$code}", 'info');

	
	    // Fetch question type and check if it's allowed
        $questionId = $event->get('qid');
        $questionType = Question::model()->findByPk($questionId)->type;
        $allowedTypes = ['K', 'X', 'F', 'S', 'M', 'L']; // tested "QBAOPSKT",

            if (!in_array($questionType, $allowedTypes)) {

                    Yii::log("Plugin not applied. in beforeQuestionRender Question type {$questionType} is not allowed.", 'info');

            return;
            }
	
	                Yii::log("Checking question type in beforeQuestionRender for Question ID: $questionId, Type: $questionType", 'info');

            // Fetch the code and if not correct stop the plugin from running embedding and javascript manipulation
            if (!$this->validateAndSetAudio($event, $code)) {

                    // If the validation fails, log an error and return without embedding the audio player
                    Yii::log("Plugin disabled for this question due to invalid code format: {$code}", 'error');

                echo "<script>yi.log('Error: Plugin disabled for question ID {$questionId} due to invalid code format.');</script>";

                return;
            }
	
            // Send information to JavaScript
            echo "<script type='text/javascript'>
                    var questionTypeInfo = questionTypeInfo || {};
                    questionTypeInfo['{$questionId}'] = " . (in_array($questionType, $allowedTypes) ? 'true' : 'false') . ";
                </script>";

            if (!in_array($questionType, $allowedTypes)) {

                    //Yii::log("Plugin not applied.  Question type {$questionType} is not allowed in beforeQuestionRender.", 'info');

                return;
            }
	
	
	
	
            // Embed audio player now with a question id to avoid placing audio players to not allowed questions 
            if (isset($_SESSION['audioUrl'])) {

                $this->embedAudioInQuestion($event, $_SESSION['audioUrl'], $questionId);

            } else {

                    //Yii::log('No audio URL found in beforeQuestionRender in session for question', 'warning');
                
                // Try to get a new audio URL
                
                $pdo = new PDO("mysql:host=[DB_HOST];dbname=[DB_NAME]", "[DB_USER]", "[DB_PASSWORD]");

                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                
                
                // Since 'surveyId' is set, now check for 'code'
                if (isset($event) && $event->get('code')) {

                    $code = $event->get('code');
                    
                        if ($code) {
                            // $this->validateAndSetAudio($event, $code);
                            // validation is being done later
                            
                        }

                        Yii::log("Code in embedAudioInQuestion obtained from event: " . $code, 'info');
                } else {
                        Yii::log("Code not available in event in embedAudioInQuestion.", 'warning');
                
                }  
                
                // Attempt to select and mark a new audio file
                $relativeAudioURL = selectAndMarkAudioFile($sessionId, $pdo, $surveyId, $code, $event);

                    if ($relativeAudioURL !== null) {

                        // If a new audio URL was obtained, set it in the session and embed the audio in the question
                        $_SESSION['audioUrl'] = $relativeAudioURL;
                        $this->embedAudioInQuestion($event, $relativeAudioURL, $questionId);

                            //Yii::log('New audio URL obtained and embedded in beforeQuestionRender: ' . $relativeAudioURL, 'info');
                    } else {
                            Yii::log('No audio file available for selection.', 'error');
                    }
            }
	
	
	
	    $surveyId = $event->get('surveyId');
	
	
	    // Get the current Survey Response ID
        $sessionId = $_SESSION['survey_' . $surveyId]['srid'] ?? 'default';

	                //Yii::log('Session variable, srid or Survey Response ID in beforeQuestionRender PLAY_sessionId set to: ' . $sessionId, 'info');
	
	    // If the switch is not enabled, proceed with script registration
        $this->registerClientScripts($surveyId);

                    Yii::log('Proceeding with script registration in beforeQuestionRender', 'info');
	
	    // Check if you need to inject the script for this question
        $questionId = $event->get('qid');
        $questionType = Question::model()->findByPk($questionId)->type;
        $allowedTypes = ['K', 'X', 'F', 'S', 'M', 'L']; // tested "QBAOPSKT",

            if (in_array($questionType, $allowedTypes)) {
                
                // Define the web-accessible path to your JavaScript file, please use this absolute path and change the limesurvey5 to the one you used
                
                $jsFilePath = '/[path_to_limesurvey]/upload/plugins/PLAY/audioPlayer.js';
                
                // Append the script tag to the question HTML
                $currentText = $event->get('text');
                $scriptTag = "<script src='{$jsFilePath}'></script>";
                $event->set('text', $currentText . $scriptTag);

                        Yii::log("script tag injected in beforeQuestionRender for question ID: $questionId", 'info');
            }
	
	
    
    
        /* 
            
        // Log all session variables, be careful..it will log a great amount of information. do not log if not needed. 
        
        //Yii::log('Session variables:', 'info');
        foreach ($_SESSION as $key => $value) {
            Yii::log("$key: " . print_r($value, true), 'info');
        }
        */
	
    }











    /**
 * Validates the provided code against a predefined pattern and sets appropriate audio settings based on the
 * extracted values. This method is crucial for ensuring that audio files are correctly associated with survey
 * questions based on a unique code format that includes an AudioNumberValue and a QuestionType.
 *
 * The method uses regular expressions to validate the code format and extract the necessary components. If the
 * code does not match the expected format, an error message is generated and appended to the survey's help text
 * to inform the user or administrator of the issue.
 *
 * This function logs the result of the regex match and, if valid, logs the extracted values for further processing.
 * If the validation fails, it logs an error and modifies the survey's help text to display the error message.
 *
 * Example usage:
 * Typically called during the survey initialization or before a question is rendered to ensure that the audio
 * settings are valid and ready to be applied.
 *
 * @param Event $event The event object provided by LimeSurvey, used for accessing and modifying survey properties.
 * @param string $code The code to be validated, which should include identifiers for the audio file and question type.
 * @return bool Returns true if the code format is valid and matches the expected pattern, otherwise returns false.
 * @throws none This method does not throw exceptions but handles errors internally by logging them and updating the event's help text.
 */
    private function validateAndSetAudio($event, $code) {

                    Yii::log('validateAndSetAudio triggered', 'info');
    
        $matches = [];
        $pattern = '/^audq(\d{2})([kxfsml])$/';
        $result = preg_match($pattern, $code, $matches);

                    Yii::log("Regex match result: " . ($result ? "true" : "false"), 'info');

        // Initialize helpText with an empty string
        $helpText = '';

            if (!$result) {

                    // Log the error and prepare the error message
                    $errorMessage = "Invalid code format: {$code}. Unable to extract AudioNumberValue and QuestionType.";

                        Yii::log($errorMessage, 'error');

                // Here we directly use the error message as the help text to be added
                $helpText = "<div style='color: red;'>{$errorMessage}</div>";

                // Append the error message to the current help text of the event
                $currentHelp = $event->get('help');
                $event->set('help', $currentHelp . "\n\n" . $helpText);

                return false;
            }

        // Extracted values from the regex
        $audioNumberValue = $matches[1];
        $questionType = $matches[2];

                    Yii::log("Valid code with AudioNumberValue: {$audioNumberValue} and QuestionType: {$questionType}", 'info');

        // Continue with function logic as necessary
        return true;
    }









    



















    /**
 * Embeds an audio player within a survey question based on the specified audio URL and question ID. This method
 * dynamically integrates audio content into LimeSurvey questions, ensuring that the audio is relevant to the
 * question context and adheres to the session-specific configurations.
 *
 * The method retrieves necessary details from the event object, establishes a database connection to manage audio
 * file selections, and uses the session and survey IDs to determine the appropriate audio file. If a valid audio
 * file URL is identified and processed, it is embedded directly into the survey's question text.
 *
 * Audio file selection and validation are handled through an external function, `selectAndMarkAudioFile`, which
 * is responsible for choosing an appropriate file based on current session data and marking it as used to avoid
 * repetitions.
 *
 * If any issues are encountered in fetching or embedding the audio file, the method logs these errors and aborts
 * the embedding process to maintain survey integrity.
 *
 * Example:
 * This method is called within event-driven hooks that manage question rendering, ensuring that each question
 * capable of supporting audio content has the appropriate player embedded before being displayed to the respondent.
 *
 * @param Event $event The LimeSurvey event object providing context and metadata about the current survey processing state.
 * @param string $audioURL The URL of the audio file intended to be embedded. This URL should be a relative path accessible by the client's browser.
 * @param int $questionId The unique identifier of the question within the survey where the audio player will be embedded.
 * @return void The method does not return a value but alters the survey content by embedding an audio player.
 * @throws Exception Errors during database operations or file handling are logged as warnings or errors in the system log.
 */
    private function embedAudioInQuestion($event, $audioURL, $questionId) {

                Yii::log('Embedding audio in the question in embedAudioInQuestion', 'info');  
	
	    $surveyId = $event->get('surveyId');
	
	            //Yii::log('Survey ID in embedAudioInQuestion: ' . $surveyId, 'info');
	
	    // Get the current Survey Response ID
        $sessionId = $_SESSION['PLAY_sessionId'] ?? 'default';	

                //Yii::log('Session ID in embedAudioInQuestion: ' . $sessionId, 'info');	
	            //Yii::log('Session variable, srid or Survey Response ID in embedAudioInQuestion PLAY_sessionId set to: ' . $sessionId, 'info');
	
	
	
	    

        // Create a new PDO instance for calling the selectAndMarkAudioFile function 
        
        $pdo = new PDO("mysql:host=[DB_HOST];dbname=[DB_NAME]", "[DB_USER]", "[DB_PASSWORD]");
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        
            // Since 'surveyId' is set, now check for 'code'
            if (isset($event) && $event->get('code')) {

                $code = $event->get('code');

                        Yii::log("Code in embedAudioInQuestion obtained from event: " . $code, 'info');
            } else {

                        Yii::log("Code not available in event in embedAudioInQuestion.", 'warning');
            }    

        // Select and mark the audio file
        $relativeAudioURL = selectAndMarkAudioFile($sessionId, $pdo, $surveyId, $code, $event);

            if ($relativeAudioURL === null) {

                        Yii::log('No audio file selected in embedAudioInQuestion', 'error');

                        Yii::log('Could not convert the file path to a relative URL in embedAudioInQuestion: ' . $audioURL, 'error'); 

                return;
            }
		 
	                Yii::log("file path to a web-accessible relative path in embedAudioInQuestion: {$relativeAudioURL}", 'info'); 

    
	    //now the audio players are correctly dependant of the question id that in the beforequestionrender function connects it with the allowed question types
	
	    // Define the audio player HTML with the relative URL and include qid
	    $audioPlayerHtml = "<audio class='audio-player' data-question-id='{$questionId}' controls controlsList='nodownload'><source src='{$relativeAudioURL}' type='audio/mpeg'></audio>"; 

	    //the question object is not accessible, then we access event attributes to embed the audioplayer

        // Attempt to get the question object from the event 
        $question = $event->get('question'); 

            if ($question !== null) { 

                $question->text .= $audioPlayerHtml; 

                        //Yii::log("Audio player embedded in the question successfully with audio URL with embedAudioInQuestion: {$relativeAudioURL}", 'info'); 

            } else { 

                        //Yii::log('Question object not found in event in embedAudioInQuestion', 'warning'); 

                        Yii::log('Question object Attributes can be logged through $eventDetails in embedAudioInQuestion', 'info'); 


                        // Logging more details about the event; here you can log the Question or Event Attributes, help, text, code, html ids, etc
                        $eventDetails = print_r($event, true); 

                        //Yii::log("Event details in embedAudioInQuestion: {$eventDetails}", 'info'); 

                // Retrieve the current question text from the event 
                $currentText = $event->get('text'); 

                        Yii::log("Current question text retrieved in embedAudioInQuestion: " . substr($currentText, 0, 100) . '...', 'info'); 











                // Append the audio player HTML to the current text. when not testing do not append the $relativeAudioURL
                //$newText = $currentText . $audioPlayerHtml . $relativeAudioURL; 
                $newText = $currentText . $audioPlayerHtml; 

                // Set the modified text back into the event 
                $event->set('text', $newText); 

                        // Yii::log("Audio player HTML set into the question text successfully with embedAudioInQuestion.", 'info'); 
                
            } 
	
	
	
	
    } 





























    /**
 * Handles operations that need to be executed before a survey page is displayed to the participant.
 * This method is triggered by LimeSurvey's 'beforeSurveyPage' event and is responsible for setting up
 * session-specific data and including necessary scripts that monitor or manipulate audio files for the survey.
 *
 * The method retrieves the survey and session identifiers, logs relevant information for debugging, and
 * ensures that the necessary session variables are initialized and set. It also includes the 'audioFileMonitor.php'
 * script, which handles the dynamic management of audio files based on session activities.
 *
 * Furthermore, it checks if the current user has the necessary permissions to update survey content,
 * ensuring that only authorized modifications are made to the survey's structure or content.
 *
 * If any issues are encountered, such as missing survey permissions or inability to find the survey model,
 * the method logs the appropriate errors and terminates execution to prevent improper page rendering or data handling.
 *
 * Example:
 * This method is part of the event-driven architecture of LimeSurvey plugins and is not directly called but
 * executed in response to the survey's lifecycle events.
 *
 * @param none This method does not take parameters directly but operates on the event context provided by LimeSurvey.
 * @return void This method does not return a value but directly affects the survey session and logs operations.
 * @throws none Direct exceptions are not thrown by this method, but it logs errors related to permissions and data retrieval.
 */
    public function beforeSurveyPage() {
    
                    Yii::log('beforeSurveyPage triggered', 'info');
	
	    // Retrieve the event and survey ID
        $event = $this->getEvent();
        $surveyId = $event->get('surveyId');

                    //Yii::log('Survey ID in beforeSurveyPage: ' . $surveyId, 'info');
		
		//get the Survey Response ID for Monitoring the directory with audios to updated, called then by audioFileMonitor
        $sessionId = $this->getCurrentSessionId($surveyId);

                    //Yii::log('Session ID: ' . $sessionId, 'info');

        $_SESSION['PLAY_sessionId'] = $sessionId;
		
		            Yii::log('Session variable in beforeSurveyPage PLAY_sessionId or Survey Response ID from getCurrentSessionId set to: ' . $sessionId, 'info');
		            //Yii::log('Survey ID in beforeSurveyPage: ' . $surveyId, 'info');
		
		
	
	    // Include the audio file monitoring script
        include_once("audioFileMonitor.php");
	   	
        // Retrieve the event and survey ID
        $event = $this->getEvent();
        $surveyId = $event->get('surveyId');

                    //Yii::log('Survey ID: ' . $surveyId, 'info');

            // Check permissions for updating survey content
            if (!Permission::model()->hasSurveyPermission($surveyId, 'surveycontent', 'update')) {
                return;
            }

                // Find the survey model by ID
                $survey = Survey::model()->findByPk($surveyId);

                    if (!$survey) {

                                Yii::log('Survey not found in beforeSurveyPage', 'error');

                        return;
                    }

    }




	/**
 * Retrieves the current session ID for a given survey from the LimeSurvey session context. This method is
 * essential for associating survey-specific data with a unique session, ensuring that any modifications
 * or data logging performed by the plugin are accurately attributed to the active survey session.
 *
 * The session ID is used primarily to manage and track interactions within a specific instance of a survey,
 * particularly for functions that depend on the session's uniqueness such as audio file handling and user
 * interactions tracking.
 *
 * If no session ID is found in the current context (i.e., the survey session is not yet initialized), a
 * default value of 'default' is returned. This behavior ensures that the function gracefully handles cases
 * where the session data might not yet be set.
 *
 * Example:
 * This method can be called whenever a session-specific operation needs to be performed, such as logging
 * session-specific events or handling audio files that are tied to a particular session of the survey.
 *
 * @param int $surveyId The identifier for the survey whose session ID is being retrieved. This is necessary
 *                      to locate the correct session information within a potentially complex session structure.
 * @return string Returns the session ID associated with the survey, or 'default' if no session is currently active.
 * @throws None This method does not throw exceptions but will return a default value in absence of specific session data.
 */
	private function getCurrentSessionId($surveyId) {

                Yii::log('getCurrentSessionId triggered', 'info');
    
        // Fetch the current session ID from LimeSurvey's context
        $sessionId = $_SESSION['survey_' . $surveyId]['srid'] ?? 'default';

                // Log the fetched session ID using Yii's logging system
                Yii::log("Current session ID or Survey Response ID for survey from getCurrentSessionId {$surveyId}: {$sessionId}", 'info', 'application');

        return $sessionId;
    }	




    /**
 * Registers client-side JavaScript scripts for use in LimeSurvey. This method is designed to ensure that
 * the necessary JavaScript is available on the client side to handle audio playback and other interactions
 * dictated by the PLAY plugin.
 *
 * The JavaScript file is located within the plugin's upload directory and is injected into the survey pages
 * at the end of the body content, ensuring that it does not interfere with the loading of the page content
 * and is executed after all DOM elements are available.
 *
 * Example:
 * This method is typically called during the initialization of the survey page to ensure the JavaScript
 * is ready before any survey interaction occurs.
 *
 * @param int $surveyId The ID of the survey for which the scripts are being registered. This is used to
 *                      log the action and could potentially be used to define path or script variations
 *                      based on the survey.
 * @throws Exception If there is an error in generating the base URL or registering the script, it might throw
 *                   an exception depending on the underlying framework's implementation.
 * @note This function depends on the LimeSurvey application context to access methods like `getBaseUrl()` and
 *       `registerScriptFile()`.
 */
    private function registerClientScripts($surveyId) {  

                Yii::log('registerClientScripts triggered', 'info');
	
            // Yii::log("Registering client scripts for Survey ID in registerClientScripts: $surveyId", 'info');
		
        /// Define the web-accessible path to the JavaScript file
        $jsFilePath = App()->getBaseUrl(true) . '/upload/plugins/PLAY/audioPlayer.js';

        // Register the JavaScript file
        App()->getClientScript()->registerScriptFile($jsFilePath, CClientScript::POS_END);

            Yii::log('JavaScript file registered in registerClientScripts: ' . $jsFilePath, 'info');
    }




    
	/**
 * Handles tasks that need to be executed after a survey has been completed. This method is triggered by the
 * 'afterSurveyComplete' LimeSurvey event. It primarily focuses on logging important session data and updating
 * the subfolder usage counter, which assists in managing audio file distribution across survey sessions.
 *
 * The method retrieves the survey ID from the event, logs it, and checks for its existence. If the survey ID
 * is not found, the method logs an error and exits to prevent further processing errors.
 *
 * It includes external PHP files that handle subfolder counting logic (`counterFolder.php`), retrieves the
 * current session ID, and updates a session variable specifically used for tracking audio-related activities.
 *
 * Finally, it calls the `updateSubfolderCounter` function to increment the count of subfolders used for the
 * survey, ensuring that audio file usage is balanced and tracked accurately across multiple survey instances.
 *
 * Example:
 * The method is not directly called but is executed as part of the LimeSurvey plugin event system upon the
 * completion of a survey.
 *
 * @throws Exception If the survey ID is not retrieved, an exception is logged and the method exits.
 * @note This method assumes that session management and the counter updating functions are properly implemented
 * in 'counterFolder.php'.
 */
	public function afterSurveyComplete() {

                    Yii::log('afterSurveyComplete triggered', 'info');
        
        $event = $this->getEvent();
        $surveyId = $event->get('surveyId');
		
            // Log retrieval of survey ID from the event
            if ($surveyId) {

                    Yii::log("Retrieved Survey ID: $surveyId from afterSurveyComplete event.", 'info');

            } else {

                    Yii::log("Failed to retrieve Survey ID from afterSurveyComplete event.", 'error');

            return; // Exit if no survey ID is found to avoid further errors
            }
	
	
	    include_once("counterFolder.php");
	
	
	     //for the updateSubfolderCounter, need to call the sessionId
        $sessionId = $this->getCurrentSessionId($surveyId);

                    //Yii::log('Session ID: ' . $sessionId, 'info');

        $_SESSION['PLAY_sessionId'] = $sessionId;
		
		            Yii::log('Session variable in afterSurveyComplete PLAY_sessionId or Survey Response ID from getCurrentSessionId set to: ' . $sessionId, 'info');
	
        
		            Yii::log("Call in afterSurveyComplete from  to update subfolder counter for Survey ID: $surveyId and Session ID: $sessionId.", 'info');
        
        // Call the updateSubfolderCounter function here

        //$this->updateSubfolderCounter($surveyId, $sessionId);
		updateSubfolderCounter($surveyId, $sessionId);
		
		 // Call logGlobalVariables 
		//$this->logGlobalVariables();
		
    }







    /* uncomment to log errors on [help] parameter from question object, last question logs, not the actual question log
        // Add logs to help text
        $helpText = $this->getLogEntriesByLevel('error'); // Change 'info' to 'warning' or 'error' as needed
        //$helpText = $this->getLogEntriesByLevel('warning'); // Change 'info' to 'warning' or 'error' as needed
        //$helpText = $this->getLogEntriesByLevel('error'); // Change 'info' to 'warning' or 'error' as needed
        $currentHelp = $event->get('help');
        $event->set('help', $currentHelp . "\n\n" . $helpText);
    */





    //to log Session Variables uncomment necessary logs in function and the $this->logGlobalVariables in AfterSurveyComplete Method
    public function logGlobalVariables() {


                    Yii::log('logGlobalVariables triggered', 'info');
        
        // Log $_SESSION variables
        //Yii::log("Session: " . print_r($_SESSION, true), 'info', 'application');

        // Log $_GET variables
        //Yii::log("GET: " . print_r($_GET, true), 'info', 'application');

        // Log $_POST variables
        //Yii::log("POST: " . print_r($_POST, true), 'info', 'application');

        // Log $_COOKIE variables
        //Yii::log("COOKIE: " . print_r($_COOKIE, true), 'info', 'application');

        // Log $_SERVER variables, selectively to avoid security risks
        //$serverKeysToLog = ['HTTP_HOST', 'SERVER_NAME', 'REQUEST_URI', 'REMOTE_ADDR'];  // Add more keys as needed
        //$serverInfoToLog = array_intersect_key($_SERVER, array_flip($serverKeysToLog));
        //Yii::log("SERVER: " . print_r($serverInfoToLog, true), 'info', 'application');
    }
	


} 