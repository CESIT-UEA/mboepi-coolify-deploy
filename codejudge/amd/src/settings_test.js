/**
 * Connections testing JS for qtype_codejudge settings page.
 *
 * @package     qtype_codejudge
 * @copyright   2026 IA Judge Contributors
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery', 'core/ajax'], function($, Ajax) {
    return {
        /**
         * Initialize the test connection button listener.
         */
        init: function() {
            var button = $('#ia-judge-test-connection-btn');
            var resultSpan = $('#ia-judge-test-result');
            var resultMessage = resultSpan.find('.ia-judge-test-result-message');
            var resultSpinner = resultSpan.find('.spinner-border');
            var buttonLabel = button.find('.ia-judge-test-button-label');
            var buttonSpinner = button.find('.spinner-border');

            if (!button.length) {
                return;
            }

            var setResultState = function(state, message) {
                resultSpan.removeClass('text-muted text-success text-danger');
                resultSpan.addClass(state.className);
                if (resultMessage.length) {
                    resultMessage.text(message || '');
                }
                if (resultSpinner.length) {
                    resultSpinner.toggleClass('d-none', !state.loading);
                }
            };

            button.on('click', function() {
                // Find settings inputs.
                // Moodle settings form fields use prefix s_qtype_codejudge_
                var provider = $('[name="s_qtype_codejudge_provider"]').val()
                    || $('[name$="provider"]').val();
                var apiKey = $('[name="s_qtype_codejudge_api_key"]').val()
                    || $('[name$="api_key"]').val();
                var baseUrl = $('[name="s_qtype_codejudge_base_url"]').val()
                    || $('[name$="base_url"]').val();
                var modelName = $('[name="s_qtype_codejudge_model_name"]').val()
                    || $('[name$="model_name"]').val();

                // Clear prior result and show loading state
                setResultState({
                    className: 'text-muted',
                    loading: true
                }, M.util.get_string('test_connection_testing', 'qtype_codejudge'));
                if (buttonSpinner.length) {
                    buttonSpinner.removeClass('d-none');
                }
                if (buttonLabel.length) {
                    buttonLabel.text(M.util.get_string('test_connection_testing', 'qtype_codejudge'));
                }
                button.prop('disabled', true);

                // Call Moodle external function
                Ajax.call([{
                    methodname: 'qtype_codejudge_test_connection',
                    args: {
                        provider: provider,
                        api_key: apiKey || '',
                        base_url: baseUrl || '',
                        model_name: modelName || ''
                    }
                }])[0].then(function(data) {
                    button.prop('disabled', false);
                    if (buttonSpinner.length) {
                        buttonSpinner.addClass('d-none');
                    }
                    if (buttonLabel.length) {
                        buttonLabel.text(button.attr('data-default-label') || M.util.get_string('test_connection', 'qtype_codejudge'));
                    }
                    if (data.success) {
                        setResultState({className: 'text-success', loading: false}, data.message);
                    } else {
                        setResultState({className: 'text-danger', loading: false}, data.message);
                    }
                }).catch(function(error) {
                    button.prop('disabled', false);
                    if (buttonSpinner.length) {
                        buttonSpinner.addClass('d-none');
                    }
                    if (buttonLabel.length) {
                        buttonLabel.text(button.attr('data-default-label') || M.util.get_string('test_connection', 'qtype_codejudge'));
                    }
                    var errorMessage = error.message || error.error || 'Connection request failed';
                    setResultState({className: 'text-danger', loading: false}, errorMessage);
                });
            });
        }
    };
});
