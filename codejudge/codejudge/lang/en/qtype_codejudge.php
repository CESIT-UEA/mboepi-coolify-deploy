<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Coding question';
$string['pluginname_help'] = 'A question type for code-based responses evaluated asynchronously by AI.';
$string['pluginname_link'] = 'question/type/codejudge';
$string['pluginnameadding'] = 'Adding a coding question';
$string['pluginnameediting'] = 'Editing a coding question';
$string['pluginnamesummary'] = 'A question type for programming answers with AI-based evaluation.';

$string['language'] = 'Programming language';
$string['allowedlanguage'] = 'Allowed language';
$string['rubric'] = 'Rubric';
$string['startercode'] = 'Starter code';
$string['editorheight'] = 'Editor height';
$string['responseeditor'] = 'Code editor';
$string['editor_help'] = 'Write your answer in the editor below. Press Tab to indent code.';
$string['lang_python'] = 'Python';
$string['lang_c'] = 'C';
$string['lang_java'] = 'Java';
$string['lang_javascript'] = 'JavaScript';
$string['settings_heading'] = 'AI configuration';
$string['settings_heading_desc'] = 'Configure the provider used to test and evaluate codejudge submissions.';
$string['provider_core_ai'] = 'Moodle core AI';
$string['provider_openai'] = 'OpenAI';
$string['provider_anthropic'] = 'Anthropic';
$string['provider_gemini'] = 'Google Gemini';
$string['provider_ollama'] = 'Ollama';
$string['settings_provider'] = 'AI provider';
$string['settings_provider_desc'] = 'Select which provider the plugin should use.';
$string['settings_api_key'] = 'API key';
$string['settings_api_key_desc'] = 'Provider authentication token, when required.';
$string['settings_base_url'] = 'Base URL';
$string['settings_base_url_desc'] = 'Custom endpoint or proxy URL for providers that support it.';
$string['settings_model_name'] = 'Model name';
$string['settings_model_name_desc'] = 'Model identifier used by the selected provider.';
$string['test_connection'] = 'Test connection';
$string['test_connection_desc'] = 'Run a connectivity test against the configured provider.';
$string['test_connection_testing'] = 'Testing...';
$string['task_grade_submission'] = 'Codejudge AI grading task';
$string['grading_status_queued'] = 'The grading request is queued.';
$string['grading_status_processing'] = 'The grading request is being processed.';
$string['grading_status_graded'] = 'The grading request has finished.';
$string['grading_status_error'] = 'The grading request failed.';
$string['connection_success'] = 'Connection succeeded.';
$string['connection_failed'] = 'Connection failed: {$a}';
$string['error_ai_response_invalid'] = 'The AI provider returned an invalid response.';
$string['error_provider_not_configured'] = 'The AI provider is not configured correctly.';
$string['error_invalid_question_type'] = 'The selected question is not a codejudge question.';
$string['error_invalid_userid'] = 'A valid user id is required to queue a grading request.';
$string['grading_status_unavailable'] = 'Grading status tracking is not available yet.';
$string['error_empty_rubric'] = 'The rubric cannot be empty.';
$string['error_no_language'] = 'Select a programming language.';
$string['error_invalid_language'] = 'Select a supported programming language.';
$string['error_invalid_editorheight'] = 'The editor height must be at least 200 pixels.';
$string['privacy:metadata'] = 'The codejudge question type does not store personal data beyond core Moodle question records.';
