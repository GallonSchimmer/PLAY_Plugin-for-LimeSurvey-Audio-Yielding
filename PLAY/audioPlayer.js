window.addEventListener('load', function() { 


/**
 * This JavaScript module is responsible for managing the playback of audio files within LimeSurvey and coordinating
 * the display of survey questions and elements based on the audio playback status. It enhances user interaction by
 * ensuring that certain survey components are only visible after all required audio files have been played, thereby
 * maintaining the integrity of the survey's flow and data collection process.
 *
 * The script initializes by logging the successful load of the audio player script and sets up the infrastructure
 * to monitor audio completion, hide or show survey elements, and log various identifiers for debugging and tracking
 * purposes.
 *
 * Key Features:
 * - Monitors the playback status of audio files to control the visibility of related survey elements.
 * - Dynamically hides or shows subquestions, tables, and answers based on audio playback.
 * - Logs unique identifiers for subquestions, tables, and audio players to assist in debugging and survey management.
 * - Updates numeric input fields based on predefined criteria to ensure data consistency across survey responses.
 *
 * Usage:
 * The script is included in LimeSurvey templates that require synchronized audio playback with survey question visibility.
 * It ensures that respondents are only able to interact with survey elements after they have engaged with the audio content.
 *
 * Example:
 * In a psychological study, respondents might need to listen to a set of instructions or scenarios before they can
 * respond to related questions. This script ensures that the questions are only displayed after the audio has finished
 * playing, thereby adhering to the study's protocol.
 *
 * @author Alejandro Gallón
 * @copyright 2024, SchimmerCreative
 * @license GNU General Public License version 2 or later
 * @link www.schimmercreative.com
 * @fileoverview Manages audio file playback and coordinates the display of survey questions and elements in LimeSurvey.
 */


                    console.log('Audio Player script loaded'); 
	
	
	
	var questionTypeInfo = {}; // Global object to store question type info

	                console.log('Question Type Info:', questionTypeInfo); // Log the global question type info
	
	
	
	
	// Call the function to log subquestion IDs
    logSubQuestionIds();

    function logSubQuestionIds() {

                    console.log('Fetching subquestion IDs...');

        // Find all subquestion list items
        var subQuestions = document.querySelectorAll('.subquestion-list li.question-item');

            if (subQuestions.length === 0) {

                    console.log('No subquestions found.');

                return;
            }

        subQuestions.forEach(function(subQuestion, index) {

                // Log the ID of each subquestion
                console.log('Subquestion ' + (index + 1) + ' ID:', subQuestion.id);

        });
    }
	
	
	// New function to log table IDs
    logTableIds();								//use this also for answers for questions type O
	
    function logTableIds() {

                console.log('Fetching table IDs...');

        var tables = document.querySelectorAll('.ls-answers');

            if (tables.length === 0) {

                        console.log('No tables found.');

                return;
            }

        tables.forEach(function(table, index) {

                        console.log('Table ' + (index + 1) + ' ID:', table.id);

        });
    }
	
	
	// New function to log answers IDs
    logAnswerIds();
	
	function logAnswerIds() {

                        console.log('Fetching answer IDs...');

        var answers = document.querySelectorAll('.answer-items');

            if (answers.length === 0) {

                        console.log('No answers found.');

                return;
            }
        answers.forEach(function(answer, index) {

                        console.log('Answer ' + (index + 1) + ' ID:', answer.id);

        });
    }
	
	
function allAudiosCompleted(playStatus) { 

        return Object.values(playStatus).every(status => status); 

    }
	
// Inside your audio handling logic, after audios are processed
document.dispatchEvent(new CustomEvent('audioPlayerProcessed'));


// Initially hide the subquestion
hideSubQuestion();
hideTables();
	
//for question type O
hideAnswers();

// Find all audio players in the survey 
var audioPlayers = document.querySelectorAll('.audio-player'); 
var allAudiosPlayed = {}; // Object to track the play status of all audios 

audioPlayers.forEach(function(player, index) { 

        allAudiosPlayed[index] = false; // Initialize play status as false 

        player.onended = function() { 

                        console.log('Audio finished playing in player:', player.id); 

            allAudiosPlayed[index] = true; // Update play status 

                // Check if all audios have been played 
                if (allAudiosCompleted(allAudiosPlayed)) { 

                    showSubQuestion(); // Show the subquestion when all audios have been played
                    showTables(); // Show the tables when all audios have been played
                    showAnswers(); 								// for questions type O that have an Answer instead of a Subquestion

                    // When all audios have been played, save the counter
                    updateNumericQuestionWithCounter();
                } 
        }; 
}); 
     

function hideSubQuestion() {

    var subQuestion = document.querySelector('.subquestion-list li.question-item');

        if (subQuestion) {

            subQuestion.style.display = 'none';

                    console.log('Subquestion hidden:', subQuestion.id);

        }
}
	
function hideTables() {

        var tables = document.querySelectorAll('.subquestion-list ');

        tables.forEach(function(table) {

            table.style.display = 'none';

                    console.log('Table hidden:', table.id);
        });
}
	
	
//hide Answers also for question type O
function hideAnswers() {

        var answers = document.querySelectorAll('.answer-item ');

        answers.forEach(function(answer) {

            answer.style.display = 'none';

                    console.log('Answer hidden:', answer.id);
        });
}
	
//show Answers also for question type O
function showAnswers() {

                    console.log('All audios have been played. Showing answers.');

        var answers = document.querySelectorAll('.answer-item ');

        answers.forEach(function(answer) {
            
            answer.style.display = '';

                    console.log('Answer shown:', answer.id);

        });
}
	
	

function showSubQuestion() {

                    console.log('All audios have been played. Showing subquestion.');
        
		var subQuestion = document.querySelector('.subquestion-list li.question-item');

            if (subQuestion) {

                subQuestion.style.display = '';

                        console.log('Subquestion shown:', subQuestion.id);

            } else {

                        console.log('No subquestion found to show');
            }
}
	
function showTables() {

                    console.log('All audios have been played. Showing tables.');

        var tables = document.querySelectorAll('.subquestion-list ');

        tables.forEach(function(table) {

            table.style.display = '';

                    console.log('Table shown:', table.id);

        });
}
	
	

	
// Function to find and update the numeric question with the counter
function updateNumericQuestionWithCounter() {

        // Find all numeric input fields (type N questions)
        var numericInputs = document.querySelectorAll('input[type="text"].numeric');

            if (numericInputs.length === 0) {

                        console.log('No numeric input fields found.');
                return;
            }

        // Assuming the first numeric input is the target
        var targetInput = numericInputs[0];

            if (typeof currentSubfolderCounter !== 'undefined') {

                targetInput.value = currentSubfolderCounter;

                        console.log('Counter updated in question input:', currentSubfolderCounter);

            } else {

                        onsole.log('Counter value not found.');
            }
}

// Call the function to update the numeric question
updateNumericQuestionWithCounter();
	
	
// New function to retrieve and log question IDs from audio players
function logAudioPlayerQuestionIds() {

        var audioPlayers = document.querySelectorAll('.audio-player');

        audioPlayers.forEach(function(player) {

            var questionId = player.getAttribute('data-question-id');

                        console.log('Audio player for questionId:', questionId);

        });
}
	
	
	
// Call the new function to log question IDs
logAudioPlayerQuestionIds();
	
	
var allAudiosPlayed = {}; // Object to track the play status of all audios 

	            console.log('Object of play status of all audios Info:', allAudiosPlayed); // Log the global question type info
	
});



