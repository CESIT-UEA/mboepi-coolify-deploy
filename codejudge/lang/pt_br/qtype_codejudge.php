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

$string['pluginname'] = 'Questão de código';
$string['pluginname_help'] = 'Um tipo de questão para respostas em código avaliadas assincronamente por IA.';
$string['pluginname_link'] = 'question/type/codejudge';
$string['pluginnameadding'] = 'Adicionando uma questão de código';
$string['pluginnameediting'] = 'Editando uma questão de código';
$string['pluginnamesummary'] = 'Tipo de questão para respostas em programação com avaliação por IA.';

$string['language'] = 'Linguagem de programação';
$string['allowedlanguage'] = 'Linguagem permitida';
$string['rubric'] = 'Rubrica de correção';
$string['startercode'] = 'Código inicial';
$string['editorheight'] = 'Altura do editor';
$string['responseeditor'] = 'Editor de código';
$string['editor_help'] = 'Escreva sua resposta no editor abaixo. Pressione Tab para indentar o código.';
$string['lang_python'] = 'Python';
$string['lang_c'] = 'C';
$string['lang_java'] = 'Java';
$string['lang_javascript'] = 'JavaScript';
$string['settings_heading'] = 'Configuração de IA';
$string['settings_heading_desc'] = 'Configure o provedor usado para testar e avaliar submissões do codejudge.';
$string['provider_core_ai'] = 'IA central do Moodle';
$string['provider_openai'] = 'OpenAI';
$string['provider_anthropic'] = 'Anthropic';
$string['provider_gemini'] = 'Google Gemini';
$string['provider_ollama'] = 'Ollama';
$string['settings_provider'] = 'Provedor de IA';
$string['settings_provider_desc'] = 'Selecione qual provedor o plugin deve usar.';
$string['settings_api_key'] = 'Chave de API';
$string['settings_api_key_desc'] = 'Token de autenticação do provedor, quando necessário.';
$string['settings_base_url'] = 'URL base';
$string['settings_base_url_desc'] = 'Endpoint personalizado ou URL de proxy para provedores que suportam isso.';
$string['settings_model_name'] = 'Nome do modelo';
$string['settings_model_name_desc'] = 'Identificador do modelo usado pelo provedor selecionado.';
$string['test_connection'] = 'Testar conexão';
$string['test_connection_desc'] = 'Executa um teste de conectividade com o provedor configurado.';
$string['test_connection_testing'] = 'Testando...';
$string['task_grade_submission'] = 'Tarefa de correção por IA do codejudge';
$string['grading_status_queued'] = 'A solicitação de correção está na fila.';
$string['grading_status_processing'] = 'A solicitação de correção está em processamento.';
$string['grading_status_graded'] = 'A solicitação de correção foi concluída.';
$string['grading_status_error'] = 'A solicitação de correção falhou.';
$string['connection_success'] = 'Conexão realizada com sucesso.';
$string['connection_failed'] = 'Falha na conexão: {$a}';
$string['error_ai_response_invalid'] = 'O provedor de IA retornou uma resposta inválida.';
$string['error_provider_not_configured'] = 'O provedor de IA não está configurado corretamente.';
$string['error_invalid_question_type'] = 'A questão selecionada não é uma questão codejudge.';
$string['error_invalid_userid'] = 'Um ID de usuário válido é necessário para enfileirar uma solicitação de correção.';
$string['grading_status_unavailable'] = 'O rastreamento do status de correção ainda não está disponível.';
$string['error_empty_rubric'] = 'A rubrica não pode ficar vazia.';
$string['error_no_language'] = 'Selecione uma linguagem de programação.';
$string['error_invalid_language'] = 'Selecione uma linguagem de programação suportada.';
$string['error_invalid_editorheight'] = 'A altura do editor deve ser de pelo menos 200 pixels.';
$string['privacy:metadata'] = 'O tipo de questão codejudge não armazena dados pessoais além dos registros centrais do Moodle.';
